<?php


namespace oat\taoProctoring\scripts\tools;

use oat\oatbox\action\Action;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use common_report_Report as Report;

/**
 * Class PerformanceTest
 * @package oat\taoProctoring\scripts\tools
 *
 * `sudo php index.php 'oat\taoProctoring\scripts\tools\PerformanceTest'
 */
class PerformanceTest implements Action, ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    public function __invoke($params = [])
    {

        $beforeMultiple = microtime(true);
        for ($i = 0; $i < 1; $i++) {
            $this->updateMultiple();
        }
        $afterMultiple = microtime(true);


        return new Report(
            Report::TYPE_SUCCESS,
            json_encode([
                ($afterMultiple-$beforeMultiple). " sec batch update",
            ])
        );
    }

    /**
     * @throws \Exception
     */
    public function updateMultiple()
    {
        /** @var \common_persistence_SqlPersistence $db */
        $db = $this->getServiceLocator()
            ->get(\common_persistence_Manager::SERVICE_ID)
            ->getPersistenceById('default');

        $db->updateMultiple(
            'event_log',
            [
                [
                    'conditions' => [
                        'id' => 10,
                        'action' => "/home/ionut/work/actpg/tao/scripts/taoSetup.php"
                    ],
                    'updateValues' => [
                        'user_roles' => 'roles 12',
                        'occurred' => (new \DateTime())->format('Y-m-d H:m:i'),
                        'properties' => 'properties 11'
                    ]
                ],
                [
                    'conditions' => [
                        'id' => 11,
                        'action' => "/home/ionut/work/actpg/tao/scripts/taoSetup.php"
                    ],
                    'updateValues' => [
                        'user_roles' => 'roles 33',
                        'occurred' => (new \DateTime())->format('Y-m-d H:m:i'),
                        'properties' => 'properties 22'
                    ]
                ],
            ]
        );
    }
}