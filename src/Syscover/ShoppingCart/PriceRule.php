<?php namespace Syscover\ShoppingCart;

class PriceRule
{
    const WITHOUT_DISCOUNT                  = 1;
    const DISCOUNT_PERCENTAGE_SUBTOTAL      = 2;
    const DISCOUNT_FIXED_AMOUNT_SUBTOTAL    = 3;
    const DISCOUNT_PERCENTAGE_TOTAL         = 4;
    const DISCOUNT_FIXED_AMOUNT_TOTAL       = 5;

    /**
     * id price rule
     *
     * @var string
     */
    public $id;

    /**
     * The price rule name, this name has to be translated.
     *
     * @var string
     */
    public $name;

    /**
     * The price rule name, this name has to be translated.
     *
     * @var string
     */
    public $description;

    /**
     * Discount type of price rule, this value can to be CONST defined
     *
     * @var int
     */
    public $discountType;

    /**
     * Check if this price rule is combinable with other price rules
     *
     * @var boolean
     */
    public $combinable;

    /**
     * Percentage of discount if discount type is DISCOUNT_PERCENTAGE_SUBTOTAL
     *
     * @var float
     */
    public $discountPercentage;

    /**
     * Discount amount if discount type is DISCOUNT_FIXED_AMOUNT_SUBTOTAL
     *
     * @var float
     */
    public $discountFixed;

    /**
     * Maximum discount amount if discount type is DISCOUNT_FIXED_AMOUNT_SUBTOTAL
     *
     * @var float
     */
    public $maximumDiscountAmount;

    /**
     * Set if apply discount to subtotal and shipping amount or only subtotal
     *
     * @var boolean
     */
    public $applyShippingAmount;

    /**
     * Check if this price rule has free shipping
     *
     * @var boolean
     */
    public $freeShipping;

    /**
     * Discount amount calculated
     *
     * @var float
     */
    public $discountAmount;


    /**
     * PriceRule constructor.
     * @param string    $name
     * @param string    $description
     * @param int       $discountType
     * @param bool      $combinable
     * @param float     $discountPercentage
     * @param float     $discountFixed
     * @param float     $maximumDiscountAmount
     * @param bool      $applyShippingAmount
     * @param bool      $freeShipping
     */
    public function __construct($name, $description, $discountType, $combinable = true, $discountPercentage = null, $discountFixed = null, $maximumDiscountAmount = null, $applyShippingAmount = false, $freeShipping  = false)
    {
        $this->name                     = $name;
        $this->description              = $description;
        $this->discountType             = $discountType;
        $this->combinable               = $combinable;
        $this->discountPercentage       = $discountPercentage;
        $this->discountFixed            = $discountFixed;
        $this->maximumDiscountAmount    = $maximumDiscountAmount;
        $this->applyShippingAmount      = $applyShippingAmount;
        $this->freeShipping             = $freeShipping;
        $this->id                       = $this->generateId();
    }

    /**
     * Generate a unique id for the new cartPriceRule
     *
     * @return string
     */
    protected function generateId()
    {
        return md5($this->name . $this->description . $this->discountType. $this->combinable);
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param   string  $attribute
     * @return  mixed
     */
    public function __get($attribute)
    {
        if(property_exists($this, $attribute)) {
            return $this->{$attribute};
        }
        return null;
    }

    /**
     * Returns the formatted discount amount.
     *
     * @param int       $decimals
     * @param string    $decimalPoint
     * @param string    $thousandSeperator
     * @return string
     */
    public function getDiscountAmount($decimals = 0, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->discountAmount, $decimals, $decimalPoint, $thousandSeperator);
    }
}