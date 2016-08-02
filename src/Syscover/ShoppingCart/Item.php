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
    public $total;

    /**
     * The quantity of total before discount, necessary for calculate from various calls.
     *
     * @var int|float
     */
    public $totalBeforeDiscount;

    /**
     * Discount type of price rule
     *
     * @var int
     */
    public $discountType;

    /**
     * Discount percentage over subtotal from this item.
     *
     * @var int|float
     */
    private $discountSubtotalPercentage = 0;

    /**
     * Discount percentage over total from this item.
     *
     * @var int|float
     */
    private $discountTotalPercentage = 0;

    /**
     * The discount amount over subtotal from this item.
     *
     * @var int|float
     */
    private $discountSubtotalAmount = 0;

    /**
     * The discount amount over total from this item.
     *
     * @var int|float
     */
    private $discountTotalAmount = 0;

    /**
     * Discount amount if discount type is DISCOUNT_SUBTOTAL_FIXED_AMOUNT
     *
     * @var float
     */
    public $discountFixed = 0;


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

        // sum rates if exist a tax rule with de same id
        if($this->taxRules->has($taxRule->id))
            $taxRule->taxRate = $taxRule->taxRate + $this->taxRules->get($taxRule->id)->taxRate;

        $this->taxRules->put($taxRule->id, $taxRule);
    }

    /**
     * magic method to make accessing the total, tax and subtotal properties
     *
     * @param   string      $attribute
     * @return  float|null
     */
    public function __get($attribute)
    {
        if($attribute === 'discountSubtotalPercentage')
        {
            return $this->discountSubtotalPercentage;
        }

        if($attribute === 'discountTotalPercentage')
        {
            return $this->discountTotalPercentage;
        }

        if($attribute === 'discountSubtotalAmount')
        {
            return $this->discountSubtotalAmount;
        }

        if($attribute === 'discountTotalAmount')
        {
            return $this->discountTotalAmount;
        }

        if($attribute === 'discountAmount')
        {
            return $this->discountSubtotalAmount + $this->discountTotalAmount;
        }

        if($attribute === 'taxAmount')
        {
            return $this->taxRules->sum('taxAmount');
        }
        return null;
    }

    /**
     * Returns the formatted unit price.
     *
     * @param   int       $decimals
     * @param   string    $decimalPoint
     * @param   string    $thousandSeperator
     * @return  string
     */
    public function getPrice($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->price, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Returns array with all tax rates apply over item formated
     *
     * @param   int       $decimals
     * @param   string    $decimalPoint
     * @param   string    $thousandSeperator
     * @return  array
     */
    public function getTaxRates($decimals = 0, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return array_map(function($taxRate) use ($decimals, $decimalPoint, $thousandSeperator) {
            return number_format($taxRate, $decimals, $decimalPoint, $thousandSeperator);
        },  $this->taxRules->pluck('taxRate')->toArray());
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
     * Returns the formatted tax amount.
     *
     * @param   int       $decimals
     * @param   string    $decimalPoint
     * @param   string    $thousandSeperator
     * @return  string
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
    public function getDiscountAmount($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->discountAmount, $decimals, $decimalPoint, $thousandSeperator);
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
     * @param   string  $id
     * @param   array   $options
     * @return  string
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
     * @param   int|float $quantity
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setQuantity($quantity)
    {
        if($quantity !== 0 && (empty($quantity) || ! is_numeric($quantity)))
            throw new \InvalidArgumentException('Please supply a valid quantity.');

        $this->quantity = $quantity;

        $this->calculateAmounts();

        return $this;
    }

    /**
     * Get format discountSubtotalPercentage over this cart item.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  string
     */
    public function getDiscountSubtotalPercentage($decimals = 0, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->discountSubtotalPercentage, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get format discountTotalPercentage over this cart item.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  string
     */
    public function getDiscountTotalPercentage($decimals = 0, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->discountTotalPercentage, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Set subtotal discount percentage over this cart item.
     *
     * @param   int|float   $discountSubtotalPercentage
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setDiscountSubtotalPercentage($discountSubtotalPercentage)
    {
        if($discountSubtotalPercentage !== 0 && (empty($discountSubtotalPercentage) || ! is_numeric($discountSubtotalPercentage)))
            throw new \InvalidArgumentException('Please supply a valid discount percentage.');

        if($this->discountTotalPercentage > 0)
            throw new \InvalidArgumentException('You can\'t apply discount over subtotal, when you already have discounts over total.');

        // set discount subtotal percentage
        $this->discountSubtotalPercentage = (float) $discountSubtotalPercentage;

        $this->calculateAmounts();

        return $this;
    }

    /**
     * Set total discount percentage over this cart item.
     *
     * @param   int|float   $discountTotalPercentage
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setDiscountTotalPercentage($discountTotalPercentage)
    {
        if($discountTotalPercentage !== 0 && (empty($discountTotalPercentage) || ! is_numeric($discountTotalPercentage)))
            throw new \InvalidArgumentException('Please supply a valid discount percentage.');

        if($this->discountSubtotalPercentage > 0)
            throw new \InvalidArgumentException('You can\'t apply discount over total, when you already have discounts over subtotal.');

        // set discount total percentage
        $this->discountTotalPercentage = (float) $discountTotalPercentage;

        $this->calculateAmounts();

        return $this;
    }


    /**
     * Calculate all amounts, this function is called, when change any property from cartItem
     *
     * @return void
     */
    private function calculateAmounts()
    {
        // subtotal calculate
        if(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX || $this->taxRules === null || $this->taxRules->count() == 0)
        {
            // calculate subtotal
            $this->subtotal = $this->quantity * $this->price;

            // calculate discount subtotal amount
            if($this->discountSubtotalPercentage > 0 )
            {
                $this->discountSubtotalAmount = ($this->subtotal * $this->discountSubtotalPercentage) / 100;
            }

            // calculate all amounts for price without tax
            $this->total = $this->calculateTotalAndTaxOverSubtotal($this->subtotal - $this->discountSubtotalAmount);

            // calculate discount total amount
            if($this->discountTotalPercentage > 0)
            {
                $this->discountTotalAmount  = ($this->total * $this->discountTotalPercentage) / 100;

                $this->subtotal = $this->calculateSubtotalAndTaxOverTotal($this->total - $this->discountTotalAmount);
            }

        }

        elseif(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            // calculate total
            $this->total = $this->quantity * $this->price;

            if($this->discountTotalPercentage > 0)
            {
                $this->discountTotalAmount  = ($this->total * $this->discountTotalPercentage) / 100;

                // calculate again total amount less discount with primary data, (quantity and price)
                $this->total = ($this->quantity * $this->price) - $this->discountTotalAmount;
            }

            // calculate all amounts for price with tax
            $this->subtotal = $this->calculateSubtotalAndTaxOverTotal($this->total);

            // to get subtotal without discount, subtotal is a amount without any discount
            $this->subtotal = (($this->subtotal * $this->quantity) * 100) / $this->total;


            // calculate discount subtotal amount
            if($this->discountSubtotalPercentage > 0 )
            {
                $this->discountSubtotalAmount = ($this->subtotal * $this->discountSubtotalPercentage) / 100;

                $this->total = $this->calculateTotalAndTaxOverSubtotal($this->subtotal - $this->discountSubtotalAmount);
            }
        }
    }

    /**
     * Calculate discount and tax over subtotal amount
     * @param   float   $subtotal
     * @return  float
     */
    private function calculateTotalAndTaxOverSubtotal($subtotal)
    {
        // when calculate amounts, also you calculate tax,
        // to do this operation and don't sum old values, you need reset amounts values from
        // tax rules
        $this->resetTaxAmounts();

        // calculate amounts of each taxRule
        $taxRules       = $this->taxRules->sortBy('priority');
        $lastPriority   = null;
        $subtotalAux    = $subtotal;

        foreach ($taxRules as &$taxRule)
        {
            if($lastPriority == null || $lastPriority != $taxRule->priority)
            {
                // if is a different priority, calculate tax over subtotal plus previous tax amounts
                $lastPriority = $taxRule->priority;
                $subtotalAux  += $taxRules->sum('taxAmount'); // attention, reset tax amounts before sum
            }
            $taxRule->taxAmount = $subtotalAux * ($taxRule->taxRate / 100);
        }

        // return total
        return $subtotal + $this->taxRules->sum('taxAmount');
    }

    /**
     * Calculate discount and tax over subtotal amount
     *
     * @param   float   $total
     * @return  float
     */
    private function calculateSubtotalAndTaxOverTotal($total)
    {
        // when calculate amounts, also you calculate tax,
        // to do this operation and don't sum old values, you need reset amounts values from
        // tax rules
        $this->resetTaxAmounts();

        // calculate amounts of each taxRule
        $taxRules       = $this->taxRules->sortByDesc('priority');  // sort taxRules desc direction to get subtotal
        $lastPriority   = null;
        $totalAux       = $total;

        foreach ($taxRules as &$taxRule)
        {
            if($lastPriority === null || $lastPriority != $taxRule->priority)
            {
                // if is a different priority, calculate tax over subtotal plus previous tax amounts
                $lastPriority = $taxRule->priority;
                $totalAux     -= $taxRules->sum('taxAmount'); // attention, reset tax amounts before sum
            }
            $taxRule->taxAmount = ($totalAux * $taxRule->taxRate) / ($taxRule->taxRate + 100);
        }

        // return subtotal
        return $this->total - $this->taxRules->sum('taxAmount');
    }

    /**
     * Reset tax amount before calculate
     *
     * @return void
     */
    private function resetTaxAmounts()
    {
        foreach ($this->taxRules as &$taxRule)
        {
            $taxRule->taxAmount = 0;
        }
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