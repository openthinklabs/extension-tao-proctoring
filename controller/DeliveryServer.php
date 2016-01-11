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
 * Copyright (c) 2015 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 *
 */
namespace oat\taoProctoring\controller;

use oat\taoProctoring\model\DeliveryExecutionStateService;
use PHPSession;
use common_Logger;
use oat\taoDelivery\models\classes\execution\DeliveryExecution;
use common_session_SessionManager;
use oat\taoDelivery\controller\DeliveryServer as DefaultDeliveryServer;
use oat\oatbox\service\ServiceManager;
use oat\taoProctoring\model\DeliveryAuthorizationService;

/**
 * Override the default DeliveryServer Controller
 *
 * @package taoProctoring
 */
class DeliveryServer extends DefaultDeliveryServer
{

    /** @var DeliveryAuthorizationService */
    public $authorizationService;

    /**
     * constructor: initialize the service and the default data
     * @return DeliveryServer
     */
    public function __construct()
    {
        $this->authorizationService = ServiceManager::getServiceManager()->get(DeliveryAuthorizationService::SERVICE_ID);
        parent::__construct();
    }

    /**
     * Overrides the content extension data
     * @see {@link \taoDelivery_actions_DeliveryServer}
     */
    public function index()
    {
        parent::index();

        // if the test taker passes by this page, he/she cannot access to any delivery without proctor authorization,
        // whatever the delivery execution status is.
        $deliveryExecutionService = \taoDelivery_models_classes_execution_ServiceProxy::singleton();
        $userUri = common_session_SessionManager::getSession()->getUserUri();
        $startedExecutions = array_merge(
            $deliveryExecutionService->getActiveDeliveryExecutions($userUri),
            $deliveryExecutionService->getPausedDeliveryExecutions($userUri)
        );
        foreach($startedExecutions as $startedExecution) {
            $this->authorizationService->revokeAuthorization($startedExecution);
        }
    }

    /**
     * Overrides the return URL
     * @return string the URL
     */
    protected function getReturnUrl()
    {
        return _url('index', 'DeliveryServer', 'taoProctoring');
    }
    
    /**
     * Overwrites the parent initDeliveryExecution()
     * Redirects the test taker to the awaitingAuthorization page after delivery initialization
     */
    public function initDeliveryExecution() 
    {
        // from this page the test taker can only goes to the awaiting page, so always revoke authorization
        $deliveryExecution = $this->_initDeliveryExecution();

        $this->authorizationService->revokeAuthorization($deliveryExecution);
	    $this->redirect(_url('awaitingAuthorization', null, null, array('init' => true, 'deliveryExecution' => $deliveryExecution->getIdentifier())));
	}

    /**
     * Displays the execution screen
     *
     * @throws common_exception_Error
     */
    public function runDeliveryExecution() 
    {
        $deliveryExecution = $this->getCurrentDeliveryExecution();
        $deliveryExecutionStateService = $this->getServiceManager()->get(DeliveryExecutionStateService::SERVICE_ID);
        $executionState = $deliveryExecutionStateService->getState($deliveryExecution);
        
        if (DeliveryExecution::STATE_AUTHORIZED == $executionState && $this->authorizationService->checkAuthorization($deliveryExecution)) {
            // the test taker is authorized to run the delivery
            // but a change is needed to make the delivery execution processable
            $deliveryExecutionStateService->resumeExecution($deliveryExecution->getIdentifier());
            $executionState = $deliveryExecutionStateService->getState($deliveryExecution);
        }

        if (DeliveryExecution::STATE_ACTIVE != $executionState ||
            (DeliveryExecution::STATE_ACTIVE == $executionState && !$this->authorizationService->checkAuthorization($deliveryExecution))) {
            // the test taker is not allowed to run the delivery
            // so we redirect him/her to the awaiting page
            common_Logger::i(get_called_class() . '::runDeliveryExecution(): try to run delivery without proctor authorization for delivery execution ' . $deliveryExecution->getIdentifier() . ' with state ' . $executionState);
            return $this->redirect(_url('awaitingAuthorization', null, null, array('deliveryExecution' => $deliveryExecution->getIdentifier())));
        }

        // ensure the result server object is properly set to avoid test runner issue 
        $this->ensureResultServerObject($deliveryExecution);

        // ok, the delivery execution can be processed
        parent::runDeliveryExecution();
    }
    
    /**
     * The awaiting authorization screen
     */
    public function awaitingAuthorization() 
    {
        $deliveryExecution = $this->getCurrentDeliveryExecution();
        $deliveryExecutionStateService = $this->getServiceManager()->get(DeliveryExecutionStateService::SERVICE_ID);
        $executionState = $deliveryExecutionStateService->getState($deliveryExecution);

        // if the test taker is already authorized, straight forward to the execution
        // note: the authorized state is valid only if the security key has been set,
        // if the test taker tries to directly access this page, the security key may not be initialized (i.e. just logged in)
        if (DeliveryExecution::STATE_AUTHORIZED == $executionState && $this->hasSecurityKey()) {
            $this->authorizationService->grantAuthorization($deliveryExecution);
            return $this->redirect(_url('runDeliveryExecution', null, null, array('deliveryExecution' => $deliveryExecution->getIdentifier())));
        }

        // from this page the test taker must wait for proctor authorization
        $this->authorizationService->revokeAuthorization($deliveryExecution);

        // if the test is in progress, first pause it to avoid inconsistent storage state
        if (DeliveryExecution::STATE_ACTIVE == $executionState) {
            $deliveryExecutionStateService->pauseExecution($deliveryExecution->getIdentifier());
        }

        // we need to change the state of the delivery execution
        if (DeliveryExecution::STATE_TERMINATED != $executionState && DeliveryExecution::STATE_FINISHED != $executionState) {
            $deliveryExecutionStateService->waitExecution($deliveryExecution->getIdentifier());
            $executionState = $deliveryExecutionStateService->getState($deliveryExecution);
        }

        if (DeliveryExecution::STATE_AWAITING == $executionState) {
            $this->setData('deliveryExecution', $deliveryExecution->getIdentifier());
            $this->setData('deliveryLabel', $deliveryExecution->getLabel());
            $this->setData('init', !!$this->getRequestParameter('init'));
            $this->setData('returnUrl', $this->getReturnUrl());
            $this->setData('userLabel', common_session_SessionManager::getSession()->getUserLabel());
            $this->setData('client_config_url', $this->getClientConfigUrl());
            $this->setData('showControls', true);

            //set template
            $this->setData('content-template', 'DeliveryServer/awaiting.tpl');
            $this->setData('content-extension', 'taoProctoring');
            $this->setView('DeliveryServer/layout.tpl', 'taoDelivery');
        } else {
            // inconsistent state
            common_Logger::i(get_called_class() . '::awaitingAuthorization(): cannot wait authorization for delivery execution ' . $deliveryExecution->getIdentifier() . ' with state ' . $executionState);
            return $this->redirect(_url('index'));
        }
    }
    
    /**
     * The action called to check if the requested delivery execution has been authorized by the proctor
     */
    public function isAuthorized()
    {
        $deliveryExecution = $this->getCurrentDeliveryExecution();
        $deliveryExecutionStateService = $this->getServiceManager()->get(DeliveryExecutionStateService::SERVICE_ID);
        $executionState = $deliveryExecutionStateService->getState($deliveryExecution);

        $authorized = false;
        $success = true;
        $message = null;
        
        // reacts to a few particular states
        switch ($executionState) {
            case DeliveryExecution::STATE_AUTHORIZED:
                // note: the authorized state is valid only if the security key has been set,
                // if the test taker tries to directly access this page, the security key may not be initialized (i.e. just logged in)
                if ($this->hasSecurityKey()) {
                    $this->authorizationService->grantAuthorization($deliveryExecution);
                    $authorized = true;
                }
                break;
            
            case DeliveryExecution::STATE_TERMINATED:
            case DeliveryExecution::STATE_FINISHED:
                $success = false;
                $message = __('This test has been terminated');
                break;
                
            case DeliveryExecution::STATE_PAUSED:
                $success = false;
                $message = __('This test has been suspended');
                break;
        }

        $this->returnJson(array(
            'authorized' => $authorized,
            'success' => $success,
            'message' => $message
        ));
    }

    /**
     * Checks if a security key has been set.
     * Left for backward capability.
     * @return bool
     */
    protected function hasSecurityKey()
    {
        return PHPSession::singleton()->hasAttribute(DeliveryAuthorizationService::SECURE_KEY_NAME);
    }

    /**
     * Ensures the result server object is properly set
     * 
     * @param \taoDelivery_models_classes_execution_DeliveryExecution $deliveryExecution
     */
    protected function ensureResultServerObject($deliveryExecution)
    {
        $session = PHPSession::singleton();
        if (!$session->hasAttribute('resultServerObject') || !$session->getAttribute('resultServerObject')) {
            $compiledDelivery = $deliveryExecution->getDelivery();
            $resultServerUri = $compiledDelivery->getOnePropertyValue(new \core_kernel_classes_Property(TAO_DELIVERY_RESULTSERVER_PROP));
            $resultServerObject = new \taoResultServer_models_classes_ResultServer($resultServerUri, array());

            $session->setAttribute('resultServerUri', $resultServerUri->getUri());
            $session->setAttribute('resultServerObject', array($resultServerUri->getUri() => $resultServerObject));
        }
    }
}
