<?php


namespace oat\taoProctoring\helpers;

class Order
{
    private $column;

    private $order;

    /**
     * Order constructor.
     * @param $column
     * @param $order
     */
    public function __construct($column, $order)
    {
        $this->column = $column;
        $this->order = $order;
    }

    /**
     * @return mixed
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }
}