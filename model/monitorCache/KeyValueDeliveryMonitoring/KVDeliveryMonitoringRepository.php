<?php

namespace oat\taoProctoring\model\monitorCache\KeyValueDeliveryMonitoring;

use common_persistence_Persistence;

interface KVDeliveryMonitoringRepository
{
    const SERVICE_ID = 'taoProctoring/KVDeliveryMonitoringRepository';

    /**
     * @return common_persistence_Persistence
     */
    public function getPersistence();

    /**
     * @param KVDeliveryMonitoringCollection $collection
     */
    public function insertCollection(KVDeliveryMonitoringCollection $collection);

    /**
     * @param KVDeliveryMonitoringCollection $collection
     */
    public function updateCollection(KVDeliveryMonitoringCollection $collection);

    /**
     * @param string $deliveryId
     * @param array $availableKeys
     * @return KVDeliveryMonitoringCollection
     */
    public function findDeliveryKVCollection($deliveryId, $availableKeys);

    /**
     * @param array $filters
     * @param array $options
     * @return mixed
     */
    public function searchDeliveryKVCollections(array $filters = [], array $options = []);
}