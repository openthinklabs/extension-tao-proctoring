<?php

namespace oat\taoProctoring\model\monitorCache\DeliveryMonitoring;

use common_persistence_SqlPersistence;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection;

use oat\oatbox\service\ConfigurableService;
use oat\taoProctoring\model\monitorCache\implementation\MonitoringStorage;
use tao_helpers_Array;

class DeliveryMonitoringRdsRepository extends ConfigurableService implements DeliveryMonitoringRepository
{
    const TABLE_NAME = MonitoringStorage::TABLE_NAME;

    const OPTION_PERSISTENCE = 'persistence';

    const OPTION_CACHE_LAYER = 'cache_layer';

    /** @var DeliveryMonitoringFactory */
    private $monitoringFactory;

    /** @var array */
    private $filtersUsed;

    /** @var array */
    private $optionsUsed;

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
     * @return CollectionCacheInterface
     */
    public function getCacheLayer()
    {
        return $this->getOption(self::OPTION_CACHE_LAYER);
    }

    /**
     * @param array $filters
     * @param array $options
     * @return array
     */
    public function search(array $filters = [], array $options = [])
    {
        if ($this->filtersUsed !== $filters
            || $this->optionsUsed !== $options) {
            $this->getCacheLayer()->reset();
        }

        /** @var DeliveryMonitoringCollection $collection */
        if ($collection = $this->getCacheLayer()->fetchCollection()) {
            return $collection->toArray();
        }

        $this->filtersUsed = $filters;
        $this->optionsUsed = $options;

        $qb = $this->buildQuery($filters, $options);
        $result = $qb->execute()->fetchAll();

        $collection = DeliveryMonitoringCollection::buildCollection($result, $this->monitoringFactory);
        $this->getCacheLayer()->saveCollection($collection);

        return $collection->toArray();
    }

    /**
     * @param string $deliveryId
     * @return DeliveryMonitoringEntity
     */
    public function find($deliveryId)
    {
        if ($entity = $this->getCacheLayer()->findEntity($deliveryId)) {
            return $entity;
        }

        $queryBuilder = $this->getPersistence()->getPlatform()->getQueryBuilder();
        $columns = $this->monitoringFactory->getColumns();

        $qb = $queryBuilder
            ->select(implode(', ', $columns))
            ->from(static::TABLE_NAME)
            ->where(MonitoringStorage::COLUMN_DELIVERY_EXECUTION_ID . ' = :' . MonitoringStorage::COLUMN_DELIVERY_EXECUTION_ID)
            ->setParameter(MonitoringStorage::COLUMN_DELIVERY_EXECUTION_ID, $deliveryId);

        $delivery = $qb->execute()->fetch();

        $deliveryEntity = $this->monitoringFactory->buildEntityFromRawArray($delivery);

        $this->getCacheLayer()->addEntity($deliveryEntity);
    }

    /**
     * @param DeliveryMonitoringEntity $entity
     * @return bool
     */
    public function update(DeliveryMonitoringEntity $entity)
    {
        $dataAttributes = $entity->getDataAttributes();
        $queryBuilder = $this->getPersistence()->getPlatform()->getQueryBuilder();
        $qb = $queryBuilder->update(static::TABLE_NAME);

        foreach ($this->monitoringFactory->getColumns() as $column) {
            $qb->set($column, ':' . $column);
            $qb->setParameter($column, $dataAttributes[$column]);
        }
        $qb->where(MonitoringStorage::COLUMN_DELIVERY_EXECUTION_ID . '= :' . MonitoringStorage::COLUMN_DELIVERY_EXECUTION_ID);
        $qb->setParameter(MonitoringStorage::COLUMN_DELIVERY_EXECUTION_ID, $entity->getId());
        $qb->execute();

        $this->getCacheLayer()->updateEntity($entity);
        return true;
    }

    /**
     * @param DeliveryMonitoringEntity $entity
     * @return bool
     */
    public function insert(DeliveryMonitoringEntity $entity)
    {
        $dataAttributes = $entity->getDataAttributes();

        $this->getPersistence()->insert(static::TABLE_NAME, $dataAttributes);
        $this->getCacheLayer()->addEntity($entity);

        return true;
    }

    /**
     * @param $deliveryId
     * @return bool
     */
    public function exists($deliveryId)
    {
        if ($this->getCacheLayer()->entityExists($deliveryId)) {
            return true;
        }

        $sql = 'SELECT 
                EXISTS(SELECT '. MonitoringStorage::COLUMN_DELIVERY_EXECUTION_ID.' 
                FROM '. self::TABLE_NAME .' 
                WHERE '. MonitoringStorage::COLUMN_DELIVERY_EXECUTION_ID . '=?)';

        $exists = $this->getPersistence()->query($sql, [$deliveryId])->fetch(\PDO::FETCH_COLUMN);

        return (bool)$exists;
    }

    /**
     * @param DeliveryMonitoringFactory $monitoringFactory
     */
    public function setMonitoringFactory(DeliveryMonitoringFactory $monitoringFactory)
    {
        $this->monitoringFactory = $monitoringFactory;
    }

    /**
     * @return DeliveryMonitoringFactory
     */
    public function getMonitoringFactory()
    {
        return  $this->monitoringFactory ;
    }

    /**
     * @param array $filters
     * @param array $options
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    protected function buildQuery(array $filters = [], array $options = [])
    {
        $queryBuilder = $this->getPersistence()->getPlatform()->getQueryBuilder();
        $columns = $this->monitoringFactory->getColumns();

        $qb = $queryBuilder
            ->select(implode(', ', $columns))
            ->from(static::TABLE_NAME);

        foreach($filters as $key => $filter) {
            if (!tao_helpers_Array::isAssoc($filter)) {
                $qb->where($key . ' IN (:'.$key.')')->setParameter(':'.$key, $filter, Connection::PARAM_STR_ARRAY);
            } else {
                foreach ($filter as $column => $value) {
                    if (!in_array($column, $columns)) {
                        continue;
                    }
                    $operator = '=';
                    if (preg_match('/^(?:\s*(<>|<=|>=|<|>|=|LIKE|ILIKE|NOT\sLIKE|NOT\sILIKE))?(.*)$/', $value, $matches)) {
                        $value = $matches[2];
                        $operator = $matches[1] ? $matches[1] : "=";
                    }
                    $qb->where($column . $operator .':'.$column)->setParameter(':'.$column, $value);
                }
            }
        }

        if (isset($options['limit'])) {
            $qb->setMaxResults(intval($options['limit']));
        }

        if (isset($options['offset'])) {
            $qb->setFirstResult(intval($options['offset']));
        }

        if (isset($options['group']) && in_array($options['group'], $columns)) {
            $qb->groupBy($options['group']);
        }

        if (isset($options['order'])) {
            $parts = explode(' ', $options['order']);
            if (in_array($parts[0], $columns)) {
                $qb->addOrderBy($parts[0], $parts[1]);
            }
        }

        return $qb;
    }
}