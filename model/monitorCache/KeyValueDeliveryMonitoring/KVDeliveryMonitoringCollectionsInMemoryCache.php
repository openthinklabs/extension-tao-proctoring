<?php


namespace oat\taoProctoring\model\monitorCache\KeyValueDeliveryMonitoring;

use Doctrine\Common\Cache\ArrayCache;
use oat\oatbox\PhpSerializable;
use oat\oatbox\PhpSerializeStateless;

class KVDeliveryMonitoringCollectionsInMemoryCache extends ArrayCache implements PhpSerializable, CollectionsCacheInterface
{
    use PhpSerializeStateless;

    const CACHE_KEY = 'kv_delivery_monitoring';

    /**
     * @param array $collections
     */
    public function saveAll(array $collections)
    {
        if (empty($collections)) {
            $this->save(static::CACHE_KEY, $collections);
        }
    }

    /**
     * @return bool|void
     */
    public function reset()
    {
        $this->delete(static::CACHE_KEY);
    }

    /**
     * @param $deliveryId
     * @return bool|KVDeliveryMonitoringCollection
     */
    public function fetchCollection($deliveryId)
    {
        if (!$this->contains($deliveryId)) {
            return false;
        }
        $collections = $this->fetch(static::CACHE_KEY);

        /** @var KVDeliveryMonitoringCollection $collection */
        foreach ($collections as $collection) {
           if ($collection->getDeliveryId() === $deliveryId) {
               return $collection;
           }
        }

        return false;
    }

    /**
     * @param string $deliveryId
     */
    public function deleteCollection($deliveryId)
    {
        $collections = $this->fetch(static::CACHE_KEY);

        /** @var KVDeliveryMonitoringCollection $collection */
        foreach ($collections as $key => $collection) {
            if ($collection->getDeliveryId() === $deliveryId) {
                unset($collections[$key]);
            }
        }

        $this->save(static::CACHE_KEY, $collections);
    }

    /**
     * @param KVDeliveryMonitoringCollection $collection
     */
    public function updateCollection(KVDeliveryMonitoringCollection $collection)
    {
        $this->deleteCollection($collection->getDeliveryId());
        $this->addCollection($collection);
    }

    /**
     * @param KVDeliveryMonitoringCollection $collection
     */
    public function addCollection(KVDeliveryMonitoringCollection $collection)
    {
        if ($collections = $this->fetch(static::CACHE_KEY)) {
            array_push($collections, $collection);
            $this->save(static::CACHE_KEY, $collections);
        } else {
            $this->save(static::CACHE_KEY, [$collection]);
        }
    }

    /**
     * @return array
     */
    public function fetchAllAsArray()
    {
        $collections = $this->fetch(static::CACHE_KEY);

        $resultArray = [] ;
        /** @var KVDeliveryMonitoringCollection $collection */
        foreach ($collections as $collection) {
            /** @var KVDeliveryMonitoring $item */
            foreach ($collection as $item) {
                $resultArray[$collection->getDeliveryId()][$item->getKey()] = $item->getValue();
            }
        }

        return $resultArray;
    }

}