<?php namespace Syscover\Shoppingcart;

use Illuminate\Support\Collection;

class CartItemTaxRule extends Collection
{
    /**
     * id tax rule
     *
     * @var string
     */
    public $id;

    /**
     * The tax rule name, this name has to be translated.
     *
     * @var string
     */
    public $name;

    /**
     * Priority to calculate tax on subtotal.
     *
     * @var string
     */
    public $priority;

    /**
     * Sort to appear on order.
     *
     * @var string
     */
    public $sortOrder;

    /**
     * Array that contain float value of tax rates to apply on price.
     *
     * @var \float
     */
    public $taxRate;

    /**
     * Amount of this taxRule.
     *
     * @var \float
     */
    public $taxAmount;

    /**
     * CartItemTaxRule constructor.
     *
     * @param string $name
     * @param int $priority
     * @param int $sortOrder
     * @param float $taxRate
     */
    public function __construct($name, $priority, $sortOrder, $taxRate)
    {
        if (empty($name))
            throw new \InvalidArgumentException('Please supply a valid name.');

        if (strlen($priority) < 0 || !is_numeric($priority))
            throw new \InvalidArgumentException('Please supply a valid priority.');

        if (strlen($sortOrder) < 0 || !is_numeric($sortOrder))
            throw new \InvalidArgumentException('Please supply a valid sortOrder.');

        if (strlen($sortOrder) < 0 || !is_numeric($sortOrder))
            throw new \InvalidArgumentException('Please supply a valid taxRate.');


        $this->id           = $this->generateId($name, $priority);
        $this->name         = $name;
        $this->priority     = $priority;
        $this->sortOrder    = $sortOrder;
        $this->taxRate      = $taxRate;
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param string $attribute
     * @return mixed
     */
    public function __get($attribute)
    {
        if(property_exists($this, $attribute)) {
            return $this->{$attribute};
        }
        return null;
    }

    /**
     * Returns the formatted sum tax rate.
     *
     * @param int       $decimals
     * @param string    $decimalPoint
     * @param string    $thousandSeperator
     * @return string
     */
    public function getTaxRate($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->taxRate, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted sum tax rate.
     *
     * @param int       $decimals
     * @param string    $decimalPoint
     * @param string    $thousandSeperator
     * @return string
     */
    public function getTaxAmount($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->taxAmount, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param string $name
     * @return string
     */
    protected function generateId($name, $priority)
    {
        return md5($name . $priority);
    }
}