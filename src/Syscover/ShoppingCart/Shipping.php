<?php namespace Syscover\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;

class Shipping implements Arrayable
{
    /**
     * Shipping amount
     *
     * @var float
     */
    public $amount;

    /**
     * Set if price amount is with tax o without tax
     *
     * @var int
     */
    public $priceType;

    /**
     * Set percentage of tax
     *
     * @var int
     */
    public $taxRate;

    /**
     * Set table to calculate price
     *
     * @var int
     */
    public $tableRates;

    /**
     * Check if cart has free shipping
     *
     * @var boolean
     */
    public $freeShipping;

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'amount'            => $this->amount,
            'priceType'         => $this->priceType,
            'taxRate'           => $this->taxRate,
            'tableRate'         => $this->tableRates,
            'freeShipping'      => $this->freeShipping
        ];
    }
}