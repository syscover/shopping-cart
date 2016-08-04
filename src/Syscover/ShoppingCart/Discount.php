<?php namespace Syscover\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;

class Discount implements Arrayable
{
    /**
     * Id from Price rule where belong this discount
     *
     * @var float
     */
    public $id;

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
     * Maximum discount amount if discount type is by percentage
     *
     * @var float
     */
    public $maximumPercentageAmount;

    /**
     * Set if apply discount to subtotal or total and shipping amount or only subtotal or total
     *
     * @var boolean
     */
    public $applyShippingAmount;

    /**
     * Discount amount calculated
     *
     * @var float
     */
    public $amount;


    /**
     * Discount constructor.
     * @param $id
     * @param $fixed
     * @param $percentage
     * @param $maximumPercentageAmount
     * @param $applyShippingAmount
     */
    public function __construct($id, $fixed, $percentage, $maximumPercentageAmount, $applyShippingAmount)
    {
        $this->id                               = $id;
        $this->fixed                            = $fixed;
        $this->percentage                       = $percentage;
        $this->maximumPercentageAmount          = $maximumPercentageAmount;
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