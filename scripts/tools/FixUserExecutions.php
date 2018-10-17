<?php
/**
 * Copyright (c) 2018 Open Assessment Technologies, S.A.
 *
 */

namespace oat\taoProctoring\scripts\tools;

use oat\dtms\DateTime;
use oat\oatbox\extension\script\ScriptAction;
use \common_report_Report as Report;
use oat\taoDelivery\model\execution\implementation\KeyValueService;
use oat\taoDelivery\model\execution\ServiceProxy;
use oat\taoProctoring\model\monitorCache\DeliveryMonitoringService;
use oat\taoProctoring\model\execution\DeliveryExecution;
use oat\taoDelivery\model\execution\DeliveryExecutionInterface;

/**
 * Class FixMonitoringStates
 *
 * Fix inconsistences in delivery execution kv storage
 *
 * Usage example:
 * ```
 * sudo -u www-data php index.php '\oat\taoProctoring\scripts\tools\FixUserExecutions' --from 1539346659 --to 1539616776 --wetRun 0
 * ```
 * @package oat\taoProctoring\scripts\tools
 */
class FixUserExecutions extends ScriptAction
{
    /** @var Report */
    private $report;

    private $from;
    private $to;
    private $wetRun;
    private $statusesToCheck = [
        DeliveryExecution::STATE_ACTIVE,
        DeliveryExecution::STATE_PAUSED,
        DeliveryExecution::STATE_AWAITING,
        DeliveryExecution::STATE_AUTHORIZED,
    ];
    private $executionService;
    private $persistence;

    /**
     * @return string
     */
    protected function provideDescription()
    {
        return 'Fixed bad Delivery Monitoring entries';
    }

    /**
     * @return array
     */
    protected function provideOptions()
    {
        return [
            'from' => [
                'longPrefix' => 'from',
                'required' => false,
                'description' => 'Date for searching from',
                'defaultValue' => time()
            ],
            'to' => [
                'longPrefix' => 'to',
                'required' => false,
                'description' => 'Date for searching to',
                'defaultValue' => time()
            ],
            'wetRun' => [
                'longPrefix' => 'wetRun',
                'required' => false,
                'description' => 'Wet run',
                'defaultValue' => 0
            ]
        ];
    }

    /**
     * @return Report
     * @throws \common_exception_Error
     */
    protected function run()
    {
        try {
            $this->init();
        } catch (\Exception $e) {
            return new Report(Report::TYPE_ERROR, $e->getMessage());
        }
        /** @var DeliveryMonitoringService $deliveryMonitoringService */
        $deliveryMonitoringService = $this->getServiceLocator()->get(DeliveryMonitoringService::SERVICE_ID);

        $deliveryExecutionsData = $deliveryMonitoringService->find([
            [
                DeliveryMonitoringService::STATUS => $this->statusesToCheck
            ],
            'AND',
            [['start_time' => '<'.$this->to], 'AND', ['start_time' => '>'.$this->from]],
        ]);
        $deliveryExecutionService = ServiceProxy::singleton();
        $this->report->add(new Report(Report::TYPE_INFO, "Found ".sizeof($deliveryExecutionsData). " items."));
        $count = 0;

        foreach ($deliveryExecutionsData as $deliveryExecutionData) {
            $data = $deliveryExecutionData->get();
            $deliveryExecution = $deliveryExecutionService->getDeliveryExecution(
                $data[DeliveryMonitoringService::DELIVERY_EXECUTION_ID]
            );
            try {
                $update = false;

                $executionsByStatus = $deliveryExecutionService->getDeliveryExecutionsByStatus(
                    $deliveryExecution->getUserIdentifier(),
                    $deliveryExecution->getState()->getUri()
                );
                if (!isset($executionsByStatus[$deliveryExecution->getIdentifier()])) {
                    $update = true;
                    $this->fixExecutionStatus($deliveryExecution);
                }

                $userExecutions = $deliveryExecutionService->getUserExecutions($deliveryExecution->getDelivery(), $deliveryExecution->getUserIdentifier());
                if (!isset($userExecutions[$deliveryExecution->getIdentifier()])) {
                    $update = true;
                    $this->fixExecutionList($deliveryExecution);
                }

                if ($update) {
                    $count++;
                }

            } catch (\common_exception_NotFound $e) {
                continue;
            }
        }

        if ($this->wetRun === true) {
            $this->report->add(new Report(Report::TYPE_INFO, "Was updated {$count} items."));
        } else {
            $this->report->add(new Report(Report::TYPE_INFO, "{$count} items to be updated."));
        }
        return $this->report;
    }

    /**
     * Initialize parameters
     */
    private function init()
    {
        $this->from = $this->getOption('from');
        $this->to = $this->getOption('to');
        $this->wetRun = (boolean) $this->getOption('wetRun');
        $this->report = new Report(
            Report::TYPE_INFO,
            'Starting checking delivery monitoring entries');
    }

    private function getExecutionServiceImplementation()
    {
        if ($this->executionService === null) {
            $extension = \common_ext_ExtensionsManager::singleton()->getExtensionById('taoDelivery');
            $this->executionService = $extension->getConfig('execution_service');
        }
        return $this->executionService;
    }

    private function getExecutionServicePersistence()
    {
        if ($this->persistence === null) {
            $persistenceOption = $this->getExecutionServiceImplementation()->getOption(KeyValueService::OPTION_PERSISTENCE);
            $this->persistence = (is_object($persistenceOption))
                ? $persistenceOption
                : $this->getServiceLocator()->get(\common_persistence_Manager::SERVICE_ID)->getPersistenceById($persistenceOption);
        }
        return $this->persistence;
    }


    /**
     * @param DeliveryExecutionInterface $deliveryExecution
     * @throws \common_exception_Error
     * @throws \common_exception_NotFound
     */
    protected function fixExecutionStatus(DeliveryExecutionInterface $deliveryExecution)
    {
        $deliveryExecutionService = $this->getExecutionServiceImplementation();
        $oldStatus = null;
        foreach ($this->statusesToCheck as $status) {
            $executions = $deliveryExecutionService->getDeliveryExecutionsByStatus($deliveryExecution->getUserIdentifier(), $status);
            if (in_array($deliveryExecution->getIdentifier(), $executions)) {
                $oldStatus = $status;
                break;
            }
        }
        if ($deliveryExecutionService instanceof KeyValueService) {
            if ($this->wetRun === true) {
                $deliveryExecutionService->updateDeliveryExecutionStatus($deliveryExecution, $oldStatus, $deliveryExecution->getState());
                $this->report->add(new Report(Report::TYPE_INFO, "Execution was updated. id: {$deliveryExecution->getIdentifier()}; state: {$deliveryExecution->getState()}"));
            } else {
                $this->report->add(new Report(Report::TYPE_INFO, "Execution will be updated. id: {$deliveryExecution->getIdentifier()}; state: {$deliveryExecution->getState()}"));
            }

        }
    }

    /**
     * @param DeliveryExecutionInterface $deliveryExecution
     * @throws \common_exception_Error
     * @throws \common_exception_NotFound
     */
    protected function fixExecutionList(DeliveryExecutionInterface $deliveryExecution)
    {
        $deliveryExecutionService = $this->getExecutionServiceImplementation();
        if ($deliveryExecutionService instanceof KeyValueService) {
            if ($this->wetRun === true) {
                $persistence = $this->getExecutionServicePersistence();
                $uid = KeyValueService::USER_DELIVERY_PREFIX . $deliveryExecution->getUserIdentifier() . $deliveryExecution->getDelivery()->getUri();
                $data = json_decode($persistence->get($uid));
                if (!$data) {
                    $data = [];
                }
                if (!in_array($deliveryExecution->getIdentifier(), $data)) {
                    $data [] = $deliveryExecution->getIdentifier();
                }
                $persistence->set($uid, json_encode($data));
                $this->report->add(new Report(Report::TYPE_INFO, "User executions list was updated. id: {$deliveryExecution->getIdentifier()}; state: {$deliveryExecution->getState()}"));
            } else {
                $this->report->add(new Report(Report::TYPE_INFO, "User executions list will be updated. id: {$deliveryExecution->getIdentifier()};"));
            }
        }
    }
}