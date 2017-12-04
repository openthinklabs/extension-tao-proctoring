<?php


namespace oat\taoProctoring\model\monitorCache\DeliveryMonitoring;

use Doctrine\Common\Collections\ArrayCollection;

class DeliveryMonitoringCollection extends ArrayCollection
{
    /**
     * @param array $rawData
     * @param DeliveryMonitoringFactory $factory
     * @return DeliveryMonitoringCollection
     */
    public static function buildCollection(array $rawData, DeliveryMonitoringFactory $factory)
    {
        $collection = new static();

        foreach ($rawData as $value) {
            $collection->add($factory->buildEntityFromRawArray($value));
        }

        return $collection;
    }

    /**
     * @param DeliveryMonitoringEntity $entity
     */
    public function updateEntity(DeliveryMonitoringEntity $entity)
    {
        /** @var DeliveryMonitoringEntity $item */
        foreach ($this as $index => $item) {
            if ($entity->getId() === $item->getId()) {
                $this->offsetSet($index, $entity);
                break;
            }
        }
    }
    /**
     * @return array
     */
    public function toArray()
    {
        $array = [];
        /** @var DeliveryMonitoringEntity $item */
        foreach ($this->getIterator() as $item) {
            $array[] = $item->toArray();
        }

        return $array;
    }
}