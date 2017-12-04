<?php

namespace oat\taoProctoring\model\monitorCache\KeyValueDeliveryMonitoring;

class KVDeliveryMonitoring
{
    /** @var string */
    private $key;

    /** @var string */
    private $value;

    /** @var bool */
    private $saved = false;

    /**
     * @param string $deliveryId
     * @param string $key
     * @param string $value
     */
    public function __construct( $key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param KVDeliveryMonitoring $kvDM
     * @return bool
     */
    public function hasSameKey(KVDeliveryMonitoring $kvDM)
    {
        return $this->key === $kvDM->getKey();
    }

    /**
     * @param KVDeliveryMonitoring $kvDM
     * @return bool
     */
    public function equals(KVDeliveryMonitoring $kvDM)
    {
        return $this->key === $kvDM->getKey()
                && $this->value === $kvDM->getValue();
    }

    /**
     * @return bool
     */
    public function isSaved()
    {
        return $this->saved;
    }

    /**
     * @param bool $saved
     */
    public function setSaved($saved)
    {
        $this->saved = $saved;
    }
}