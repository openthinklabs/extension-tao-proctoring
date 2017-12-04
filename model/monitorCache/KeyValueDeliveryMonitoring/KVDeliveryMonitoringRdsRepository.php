<?php

namespace oat\taoProctoring\model\monitorCache\KeyValueDeliveryMonitoring;

use common_persistence_SqlPersistence;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use oat\oatbox\service\ConfigurableService;
use oat\taoProctoring\model\monitorCache\implementation\MonitoringStorage;
use tao_helpers_Array;

class KVDeliveryMonitoringRdsRepository extends ConfigurableService implements KVDeliveryMonitoringRepository
{
    const OPTION_PERSISTENCE = 'persistence';

    const OPTION_CACHE_LAYER = 'cache_layer';

    /** @var array */
    private $filtersUsed;

    /**
     * @return common_persistence_SqlPersistence
     */
    public function getPersistence()
    {
        return $this->getServiceManager()
            ->get(\common_persistence_Manager::SERVICE_ID)
            ->getPersistenceById($this->getOption(static::OPTION_PERSISTENCE));
    }

    /**
     * @return CollectionsCacheInterface
     */
    public function getCacheLayer()
    {
        return $this->getOption(self::OPTION_CACHE_LAYER);
    }

    /**
     * @param KVDeliveryMonitoringCollection $collection
     */
    public function insertCollection(KVDeliveryMonitoringCollection $collection)
    {
        if ($collection->isEmpty()) {
            return;
        }

        $this->getPersistence()->insertMultiple(MonitoringStorage::KV_TABLE_NAME, $collection->toArray());
        $this->getCacheLayer()->addCollection($collection);
    }

    /**
     * @param KVDeliveryMonitoringCollection $collection
     * @throws \Exception
     */
    public function updateCollection(KVDeliveryMonitoringCollection $collection)
    {
        if ($collection->isEmpty()) {
            return;
        }
        list($keys, $data) = $collection->getInformationToStore();

        $this->getPersistence()->updateMultiple(
            MonitoringStorage::KV_TABLE_NAME,
            $data
        );

        $deliveryId = $collection->getDeliveryId();
        if ($collection = $this->getCacheLayer()->fetchCollection($deliveryId)) {
            $collection->markAsUpdatedTripletsByKeys($keys);
            $this->getCacheLayer()->updateCollection($collection);
        }
    }

    /**
     * @param array $filters
     * @param array $options
     * @return array
     */
    public function searchDeliveryKVCollections(array $filters = [], array $options = [])
    {
        if ($this->filtersUsed == $filters) {
            return $this->getCacheLayer()->fetchAllAsArray();
        }
        $this->getCacheLayer()->reset();
        $this->filtersUsed = $filters;

        $qb = $this->buildQuery($filters, $options);
        $result = $qb->execute()->fetchAll();
        $resultArray = [];

        foreach ($result as $data) {
            $resultArray[$data[MonitoringStorage::KV_COLUMN_PARENT_ID]][$data[MonitoringStorage::KV_COLUMN_KEY]] = $data[MonitoringStorage::KV_COLUMN_VALUE];
        }

        $collections = [];
        foreach ($resultArray as $deliveryId => $rawData) {
            $factoryData = [];

            foreach ($rawData as $key => $value) {
                $factoryData[$key] = $value;
            }

            $collection = KVDeliveryMonitoringCollection::buildCollection($deliveryId, $factoryData);
            $collections[$deliveryId] = $collection;
        }

        $this->getCacheLayer()->saveAll($collections);

        return $resultArray;
    }

    /**
     * @param string $deliveryId
     * @param array $availableKeys
     * @return KVDeliveryMonitoringCollection
     */
    public function findDeliveryKVCollection($deliveryId, $availableKeys)
    {
        if ($collection = $this->getCacheLayer()->fetchCollection($deliveryId)) {
            return $collection;
        }

        $queryBuilder = $this->getPersistence()->getPlatform()->getQueryBuilder();

        $qb = $queryBuilder->select(MonitoringStorage::KV_COLUMN_KEY . ', ' . MonitoringStorage::KV_COLUMN_VALUE)
            ->from(MonitoringStorage::KV_TABLE_NAME)
            ->where(MonitoringStorage::KV_COLUMN_PARENT_ID . '= :' . MonitoringStorage::KV_COLUMN_PARENT_ID)
            ->andWhere(MonitoringStorage::KV_COLUMN_KEY . ' IN(:keys)')
            ->setParameter(MonitoringStorage::KV_COLUMN_PARENT_ID, $deliveryId)
            ->setParameter('keys', $availableKeys, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);

        $rawData = $qb->execute()->fetchAll();
        $factoryData = [];

        foreach ($rawData as $datum) {
            $factoryData[$datum[MonitoringStorage::KV_COLUMN_KEY]] = $datum[MonitoringStorage::KV_COLUMN_VALUE];
        }

        $collection = KVDeliveryMonitoringCollection::buildCollection($deliveryId, $factoryData);

        $this->getCacheLayer()->addCollection($collection);

        return $collection;
    }

    /**
     * @param array $filters
     * @param array $options
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    protected function buildQuery(array $filters = [], array $options = [])
    {
        $queryBuilder = $this->getPersistence()->getPlatform()->getQueryBuilder();

        $qb = $queryBuilder->select(MonitoringStorage::KV_COLUMN_PARENT_ID. ', ' . MonitoringStorage::KV_COLUMN_KEY . ', ' . MonitoringStorage::KV_COLUMN_VALUE);
        $qb->from(MonitoringStorage::KV_TABLE_NAME);

        foreach($filters as $key => $filter) {
            if (!tao_helpers_Array::isAssoc($filter)) {
                $qb
                    ->where($key . ' IN (:'.$key.')')
                    ->setParameter(':'.$key, $filter, Connection::PARAM_STR_ARRAY)
                ;
            }else {
                foreach ($filter as $column => $value) {
                    $qb
                        ->where($column . ' = :'.$column)
                        ->setParameter(':'.$column, $value)
                    ;
                }
            }
        }

        return $qb;
    }
}