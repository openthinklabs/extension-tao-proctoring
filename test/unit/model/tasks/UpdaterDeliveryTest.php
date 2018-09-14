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
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */
namespace oat\taoProctoring\test\integration\model\authorization;

use oat\oatbox\service\ServiceManager;
use Prophecy\Argument;
use oat\taoDelivery\model\execution\OntologyDeliveryExecution;
use oat\taoProctoring\model\monitorCache\DeliveryMonitoringService;
use oat\taoProctoring\model\monitorCache\implementation\MonitoringStorage;
use oat\taoProctoring\model\Tasks\DeliveryUpdaterTask;
use oat\taoProctoring\scripts\install\db\DbSetup;
use oat\generis\test\TestCase;
use Zend\ServiceManager\ServiceLocatorInterface;
use oat\taoProctoring\model\monitorCache\DeliveryMonitoringData;


/**
 * Test the UpdaterDeliveryTest
 *
 * @author Aleksej Tikhanovich <aleksej@taotesting.com>
 */
class UpdaterDeliveryTest extends TestCase
{
    /** @var string  */
    const DELIVERY_ID = 'http://sample/first.rdf#i1450191587554175_test_record';

    /**
     * Test the UpdateDelivery task for updating labels
     */
    public function testUpdateDeliveryLabels()
    {
        $monitoringService = $this->getMonitoringStorage();
        $this->loadFixture($monitoringService);
        
        $deliveryUpdaterTask = new DeliveryUpdaterTask();
        $sl = $this->prophesize(ServiceLocatorInterface::class);
        $sl->get(DeliveryMonitoringService::SERVICE_ID)->willReturn($monitoringService);
        $deliveryUpdaterTask->setServiceLocator($sl->reveal());

        $update = $deliveryUpdaterTask->updateDeliveryLabels(self::DELIVERY_ID, 'Delivery test 2');
        $this->assertTrue($update);

        $result = $monitoringService->find([
            [MonitoringStorage::DELIVERY_ID => self::DELIVERY_ID],
        ]);
        $this->assertEquals(count($result), 1);
        $this->assertEquals($result[0]->get()[MonitoringStorage::DELIVERY_NAME], 'Delivery test 2', 1);
    }
    
    
    /**
     * Init DeliveryMonitoring Service
     */
    protected function getMonitoringStorage()
    {
        $pmMock = $this->getSqlMock('test_monitoring');
        $persistence = $pmMock->getPersistenceById('test_monitoring');
        DbSetup::generateTable($persistence);
        $slPm = $this->prophesize(ServiceLocatorInterface::class);
        $slPm->get(\common_persistence_Manager::SERVICE_ID)->willReturn($pmMock);
        
        $deliveryMonitoringService = new MonitoringStorage([
            MonitoringStorage::OPTION_PERSISTENCE => 'test_monitoring',
            MonitoringStorage::OPTION_PRIMARY_COLUMNS => array(
                'delivery_execution_id',
                'status',
                'current_assessment_item',
                'test_taker',
                'authorized_by',
                'start_time',
                'end_time',
                'delivery_name',
                'delivery_id'
            )
        ]);
        $deliveryMonitoringService->setServiceLocator($slPm->reveal());
        return $deliveryMonitoringService;
    }
    
    /**
     * Load fixtures for delivery monitoring table
     * @param DeliveryMonitoringService
     * @return array
     */
    protected function loadFixture(DeliveryMonitoringService $deliveryMonitoringService)
    {
        $data = $this->prophesize(DeliveryMonitoringData::class);
        $data->get()->willReturn([
            MonitoringStorage::COLUMN_DELIVERY_EXECUTION_ID => 'http://sample/first.rdf#i1450192587555880_test_record',
            MonitoringStorage::COLUMN_TEST_TAKER => 'test_taker_1',
            MonitoringStorage::COLUMN_STATUS => 'active_test',
            OntologyDeliveryExecution::PROPERTY_SUBJECT => 'http://sample/first.rdf#i1450191587554175_test_user',
            MonitoringStorage::DELIVERY_NAME => 'Delivery test 1',
            MonitoringStorage::DELIVERY_ID => self::DELIVERY_ID,
        ]);
        $data->validate()->willReturn(true);
        $deliveryMonitoringService->save($data->reveal());
    }
    
    
    /**
     * Returns a persistence Manager with a mocked sql persistence
     *
     * @param string $key identifier of the persistence
     * @return \common_persistence_Manager
     */
    protected function getSqlMock($key)
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('sqlite not found, tests skipped.');
        }
        $driver = new \common_persistence_sql_dbal_Driver();
        $persistence = $driver->connect($key, ['connection' => ['url' => 'sqlite:///:memory:']]);
        $pmProphecy = $this->prophesize(\common_persistence_Manager::class);
        $pmProphecy->setServiceLocator(Argument::any())->willReturn(null);
        $pmProphecy->getPersistenceById($key)->willReturn($persistence);
        return $pmProphecy->reveal();
    }
    
}
