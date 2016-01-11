<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2016 (original work) Open Assessment Technologies SA;
 *
 */

namespace oat\taoProctoring\model;

use oat\taoProctoring\model\implementation\ExtendedStateService;
use oat\taoProctoring\model\monitorCache\DeliveryMonitoringService;
use oat\oatbox\service\ConfigurableService;
use oat\taoDelivery\models\classes\execution\DeliveryExecution;
use oat\taoDelivery\model\AssignmentService;
use qtism\runtime\storage\binary\BinaryAssessmentTestSeeker;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\storage\common\AbstractStorage;
use oat\taoQtiTest\models\TestSessionMetaData;
use oat\oatbox\service\ServiceManager;


/**
 * Class DeliveryExecutionStateService
 * @package oat\taoProctoring\model
 * @author Aleh Hutnikau <hutnikau@1pt.com>
 */
class DeliveryExecutionStateService extends ConfigurableService
{
    const SERVICE_ID = 'taoProctoring/DeliveryExecutionState';


    private $executionService;

    /**
     * QtiSm AssessmentTestSession Storage Service
     * @var AbstractStorage
     */
    private $storage;

    /**
     * temporary variable until proper servicemanager integration
     * @var ExtendedStateService
     */
    private $extendedStateService;

    /**
     * Gets the list of allowed states
     * @return array
     */
    public function getAllowedStates()
    {
        return self::$allowedStates;
    }

    /**
     * Computes the state of the delivery and returns one of the extended state code
     *
     * @param DeliveryExecution $deliveryExecution
     * @return null|string
     * @throws \common_Exception
     */
    public function getState(DeliveryExecution $deliveryExecution)
    {
        $executionStatus = $deliveryExecution->getState()->getUri();

        return $executionStatus;
    }

    /**
     * Sets a delivery execution in the awaiting state
     *
     * @param string $executionId
     * @return bool
     */
    public function waitExecution($executionId)
    {
        $deliveryExecution = $this->getExecutionService()->getDeliveryExecution($executionId);
        $executionState = $this->getState($deliveryExecution);
        $result = false;

        if ($executionState !== DeliveryExecution::STATE_FINISHED && $executionState !== DeliveryExecution::STATE_TERMINATED) {
            ServiceManager::getServiceManager()->get(DeliveryAuthorizationService::SERVICE_ID)->revokeAuthorization($deliveryExecution);
            $this->setProctoringState($deliveryExecution->getIdentifier(), DeliveryExecution::STATE_AWAITING);

            $result = true;
        }

        return $result;
    }

    /**
     * Sets a delivery execution in the inprogress state
     *
     * @param string $executionId
     * @return bool
     */
    public function resumeExecution($executionId)
    {
        $deliveryExecution = $this->getExecutionService()->getDeliveryExecution($executionId);
        $executionState = $this->getState($deliveryExecution);
        $result = false;

        if ($executionState === DeliveryExecution::STATE_AUTHORIZED) {
            $session = $this->getTestSession($deliveryExecution);
            if ($session) {
                $this->resumeSession($session);
            }

            $this->setProctoringState($deliveryExecution->getIdentifier(), DeliveryExecution::STATE_ACTIVE);

            $result = true;
        }

        return $result;
    }

    /**
     * Authorises a delivery execution
     *
     * @param string $executionId
     * @param array $reason
     * @return bool
     */
    public function authoriseExecution($executionId, $reason = null)
    {
        $deliveryExecution = $this->getExecutionService()->getDeliveryExecution($executionId);
        $result = false;

        if ($this->getState($deliveryExecution) === DeliveryExecution::STATE_AWAITING) {
            ServiceManager::getServiceManager()->get(DeliveryAuthorizationService::SERVICE_ID)->grantAuthorization($deliveryExecution);
            $session = $this->getTestSession($deliveryExecution);
            if ($session) {
                $this->setTestVariable($session, 'TEST_AUTHORISE', $reason);
                $this->getStorage()->persist($session);
            }

            $this->setProctoringState($deliveryExecution->getIdentifier(), DeliveryExecution::STATE_AUTHORIZED, $reason);

            $result = true;
        }

        return $result;
    }

    /**
     * Terminates a delivery execution
     *
     * @param string $executionId
     * @param array $reason
     * @return bool
     */
    public function terminateExecution($executionId, $reason = null)
    {
        $deliveryExecution = $this->getExecutionService()->getDeliveryExecution($executionId);
        $executionState = $this->getState($deliveryExecution);
        $result = false;

        if ($executionState !== DeliveryExecution::STATE_TERMINATED && $executionState !== DeliveryExecution::STATE_FINISHED) {
            $session = $this->getTestSession($deliveryExecution);
            if ($session) {
                $testSessionMetaData = new TestSessionMetaData($session);
                $testSessionMetaData->save(array(
                    'TEST' => array(
                        'TEST_EXIT_CODE' => TestSessionMetaData::TEST_CODE_TERMINATED,
                        $this->nameTestVariable($session, 'TEST_TERMINATE') => $this->encodeTestVariable($reason)
                    ),
                    'SECTION' => array('SECTION_EXIT_CODE' => TestSessionMetaData::SECTION_CODE_FORCE_QUIT),
                ));

                $this->finishSession($session);
            }

            $this->setProctoringState($deliveryExecution->getIdentifier(), DeliveryExecution::STATE_TERMINATED, $reason);

            $result = true;
        }

        return $result;
    }

    /**
     * Pauses a delivery execution
     *
     * @param string $executionId
     * @param array $reason
     * @return bool
     */
    public function pauseExecution($executionId, $reason = null)
    {
        $deliveryExecution = $this->getExecutionService()->getDeliveryExecution($executionId);
        $executionState = $this->getState($deliveryExecution);
        $result = false;

        if ($executionState !== DeliveryExecution::STATE_TERMINATED && $executionState !== DeliveryExecution::STATE_FINISHED) {
            $session = $this->getTestSession($deliveryExecution);
            if ($session) {
                $this->setTestVariable($session, 'TEST_PAUSE', $reason);
                $this->suspendSession($session);
            }

            $this->setProctoringState($deliveryExecution->getIdentifier(), DeliveryExecution::STATE_PAUSED, $reason);

            $result = true;
        }

        return $result;
    }

    /**
     * Report irregularity to a delivery execution
     *
     * @param string $executionId
     * @param array $reason
     * @return bool
     */
    public function reportExecution($executionId, $reason)
    {
        $deliveryMonitoringData = $this->getDeliveryMonitoringService()->getData($executionId);
        /* todo do not override irregularities */
        $deliveryMonitoringData->add('TEST_IRREGULARITY', $reason, true);
        return $this->getDeliveryMonitoringService()->save($deliveryMonitoringData);
    }

    /**
     * Sets a proctoring state on a delivery execution. Use the test state storage.
     * @param string|DeliveryExecution $executionId
     * @param string $state
     * @param array $reason
     */
    public function setProctoringState($executionId, $state, $reason = null)
    {
        $deliveryExecution = $this->getExecutionService()->getDeliveryExecution($executionId);
        $deliveryExecution->setState($state);

        $deliveryMonitoringService = $this->getDeliveryMonitoringService();

        $deliveryMonitoringData = $deliveryMonitoringService->getData($deliveryExecution->getIdentifier());
        $deliveryMonitoringData->add(DeliveryMonitoringService::STATUS, $state, true);
        $deliveryMonitoringData->add('reason', $reason, true);
        $deliveryMonitoringService->save($deliveryMonitoringData);
    }

    /**
     * Gets a proctoring state from a delivery execution. Use the test state storage.
     * @param string|DeliveryExecution $executionId
     * @return array
     */
    public function getProctoringState($executionId)
    {
        $deliveryMonitoringData = $this->getDeliveryMonitoringService()->getData($executionId);
        $proctoringState = $deliveryMonitoringData->get();

        if (!isset($proctoringState['reason'])) {
            $proctoringState['reason'] = null;
        }

        return $proctoringState;
    }

    /**
     * Gets the test session for a particular deliveryExecution
     *
     * @param DeliveryExecution $deliveryExecution
     * @return \qtism\runtime\tests\AssessmentTestSession
     * @throws \common_exception_Error
     * @throws \common_exception_MissingParameter
     */
    public function getTestSession(DeliveryExecution $deliveryExecution)
    {
        $resultServer = \taoResultServer_models_classes_ResultServerStateFull::singleton();

        $compiledDelivery = $deliveryExecution->getDelivery();
        $inputParameters = $this->getRuntimeInputParameters($deliveryExecution);

        $testDefinition = \taoQtiTest_helpers_Utils::getTestDefinition($inputParameters['QtiTestCompilation']);
        $testResource = new \core_kernel_classes_Resource($inputParameters['QtiTestDefinition']);

        $sessionManager = new \taoQtiTest_helpers_SessionManager($resultServer, $testResource);

        $qtiStorage = new \taoQtiTest_helpers_TestSessionStorage(
            $sessionManager,
            new BinaryAssessmentTestSeeker($testDefinition), $deliveryExecution->getUserIdentifier()
        );
        $this->setStorage($qtiStorage);

        $sessionId = $deliveryExecution->getIdentifier();

        if ($qtiStorage->exists($sessionId)) {
            $session = $qtiStorage->retrieve($testDefinition, $sessionId);

            $resultServerUri = $compiledDelivery->getOnePropertyValue(new \core_kernel_classes_Property(TAO_DELIVERY_RESULTSERVER_PROP));
            $resultServerObject = new \taoResultServer_models_classes_ResultServer($resultServerUri, array());
            $resultServer->setValue('resultServerUri', $resultServerUri->getUri());
            $resultServer->setValue('resultServerObject', array($resultServerUri->getUri() => $resultServerObject));
            $resultServer->setValue('resultServer_deliveryResultIdentifier', $deliveryExecution->getIdentifier());
        } else {
            $session = null;
        }

        return $session;
    }

    /**
     * Finishes the session of a delivery execution
     *
     * @param AssessmentTestSession $session
     * @throws \qtism\runtime\tests\AssessmentTestSessionException
     */
    public function finishSession(AssessmentTestSession $session)
    {
        if ($session) {
            $session->endTestSession();
            $this->getStorage()->persist($session);
        }
    }

    /**
     * Suspends the session of a delivery execution
     *
     * @param AssessmentTestSession $session
     */
    public function suspendSession(AssessmentTestSession $session)
    {
        if ($session) {
            $session->suspend();
            $this->getStorage()->persist($session);
        }
    }

    /**
     * Resumes the session of a delivery execution
     *
     * @param AssessmentTestSession $session
     */
    public function resumeSession(AssessmentTestSession $session)
    {
        if ($session) {
            $session->resume();
            $this->getStorage()->persist($session);
        }
    }

    /**
     *
     * @param DeliveryExecution $deliveryExecution
     * @return array
     * Exapmple:
     * <pre>
     * array(
     *   'QtiTestCompilation' => 'http://sample/first.rdf#i14369768868163155-|http://sample/first.rdf#i1436976886612156+',
     *   'QtiTestDefinition' => 'http://sample/first.rdf#i14369752345581135'
     * )
     * </pre>
     */
    public function getRuntimeInputParameters(DeliveryExecution $deliveryExecution)
    {
        $compiledDelivery = $deliveryExecution->getDelivery();
        $runtime = $this->getServiceManager()->get(AssignmentService::CONFIG_ID)->getRuntime($compiledDelivery->getUri());
        $inputParameters = \tao_models_classes_service_ServiceCallHelper::getInputValues($runtime, array());

        return $inputParameters;
    }

    /**
     * Gets delivery execution service
     *
     * @return \taoDelivery_models_classes_execution_ServiceProxy
     */
    private function getExecutionService()
    {
        if ($this->executionService === null) {
            $this->executionService = \taoDelivery_models_classes_execution_ServiceProxy::singleton();
        }
        return $this->executionService;
    }

    /**
     * temporary helper until proper servicemanager integration
     * @return ExtendedStateService
     */
    /*private function getExtendedStateService()
    {
        if (!isset($this->extendedStateService)) {
            $this->extendedStateService = new ExtendedStateService();
        }
        return $this->extendedStateService;
    }*/

    /**
     * Sets a test variable with name automatic suffix
     * @param AssessmentTestSession $session
     * @param string $name
     * @param mixe $value
     */
    private function setTestVariable(AssessmentTestSession $session, $name, $value)
    {
        $testSessionMetaData = new TestSessionMetaData($session);
        $testSessionMetaData->save(array(
            'TEST' => array(
                $this->nameTestVariable($session, $name) => $this->encodeTestVariable($value)
            )
        ));
    }

    /**
     * Build a variable name based on the current position inside the test
     * @param AssessmentTestSession $session
     * @param string $name
     * @return string
     */
    private function nameTestVariable(AssessmentTestSession $session, $name)
    {
        $varName = array($name);
        if ($session) {
            $varName[] = $session->getCurrentAssessmentItemRef();
            $varName[] = $session->getCurrentAssessmentItemRefOccurence();
            $varName[] = time();
        }
        return implode('.', $varName);
    }

    /**
     * Encodes a test variable
     * @param mixed $value
     * @return string
     */
    private function encodeTestVariable($value)
    {
        return json_encode(array(
            'timestamp' => microtime(),
            'details' => $value
        ));
    }

    /**
     * Get the QtiSm AssessmentTestSession Storage Service.
     *
     * @return AbstractStorage An AssessmentTestSession Storage Service.
     */
    private function getStorage() {
        return $this->storage;
    }

    /**
     * Set the QtiSm AssessmentTestSession Storage Service.
     *
     * @param AbstractStorage $storage An AssessmentTestSession Storage Service.
     */
    private function setStorage(AbstractStorage $storage) {
        $this->storage = $storage;
    }

    /**
     * Get delivery monitoring service
     * @return DeliveryMonitoringService
     */
    private function getDeliveryMonitoringService()
    {
        return ServiceManager::getServiceManager()->get(DeliveryMonitoringService::CONFIG_ID);
    }

}