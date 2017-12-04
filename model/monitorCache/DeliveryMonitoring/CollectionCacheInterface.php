<?php

namespace oat\taoProctoring\model\monitorCache\DeliveryMonitoring;

use Doctrine\Common\Collections\ArrayCollection;

interface CollectionCacheInterface
{
    /**
     * @return ArrayCollection
     */
    public function fetchCollection();

    /**
     * @return bool
     */
    public function exists();

    /**
     * @param ArrayCollection $collection
     * @return bool
     */
    public function saveCollection($collection);

    /**
     * @return bool
     */
    public function reset();

    /**
     * @param DeliveryMonitoringEntity $entity
     * @return bool
     */
    public function addEntity(DeliveryMonitoringEntity $entity);

    /**
     * @param string $id
     * @return bool
     */
    public function entityExists($id);

    /**
     * @param DeliveryMonitoringEntity $entity
     */
    public function deleteEntity(DeliveryMonitoringEntity $entity);

    /**
     * @param DeliveryMonitoringEntity $entity
     * @return bool
     */
    public function updateEntity(DeliveryMonitoringEntity $entity);

    /**
     * @param $deliveryId
     * @return DeliveryMonitoringEntity|bool
     */
    public function findEntity($deliveryId);
}