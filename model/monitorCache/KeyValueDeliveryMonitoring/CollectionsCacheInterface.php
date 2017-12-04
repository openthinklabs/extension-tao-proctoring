<?php

namespace oat\taoProctoring\model\monitorCache\KeyValueDeliveryMonitoring;

interface CollectionsCacheInterface
{
    /**
     * @param array $collections
     */
    public function saveAll(array $collections);

    /**
     * @return bool|void
     */
    public function reset();

    /**
     * @param $deliveryId
     * @return bool|KVDeliveryMonitoringCollection
     */
    public function fetchCollection($deliveryId);

    /**
     * @param string $deliveryId
     */
    public function deleteCollection($deliveryId);

    /**
     * @param KVDeliveryMonitoringCollection $collection
     */
    public function updateCollection(KVDeliveryMonitoringCollection $collection);

    /**
     * @param KVDeliveryMonitoringCollection $collection
     */
    public function addCollection(KVDeliveryMonitoringCollection $collection);

    /**
     * @return array
     */
    public function fetchAllAsArray();
}