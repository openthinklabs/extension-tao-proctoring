<?php

namespace oat\taoProctoring\model\monitorCache\KeyValueDeliveryMonitoring;

use Doctrine\Common\Collections\ArrayCollection;
use oat\taoProctoring\model\monitorCache\implementation\MonitoringStorage;

class KVDeliveryMonitoringCollection extends ArrayCollection
{
    /** @var $deliveryId */
    private $deliveryId;

    /**
     * KVDeliveryMonitoringCollection constructor.
     * @param string $deliveryId
     * @param array $elements
     */
    public function __construct($deliveryId, array $elements = array())
    {
        $this->deliveryId = $deliveryId;
        parent::__construct($elements);
    }

    /**
     * @param string $deliveryId
     * @param array $rawData
     * @return KVDeliveryMonitoringCollection
     */
    public static function buildCollection($deliveryId, array $rawData)
    {
        $collection = new static($deliveryId);

        foreach ($rawData as $key => $value) {
            $collection->add(new KVDeliveryMonitoring($key, $value));
        }

        return $collection;
    }

    /**
     * @return mixed
     */
    public function getDeliveryId()
    {
        return $this->deliveryId;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = [];
        /** @var KVDeliveryMonitoring $item */
        foreach ($this->getIterator() as $item) {
            $array[] = [
                MonitoringStorage::KV_COLUMN_PARENT_ID => $this->getDeliveryId(),
                MonitoringStorage::KV_COLUMN_KEY => $item->getKey(),
                MonitoringStorage::KV_COLUMN_VALUE => $item->getValue(),
            ];
        }

        return $array;
    }

    /**
     * @return array
     */
    public function getInformationToStore()
    {
        $array = [];
        $keysUpdated = [];
        /** @var KVDeliveryMonitoring $item */
        foreach ($this->getIterator() as $item) {
            $keysUpdated[] = $item->getKey();
            $array[] = [
                'conditions' => [
                    MonitoringStorage::KV_COLUMN_PARENT_ID => $this->getDeliveryId(),
                    MonitoringStorage::KV_COLUMN_KEY => $item->getKey(),
                ],
                'updateValues' => [
                    MonitoringStorage::KV_COLUMN_VALUE => $item->getValue()
                ]
            ];
        }

        return [$keysUpdated, $array];
    }

    /**
     * @param KVDeliveryMonitoringCollection $existedCollection
     * @return KVDeliveryMonitoringCollection
     */
    public function diffToGetNewTriplets(KVDeliveryMonitoringCollection $existedCollection)
    {
        if ($existedCollection->isEmpty()) {
            return new static($this->deliveryId, $this->getIterator()->getArrayCopy());
        }

        $copyArray = $this->getIterator()->getArrayCopy();
        $copyArrayToCompare = $existedCollection->getIterator()->getArrayCopy();

        /** @var KVDeliveryMonitoring $item */
        foreach ($copyArray as $key => $item){

            /** @var KVDeliveryMonitoring $compareItem */
            foreach ($copyArrayToCompare as $key2 => $compareItem) {
                if ($item->hasSameKey($compareItem)) {
                    unset($copyArray[$key]);
                    unset($copyArrayToCompare[$key2]);
                    continue 2;
                }
            }
        }

        return new static($this->deliveryId, $copyArray);
    }

    /**
     * @param KVDeliveryMonitoringCollection $existedCollection
     *
     * @return KVDeliveryMonitoringCollection
     */
    public function diffToGetUpdatedTriplets(KVDeliveryMonitoringCollection $existedCollection)
    {
        if ($existedCollection->isEmpty()) {
            return new static($this->deliveryId, $this->getIterator()->getArrayCopy());
        }

        $copyArray = $this->getIterator()->getArrayCopy();
        $copyArrayToCompare = $existedCollection->getIterator()->getArrayCopy();

        /** @var KVDeliveryMonitoring $item */
        foreach ($copyArray as $key => $item){
            /** @var KVDeliveryMonitoring $compareItem */
            foreach ($copyArrayToCompare as $key2 => $compareItem) {
                if ($item->equals($compareItem) || $compareItem->isSaved()) {
                    unset($copyArray[$key]);
                    unset($copyArrayToCompare[$key2]);
                    break;
                }

                if ($item->hasSameKey($compareItem)) {
                    unset($copyArrayToCompare[$key2]);
                    continue 2;
                }
            }
        }

        return new static($this->deliveryId, $copyArray);
    }

    /**
     * @param array $keys
     */
    public function markAsUpdatedTripletsByKeys(array $keys)
    {
        /** @var  KVDeliveryMonitoring $item */
        foreach ($this as $index => $item){
            if (in_array($item->getKey(), $keys)) {
                $item->setSaved(true);
                $this->offsetSet($index, $item);
                break;
            }
        }
    }
}