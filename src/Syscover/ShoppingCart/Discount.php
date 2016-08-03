<?php namespace Syscover\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;

class Discount implements Arrayable
{
    /**
     * Discount amount if discount is fixed
     *
     * @var float
     */
    public $fixed;

    /**
     * Set if price amount is with tax o without tax
     *
     * @var float
     */
    public $percentage;

    /**
     * Maximum discount amount if discount type is DISCOUNT_SUBTOTAL_FIXED_AMOUNT
     *
     * @var float
     */
    public $maximumDiscountPercentageAmount;

    /**
     * Set if apply discount to subtotal or total and shipping amount or only subtotal or total
     *
     * @var boolean
     */
    public $applyShippingAmount;

    /**
     * Discount constructor.
     * @param $fixed
     * @param $percentage
     * @param $maximumDiscountPercentageAmount
     * @param $applyShippingAmount
     */
    public function __construct($fixed, $percentage, $maximumDiscountPercentageAmount, $applyShippingAmount)
    {
        $this->fixed                            = $fixed;
        $this->percentage                       = $percentage;
        $this->maximumDiscountPercentageAmount  = $maximumDiscountPercentageAmount;
        $this->applyShippingAmount              = $applyShippingAmount;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'fixed'                             => $this->fixed,
            'percentage'                        => $this->percentage,
            'maximumDiscountPercentageAmount'   => $this->maximumDiscountPercentageAmount
        ];
    }
}