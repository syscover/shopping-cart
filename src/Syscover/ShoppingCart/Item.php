<?php namespace Syscover\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;

class Item implements Arrayable
{
    /**
     * The rowID of the cart item.
     *
     * @var string
     */
    public $rowId;

    /**
     * The ID of the cart item.
     *
     * @var int|string
     */
    public $id;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public $name;

    /**
     * The price without TAX of the cart item.
     *
     * @var float
     */
    public $price;

    /**
     * set if product is transportable.
     *
     * @var boolean
     */
    public $transportable;

    /**
     * Weight or unit to calculate ahippiing amount.
     *
     * @var int|float
     */
    public $weight;

    /**
     * The tax rules for the cart item.
     *
     * @var \Syscover\ShoppingCart\CartItemTaxRules;
     */
    public $taxRules;

    /**
     * The options for this cart item.
     *
     * @var array
     */
    public $options;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    private $quantity;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $subtotal;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $taxAmount;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $total;

    /**
     * Discount type of price rule
     *
     * @var int
     */
    public $discountType;

    /**
     * The discount percentage over this item.
     *
     * @var int|float
     */
    private $discountPercentage = 0;

    /**
     * The discount amount over this item.
     *
     * @var int|float
     */
    private $discountAmount = 0;

    /**
     * CartItem constructor.
     *
     * @param int|string                            $id
     * @param string                                $name
     * @param float                                 $quantity
     * @param float                                 $price
     * @param boolean                               $transportable
     * @param float                                 $weight
     * @param array|\Syscover\ShoppingCart\TaxRule  $taxRule
     * @param array                                 $options
     */

    public function __construct($id, $name, $quantity, $price, $weight = 1.000, $transportable = true, $taxRule = [], array $options = [])
    {
        if(empty($id))
            throw new \InvalidArgumentException('Please supply a valid identifier.');

        if(empty($name))
            throw new \InvalidArgumentException('Please supply a valid name.');

        if(strlen($price) < 0 || ! is_numeric($price))
            throw new \InvalidArgumentException('Please supply a valid price.');

        if(! is_bool($transportable))
            throw new \InvalidArgumentException('Please supply a valid transportable.');

        if(strlen($weight) < 0 || ! is_numeric($weight))
            throw new \InvalidArgumentException('Please supply a valid weight.');

        $this->id               = $id;
        $this->name             = $name;
        $this->price            = floatval($price);
        $this->transportable    = $transportable;
        $this->weight           = floatval($weight);
        $this->options          = new CartItemOptions($options);
        $this->taxRules         = new CartItemTaxRules();
        $this->rowId            = $this->generateRowId($id, $options);

        // add tax rule to taxRules property
        $this->addTaxRule($taxRule);

        // When set quantity, calculate all amounts, for this reason this is last function
        // called in constructor
        $this->setQuantity($quantity);
    }

    /**
     * Returns the formatted unit price.
     *
     * @param int       $decimals
     * @param string    $decimalPoint
     * @param string    $thousandSeperator
     * @return string
     */
    public function getPrice($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->price, $decimals, $decimalPoint, $thousandSeperator);
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
        return number_format($this->taxRules->sum('taxRate'), $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted tax amount.
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
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  string
     */
    public function getSubtotal($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->subtotal, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns the formatted total.
     * Total is price for whole CartItem with TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  string
     */
    public function getTotal($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->total, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param string $id
     * @param array  $options
     * @return string
     */
    protected function generateRowId($id, array $options)
    {
        ksort($options);
        return md5($id . serialize($options));
    }

    /**
     * Get the quantity for this cart item.
     *
     * @return float
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int|float $quantity
     */
    public function setQuantity($quantity)
    {
        if($quantity !== 0 && (empty($quantity) || ! is_numeric($quantity)))
            throw new \InvalidArgumentException('Please supply a valid quantity.');

        $this->quantity = $quantity;
        $this->calculateAmounts();
    }

    /**
     * get the discount percentage over this cart item.
     *
     * @return float
     */
    public function getDiscountPercentage()
    {
        return $this->discountPercentage;
    }

    /**
     * Set the discount percentage over this cart item.
     *
     * @param int|float $discountPercentage
     */
    public function setDiscountPercentage($discountPercentage)
    {
        if($discountPercentage !== 0 && (empty($discountPercentage) || ! is_numeric($discountPercentage)))
            throw new \InvalidArgumentException('Please supply a valid discount percentaje.');

        $this->discountPercentage = $discountPercentage;
        $this->calculateAmounts();
    }

    /**
     *  calculate all amounts, this function is called, when change any property from cartItem
     */
    private function calculateAmounts()
    {
        // subtotal calculate
        if(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX || $this->taxRules === null || $this->taxRules->count() == 0)
        {
            $this->subtotal = $this->quantity * $this->price;

            // calculate discount amount if has discount percentage
            if($this->discountPercentage > 0)
                $this->discountAmount = ($this->subtotal * $this->discountPercentage) / 100;

            if($this->taxRules === null || $this->taxRules->count() == 0)
            {
                $this->taxAmount = 0;
            }
            else
            {
                // calculate amounts of each taxRule
                $taxRules           = $this->taxRules->sortBy('priority');
                $lastPriority       = 0;
                $baseToCalculate    = $this->subtotal - $this->discountAmount;
                foreach ($taxRules as $taxRule)
                {
                    if($lastPriority == $taxRule->priority)
                    {
                        $taxRule->taxAmount = $baseToCalculate * ($taxRule->taxRate / 100);
                    }
                    else
                    {
                        $lastPriority = $taxRule->priority;

                        // if is a different priority, calculate tax over subtotal plus previous tax amounts
                        $baseToCalculate    += $taxRules->sum('taxAmount');
                        $taxRule->taxAmount = $baseToCalculate * ($taxRule->taxRate / 100);
                    }
                }

                // set total tax from this car item
                $this->taxAmount = $this->taxRules->sum('taxAmount');
            }

            $this->total = ($this->subtotal - $this->discountAmount) + $this->taxAmount;
        }
        elseif(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            $taxRules       = $this->taxRules->sortByDesc('priority');
            $lastPriority   = null;
            $this->total    = $this->quantity * $this->price;
            $totalAux       = $this->total;
            $taxAmountAux   = 0;

            foreach ($taxRules as $taxRule)
            {
                if($lastPriority === null || $lastPriority != $taxRule->priority)
                {
                    $lastPriority = $taxRule->priority;
                    $totalAux     -= $taxAmountAux;
                }

                // get total taxRate from taxRules with the same priority
                $taxRateAux         = $taxRules->where('priority', $taxRule->priority)->sum('taxRate');
                $taxAmountAux       = ($taxRateAux * $totalAux) / ($taxRateAux + 100);
                $taxRule->taxAmount = ($taxAmountAux * $taxRule->taxRate) / $taxRateAux;
            }

            $this->taxAmount    = $taxRules->sum('taxAmount');
            $this->subtotal     = $this->total - $this->taxAmount;
        }
    }

    /**
     * Add TaxRule to cartItemTaxRules object
     *
     * @param   array|\Syscover\ShoppingCart\TaxRule        $taxRule
     * @return  \Syscover\ShoppingCart\CartItemTaxRules
     */
    private function addTaxRule($taxRule)
    {
        if(is_array($taxRule))
        {
            return array_map(function ($item) {
                return $this->addTaxRule($item);
            }, $taxRule);
        }

        // sum ratesy if exist a tax rule with de same id
        if($this->taxRules->has($taxRule->id))
            $taxRule->taxRate = $taxRule->taxRate + $this->taxRules->get($taxRule->id)->taxRate;

        $this->taxRules->put($taxRule->id, $taxRule);
    }















    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'rowId'         => $this->rowId,
            'id'            => $this->id,
            'name'          => $this->name,
            'quantity'      => $this->quantity,
            'price'         => $this->price,
            'transportable' => $this->transportable,
            'weight'        => $this->weight,
            'options'       => $this->options,
        ];
    }
}