<?php


namespace oat\taoProctoring\model\monitorCache\DeliveryMonitoring;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Collections\Criteria;
use oat\oatbox\PhpSerializable;
use oat\oatbox\PhpSerializeStateless;

class DeliveryMonitoringCollectionInMemoryCache extends ArrayCache implements PhpSerializable, CollectionCacheInterface
{
    use PhpSerializeStateless;

    const CACHE_KEY = 'delivery_monitoring_collection';

    /**
     * @return DeliveryMonitoringCollection
     */
    public function fetchCollection()
    {
        return $this->fetch(static::CACHE_KEY);
    }

    /**
     * @return bool
     */
    public function exists()
    {
        return $this->contains(static::CACHE_KEY);
    }

    /**
     * @param DeliveryMonitoringCollection $collection
     * @return bool
     */
    public function saveCollection($collection)
    {
        $this->save(static::CACHE_KEY, $collection);

        return true;
    }

    /**
     * @return bool
     */
    public function reset()
    {
        $this->delete(static::CACHE_KEY);

        return true;
    }

    /**
     * @param DeliveryMonitoringEntity $entity
     * @return bool
     */
    public function addEntity(DeliveryMonitoringEntity $entity)
    {
        if (!$this->exists()) {
            $collection = new DeliveryMonitoringCollection();
        } else {
            $collection = $this->fetchCollection();
        }

        $collection->add($entity);
        $this->save(static::CACHE_KEY, $collection);

        return true;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function entityExists($id)
    {
        if ($this->exists()) {
            $collection = $this->fetchCollection();
            $criteria = Criteria::create()->where(Criteria::expr()->eq('id', $id));
            if ($collection->matching($criteria)->first()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param DeliveryMonitoringEntity $entity
     */
    public function deleteEntity(DeliveryMonitoringEntity $entity)
    {
        $collection = $this->fetchCollection();
        foreach ($collection as $key => $item) {
            if ($item->getId() === $entity->getId()) {
                $collection->remove($key);
            }
        }

        $this->save(static::CACHE_KEY, $collection);
    }

    /**
     * @param DeliveryMonitoringEntity $entity
     * @return bool
     */
    public function updateEntity(DeliveryMonitoringEntity $entity)
    {
        $this->deleteEntity($entity);
        $this->addEntity($entity);

        return true;
    }

    /**
     * @param $deliveryId
     * @return DeliveryMonitoringEntity|bool
     */
    public function findEntity($deliveryId)
    {
        if (!$this->exists()) {
            return false;
        }
        $collection = $this->fetchCollection();
        $criteria = Criteria::create()->where(Criteria::expr()->eq('id', $deliveryId));
        if ($found = $collection->matching($criteria)->first()) {
            return $found;
        }
    }
}