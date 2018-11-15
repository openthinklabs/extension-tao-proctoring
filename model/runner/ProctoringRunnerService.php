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
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA ;
 */

namespace oat\taoProctoring\model\runner;

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\user\User;
use oat\taoDelivery\model\execution\ServiceProxy;
use oat\taoProctoring\model\authorization\TestTakerAuthorizationService;
use oat\taoProctoring\model\ProctorService;
use oat\taoQtiTest\models\ExtendedStateService;
use oat\taoQtiTest\models\runner\QtiRunnerPausedException;
use oat\taoQtiTest\models\runner\QtiRunnerService;
use oat\taoQtiTest\models\runner\RunnerServiceContext;
use oat\taoQtiTest\models\runner\session\TestSession;
use oat\taoQtiTest\models\SectionPauseService;
use qtism\runtime\tests\AssessmentTestSessionState;

/**
 * Class ProctoringRunnerService
 *
 * QTI implementation service for the test runner
 *
 * @package oat\taoProctoring\model\runner
 */
class ProctoringRunnerService extends QtiRunnerService
{
    use OntologyAwareTrait;

    const IS_PROCTORED_KEY = 'is_proctored_delivery';

    private $isProctored = null;

    /**
     * Get Test Context.
     *
     * @param RunnerServiceContext $context
     * @return array
     * @throws \common_Exception
     * @throws \common_exception_NotFound
     * @throws \core_kernel_persistence_Exception
     */
    public function getTestContext(RunnerServiceContext $context)
    {
        $response = parent::getTestContext($context);
        $response['securePauseStateRequired'] = $this->isSecurePauseStateRequired($context, $response);

        return $response;
    }

    /**
     * Check if delivery is proctored.
     *
     * @param TestSession $session
     * @return bool
     * @throws \common_Exception
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     */
    public function isProctored(TestSession $session)
    {
        if (is_null($this->isProctored)) {
            $this->isProctored = false;

            /** @var ExtendedStateService $extendedStateService */
            $extendedStateService = $this->getServiceLocator()->get(ExtendedStateService::SERVICE_ID);
            $isProctored = $extendedStateService->getValue($session->getSessionId(), self::IS_PROCTORED_KEY);

            if (is_null($isProctored)) {
                $user = \common_session_SessionManager::getSession()->getUser();

                // Get value from storage
                $deliveryExecution = ServiceProxy::singleton()->getDeliveryExecution($session->getSessionId());

                /** @var TestTakerAuthorizationService $authorizationService */
                $authorizationService = $this->getServiceManager()->get(TestTakerAuthorizationService::SERVICE_ID);
                $this->isProctored = $authorizationService->isProctored($deliveryExecution->getDelivery(), $user);

                $extendedStateService->setValue($session->getSessionId(), self::IS_PROCTORED_KEY, $this->isProctored);
            }
        }

        return $this->isProctored;
    }

    /**
     * Check whether the test is in a runnable state.
     *
     * @param RunnerServiceContext $context
     * @return bool
     * @throws \common_Exception
     * @throws \oat\taoQtiTest\models\runner\QtiRunnerClosedException
     * @throws QtiRunnerPausedException
     */
    public function check(RunnerServiceContext $context)
    {
        parent::check($context);

        $state = $context->getTestSession()->getState();

        if ($state == AssessmentTestSessionState::SUSPENDED) {
            throw new QtiRunnerPausedException();
        }

        return true;
    }

    /**
     * Check if securePauseStateRequired is required.
     *
     * @param RunnerServiceContext $context
     * @param $response
     * @return mixed
     * @throws \common_Exception
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     */
    protected function isSecurePauseStateRequired(RunnerServiceContext $context, $response)
    {
        if (isset($response['options']['sectionPause'])) {
            $securePauseIsRequired = $response['options']['sectionPause'];
        } else {
            $securePauseIsRequired = $this->isProctored($context->getTestSession());
        }

        return $securePauseIsRequired;
    }
}
