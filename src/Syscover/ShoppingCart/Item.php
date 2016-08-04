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
     * Price multiplied by quantity.
     *
     * @var int|float
     */
    public $subtotal;

    /**
     * Price multiplied by quantity less discounts.
     *
     * @var int|float
     */
    public $subtotalWithDiscounts;

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
     * Discount percentage over subtotal from this item.
     *
     * @var int|float
     */
    private $discountSubtotalPercentage;

    /**
     * Discount percentage over total from this item.
     *
     * @var int|float
     */
    private $discountTotalPercentage;

    /**
     * Discount amount if discount type is DISCOUNT_SUBTOTAL_FIXED_AMOUNT
     *
     * @var float
     */
    private $discountSubtotalFixedAmount ;

    /**
     * Discount amount if discount type is DISCOUNT_TOTAL_FIXED_AMOUNT
     *
     * @var float
     */
    private $discountTotalFixedAmount;


    /**
     * CartItem constructor.
     * When we create a new Item, automatically create all amounts for this Item, to call method setQuantity
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

        // set discounts
        $this->discountSubtotalPercentage   = new CartItemDiscounts();
        $this->discountTotalPercentage      = new CartItemDiscounts();
        $this->discountSubtotalFixedAmount  = new CartItemDiscounts();
        $this->discountTotalFixedAmount     = new CartItemDiscounts();

        // add tax rule to taxRules property
        $this->addTaxRule($taxRule);

        // When set quantity, calculate all amounts, for this reason this is last function
        // called in constructor
        $this->setQuantity($quantity);
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

        if($attribute === 'discountSubtotalPercentageAmount')
        {
            return $this->discountSubtotalPercentage->sum('amount');
        }

        if($attribute === 'discountTotalPercentageAmount')
        {
            return $this->discountTotalPercentage->sum('amount');
        }

        if($attribute === 'discountSubtotalFixedAmount')
        {
            return $this->discountSubtotalFixedAmount->sum('amount');
        }

        if($attribute === 'discountTotalFixedAmount')
        {
            return $this->discountTotalFixedAmount->sum('amount');
        }

        if($attribute === 'discountAmount')
        {
            return
                $this->discountSubtotalPercentageAmount +
                $this->discountTotalPercentageAmount +
                $this->discountSubtotalFixedAmount->sum('amount') +
                $this->discountTotalFixedAmount->sum('amount');
        }

        if($attribute === 'taxAmount')
        {
            return $this->taxRules->sum('taxAmount');
        }
        return null;
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
     * Returns the formatted subtotal with discount.
     * Subtotal with discount is price for whole CartItem less discounts without TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  string
     */
    public function getSubtotalWithDiscounts($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->subtotalWithDiscounts, $decimals, $decimalPoint, $thousandSeperator);
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
        return number_format($this->discountSubtotalPercentage->sum('percentage'), $decimals, $decimalPoint, $thousandSeperator);
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
     * @param   \Syscover\ShoppingCart\Discount   $discount
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setDiscountSubtotalPercentage(Discount $discount)
    {
        if($discount->percentage !== 0 && (empty($discount->percentage) || ! is_numeric($discount->percentage)))
            throw new \InvalidArgumentException('Please supply a valid discount percentage.');

        if($this->discountTotalPercentage->count() > 0)
            throw new \InvalidArgumentException('You can\'t apply discount over subtotal, when you already have discounts over total.');

        // add discount subtotal percentage
        $this->discountSubtotalPercentage->put($discount->id, $discount);

        $this->calculateAmounts();

        return $this;
    }

    /**
     * Set total discount percentage over this cart item.
     *
     * @param   \Syscover\ShoppingCart\Discount   $discount
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setDiscountTotalPercentage(Discount $discount)
    {
        if($discount->percentage !== 0 && (empty($discount->percentage) || ! is_numeric($discount->percentage)))
            throw new \InvalidArgumentException('Please supply a valid discount percentage.');

        if($this->discountSubtotalPercentage->count() > 0)
            throw new \InvalidArgumentException('You can\'t apply discount over total, when you already have discounts over subtotal.');

        // set discount total percentage
        $this->discountTotalPercentage->put($discount->id, $discount);

        $this->calculateAmounts();

        return $this;
    }

    /**
     * Set subtotal discount fixed over this cart item.
     *
     * @param   \Syscover\ShoppingCart\Discount   $discount
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setDiscountSubtotalFixed(Discount $discount)
    {
        if($discount->amount !== 0 && (empty($discount->amount) || ! is_numeric($discount->amount)))
            throw new \InvalidArgumentException('Please supply a valid discount percentage.');

        // set discount subtotal fixed amount
        $this->discountSubtotalFixedAmount->put($discount->id, $discount);

        $this->calculateAmounts();

        return $this;
    }

    /**
     * Set total discount percentage over this cart item.
     *
     * @param   \Syscover\ShoppingCart\Discount   $discount
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setDiscountTotalFixed(Discount $discount)
    {
        if($discount->amount !== 0 && (empty($discount->amount) || ! is_numeric($discount->amount)))
            throw new \InvalidArgumentException('Please supply a valid discount percentage.');

        // set discount total fixed amount
        $this->discountTotalFixedAmount->put($discount->id, $discount);

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

            if($this->discountSubtotalFixedAmount->sum('fixed') > 0)
            {
                // calculate subtotal including with discount fixed amount
                $this->subtotalWithDiscounts = ($this->quantity * $this->price) - $this->discountSubtotalFixedAmount->sum('amount');
            }
            else
            {
                // if there are not discount, save subtotal value in subtotalWithDiscounts to calculate possible percentage discounts
                $this->subtotalWithDiscounts = $this->subtotal;
            }

            // calculate all amounts for price without tax
            $this->total = $this->calculateTotalAndTaxOverSubtotal($this->subtotalWithDiscounts);

            // calculate discount total fixed amount
            if($this->discountTotalFixedAmount->sum('fixed') > 0)
            {
                // calculate total less discount fixed amount
                $this->total -= $this->discountTotalFixedAmount->sum('amount');

                $this->subtotalWithDiscounts = $this->calculateSubtotalAndTaxOverTotal($this->total);
            }

            // when we have subtotal, subtotalWithDiscounts and total amount, calculate percentage discounts
            $this->applyDiscountsPercentage();
        }

        elseif(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            // total calculate
            $this->total = $this->quantity * $this->price;

            if($this->discountTotalFixedAmount->sum('fixed') > 0)
            {
                // calculate total including possible discount fixed amount
                $this->total = ($this->quantity * $this->price) - $this->discountTotalFixedAmount->sum('amount');
            }

            // calculate all amounts for price with tax
            $this->subtotalWithDiscounts = $this->calculateSubtotalAndTaxOverTotal($this->total);

            // to get subtotal without discount, subtotal is the amount without any discount
            $this->subtotal = ($this->subtotalWithDiscounts * ($this->quantity * $this->price)) / $this->total;

            // calculate discount subtotal fixed amount
            if($this->discountSubtotalFixedAmount->sum('fixed') > 0)
            {
                // calculate subtotal less discount fixed amount
                $this->subtotalWithDiscounts -= $this->discountSubtotalFixedAmount->sum('amount');

                $this->total = $this->calculateTotalAndTaxOverSubtotal($this->subtotalWithDiscounts);
            }

            // when we have subtotal, subtotalWithDiscounts and total amount, calculate percentage discounts
            $this->applyDiscountsPercentage();
        }
    }

    /**
     * Check if has percentage discounts and apply this discounts
     *
     * @return void
     */
    private function applyDiscountsPercentage()
    {
        // calculate if there are subtotal percentage discount
        if($this->discountTotalPercentage->count() > 0)
        {
            $this->discountTotalPercentage->transform(function(Discount $discount, $key) {
                // calculate discount to apply
                $discount->amount = ($this->total * $discount->percentage) / 100;

                // check than discount is not greater than maximumPercentageAmount
                if($discount->maximumPercentageAmount !== null && $discount->amount > $discount->maximumPercentageAmount)
                    $discount->amount = $discount->maximumPercentageAmount;

                return $discount;
            });

            // calculate again total amount less discount
            $this->total -= $this->discountTotalPercentageAmount;

            // calculate all amounts for price with tax
            $this->subtotalWithDiscounts = $this->calculateSubtotalAndTaxOverTotal($this->total);
        }

        // calculate if there are total percentage discount
        if($this->discountSubtotalPercentage->count() > 0)
        {
            $this->discountSubtotalPercentage->transform(function(Discount $discount, $key) {
                // calculate discount to apply
                $discount->amount = ($this->subtotalWithDiscounts * $discount->percentage) / 100;

                // check than discount is not greater than maximumPercentageAmount
                if($discount->maximumPercentageAmount !== null && $discount->amount > $discount->maximumPercentageAmount)
                    $discount->amount = $discount->maximumPercentageAmount;

                return $discount;
            });

            // calculate again subtotal amount less discount
            $this->subtotalWithDiscounts -= $this->discountSubtotalPercentageAmount;

            $this->total = $this->calculateTotalAndTaxOverSubtotal($this->subtotalWithDiscounts);
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