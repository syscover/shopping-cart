<?php namespace Syscover\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;

class Item implements Arrayable
{
    /**
     * The rowID of the cart item
     *
     * @var string
     */
    public $rowId;

    /**
     * The ID of the cart item
     *
     * @var int|string
     */
    public $id;

    /**
     * The name of the cart item
     *
     * @var string
     */
    public $name;

    /**
     * The price per unit without tax
     *
     * @var float
     */
    private $unitPrice;

    /**
     * The input price when instance object
     *
     * @var float
     */
    private $inputPrice;

    /**
     * set if product is transportable
     *
     * @var boolean
     */
    public $transportable;

    /**
     * Weight or unit to calculate shipping amount
     *
     * @var int|float
     */
    public $weight;

    /**
     * The tax rules for the cart item
     *
     * @var \Syscover\ShoppingCart\CartItemTaxRules;
     */
    public $taxRules;

    /**
     * The options for this cart item
     *
     * @var array
     */
    public $options;

    /**
     * The quantity for this cart item
     *
     * @var int|float
     */
    private $quantity;

    /**
     * Price multiplied by quantity
     *
     * @var int|float
     */
    public $subtotal;

    /**
     * Price multiplied by quantity less discounts
     *
     * @var int|float
     */
    public $subtotalWithDiscounts;

    /**
     * The quantity for this cart item
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
     * Cart prices rules with discounts percentage over subtotal, your type is DISCOUNT_SUBTOTAL_PERCENTAGE
     *
     * @var CartItemDiscounts
     */
    protected $discountsSubtotalPercentage;

    /**
     * Cart prices rules with discounts percentage over total, your type is DISCOUNT_TOTAL_PERCENTAGE
     *
     * @var CartItemDiscounts
     */
    protected $discountsTotalPercentage;

    /**
     * Cart prices rules with discounts fixed over subtotal, your type is DISCOUNT_SUBTOTAL_FIXED_AMOUNT
     *
     * @var CartItemDiscounts
     */
    protected $discountsSubtotalFixed;

    /**
     * Cart prices rules with discounts fixed over total, your type is DISCOUNT_TOTAL_FIXED_AMOUNT
     *
     * @var CartItemDiscounts
     */
    protected $discountsTotalFixed;


    /**
     * CartItem constructor.
     * When we create a new Item, automatically create all amounts for this Item, to call method setQuantity
     *
     * @param int|string                            $id
     * @param string                                $name
     * @param float                                 $quantity
     * @param float                                 $inputPrice
     * @param boolean                               $transportable
     * @param float|null                            $weight
     * @param array|\Syscover\ShoppingCart\TaxRule  $taxRule
     * @param array                                 $options
     */
    public function __construct($id, $name, $quantity, $inputPrice, $weight = 1.000, $transportable = true, $taxRule = [], array $options = [])
    {
        if(empty($id))
            throw new \InvalidArgumentException('Please supply a valid identifier.');

        if(empty($name))
            throw new \InvalidArgumentException('Please supply a valid name.');

        if(strlen($inputPrice) < 0 || ! is_numeric($inputPrice))
            throw new \InvalidArgumentException('Please supply a valid price.');

        if(! is_bool($transportable))
            throw new \InvalidArgumentException('Please supply a valid transportable.');

        if($weight === null)
            $weight = 0;

        if(strlen($weight) < 0 || ! is_numeric($weight))
            throw new \InvalidArgumentException('Please supply a valid weight.');

        $this->id               = $id;
        $this->name             = $name;
        $this->inputPrice       = floatval($inputPrice);
        $this->transportable    = $transportable;
        $this->weight           = floatval($weight);
        $this->options          = new Options($options);
        $this->taxRules         = new CartItemTaxRules();
        $this->rowId            = $this->generateRowId($id, $options);

        // set discounts
        $this->discountsSubtotalPercentage  = new CartItemDiscounts();
        $this->discountsTotalPercentage     = new CartItemDiscounts();
        $this->discountsSubtotalFixed       = new CartItemDiscounts();
        $this->discountsTotalFixed          = new CartItemDiscounts();

        // add tax rule to taxRules property
        $this->addTaxRule($taxRule);

        // When set quantity, calculate all amounts, for this reason this is last function
        // called in constructor
        $this->setQuantity($quantity);
    }

    //*****************
    // Getters
    //*****************

    /**
     * magic method to make accessing the total, tax and subtotal properties
     *
     * @param   string      $attribute
     * @return  null|float|CartItemDiscounts
     */
    public function __get($attribute)
    {
        if($attribute === 'discountsSubtotalPercentage')
        {
            return $this->discountsSubtotalPercentage;
        }

        if($attribute === 'discountsTotalPercentage')
        {
            return $this->discountsTotalPercentage;
        }

        if($attribute === 'discountSubtotalPercentage')
        {
            return $this->discountsSubtotalPercentage->sum('percentage');
        }

        if($attribute === 'discountTotalPercentage')
        {
            return $this->discountsTotalPercentage->sum('percentage');
        }

        if($attribute === 'discountSubtotalPercentageAmount')
        {
            return $this->discountsSubtotalPercentage->sum('amount');
        }

        if($attribute === 'discountTotalPercentageAmount')
        {
            return $this->discountsTotalPercentage->sum('amount');
        }

        if($attribute === 'discountSubtotalFixedAmount')
        {
            return $this->discountsSubtotalFixed->sum('amount');
        }

        if($attribute === 'discountTotalFixedAmount')
        {
            return $this->discountsTotalFixed->sum('amount');
        }

        if($attribute === 'discountAmount')
        {
            return
                $this->discountSubtotalPercentageAmount +
                $this->discountTotalPercentageAmount +
                $this->discountSubtotalFixedAmount +
                $this->discountTotalFixedAmount;
        }

        if($attribute === 'taxAmount')
        {
            return $this->taxRules->sum('taxAmount');
        }

        // get price for item, can to be with or without tax depend of configuration
        if($attribute === 'price')
        {
            if(config('shoppingCart.taxProductDisplayPrices') == Cart::PRICE_WITHOUT_TAX)
            {
                return $this->unitPrice;
            }
            elseif(config('shoppingCart.taxProductDisplayPrices') == Cart::PRICE_WITH_TAX)
            {
                return $this->calculateUnitPriceWithTax($this->unitPrice);
            }
        }

        if($attribute === 'quantity')
        {
            return $this->quantity;
        }

        if($attribute === 'totalWithoutDiscounts')
        {
            return $this->total + $this->discountAmount;
        }

        return null;
    }

    /**
     * Add TaxRule to cartItemTaxRules object
     *
     * @param   array|\Syscover\ShoppingCart\TaxRule        $taxRule
     * @return  \Syscover\ShoppingCart\CartItemTaxRules
     */
    public function addTaxRule($taxRule)
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
     * @param   string    $thousandSeparator
     * @return  string
     */
    public function getPrice($decimals = 2, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->price, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns array with all tax rates apply over item formated
     *
     * @param   int       $decimals
     * @param   string    $decimalPoint
     * @param   string    $thousandSeparator
     * @return  array
     */
    public function getTaxRates($decimals = 0, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return array_map(function($taxRate) use ($decimals, $decimalPoint, $thousandSeparator) {
            return number_format($taxRate, $decimals, $decimalPoint, $thousandSeparator);
        },  $this->taxRules->pluck('taxRate')->toArray());
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeparator
     * @return  string
     */
    public function getSubtotal($decimals = 2, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->subtotal, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted subtotal with discount.
     * Subtotal with discount is price for whole CartItem less discounts without TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeparator
     * @return  string
     */
    public function getSubtotalWithDiscounts($decimals = 2, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->subtotalWithDiscounts, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted tax amount.
     *
     * @param   int       $decimals
     * @param   string    $decimalPoint
     * @param   string    $thousandSeparator
     * @return  string
     */
    public function getTaxAmount($decimals = 2, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->taxAmount, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeparator
     * @return  string
     */
    public function getDiscountAmount($decimals = 2, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->discountAmount, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted total.
     * Total is price for whole CartItem with TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeparator
     * @return  string
     */
    public function getTotal($decimals = 2, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->total, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted total without discount amount.
     * Total is price for whole CartItem with TAX
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeparator
     * @return  string
     */
    public function getTotalWithoutDiscounts($decimals = 2, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->totalWithoutDiscounts, $decimals, $decimalPoint, $thousandSeparator);
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
     * Get format discountsSubtotalPercentage over this cart item.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeparator
     * @return  string
     */
    public function getDiscountSubtotalPercentage($decimals = 0, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->discountsSubtotalPercentage->sum('percentage'), $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get format discountTotalPercentage over this cart item.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeparator
     * @return  string
     */
    public function getDiscountTotalPercentage($decimals = 0, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->discountsTotalPercentage->sum('percentage'), $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get format quantity over this cart item.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeparator
     * @return  string
     */
    public function getQuantity($decimals = 0, $decimalPoint = ',', $thousandSeparator = '.')
    {
        return number_format($this->quantity, $decimals, $decimalPoint, $thousandSeparator);
    }


    //*****************
    // Setters
    //*****************

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
     * Set subtotal discount percentage over this cart item.
     *
     * @param   \Syscover\ShoppingCart\Discount   $discount
     * @return  \Syscover\ShoppingCart\Item
     */
    public function setDiscountSubtotalPercentage(Discount $discount)
    {
        if($discount->percentage !== 0 && (empty($discount->percentage) || ! is_numeric($discount->percentage)))
            throw new \InvalidArgumentException('Please supply a valid discount percentage.');

        if($this->discountsTotalPercentage->count() > 0)
            throw new \InvalidArgumentException('You can\'t apply discount over subtotal, when you already have discounts over total.');

        // add discount subtotal percentage
        $this->discountsSubtotalPercentage->put($discount->id, $discount);

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

        if($this->discountsSubtotalPercentage->count() > 0)
            throw new \InvalidArgumentException('You can\'t apply discount over total, when you already have discounts over subtotal.');

        // set discount total percentage
        $this->discountsTotalPercentage->put($discount->id, $discount);

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
        $this->discountsSubtotalFixed->put($discount->id, $discount);

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
        $this->discountsTotalFixed->put($discount->id, $discount);

        $this->calculateAmounts();

        return $this;
    }



    //*****************
    // Methods
    //*****************

    /**
     * Calculate all amounts, this function is called, when change any property from cartItem
     *
     * @param   null    $mode   you can force mode of calculate amounts, with tax or without tax
     * @return  void
     */
    public function calculateAmounts($mode = null)
    {
        // subtotal calculate
        // PRICE WITHOUT TAX
        if(
            ($mode == Cart::PRICE_WITHOUT_TAX) ||
            ($mode == null && config('shoppingCart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX || $this->taxRules === null || $this->taxRules->count() == 0)
        )
        {
            if(! isset($this->unitPrice))
                $this->unitPrice = $this->inputPrice;

            // calculate subtotal
            $this->subtotal                 = $this->quantity * $this->unitPrice;
            $this->subtotalWithDiscounts    = $this->subtotal;

            if($this->discountsSubtotalFixed->sum('fixed') > 0)
            {
                // calculate subtotal including with discount fixed amount
                $this->subtotalWithDiscounts = $this->subtotal - $this->discountSubtotalFixedAmount;
            }

            // calculate all amounts for price without tax
            $this->total = $this->calculateTotalAndTaxOverSubtotal($this->subtotalWithDiscounts);

            // calculate discount total fixed amount
            if($this->discountsTotalFixed->sum('fixed') > 0)
            {
                // calculate total less discount fixed amount
                $this->total -= $this->discountTotalFixedAmount;

                $this->subtotalWithDiscounts = $this->calculateSubtotalAndTaxOverTotal($this->total);
            }

            // when we have subtotal, subtotalWithDiscounts and total amount, calculate percentage discounts
            $this->applyDiscountsPercentage();
        }

        // PRICE WITH TAX
        elseif(
            ($mode == Cart::PRICE_WITH_TAX) ||
            ($mode == null && config('shoppingCart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        )
        {
            // total calculate
            $this->total = $this->quantity * $this->inputPrice;

            // check  that taxes have been calculated
            $isCalculateTax = false;

            if(! isset($this->unitPrice))
            {
                // calculate unit price without tax
                $this->unitPrice    = $this->calculateUnitPriceOverPriceWithTax($this->inputPrice);
            }

            // subtotal is the amount without any discount
            $this->subtotal                 = $this->quantity * $this->unitPrice;
            $this->subtotalWithDiscounts    = $this->subtotal;

            if($this->discountsTotalFixed->sum('fixed') > 0)
            {
                // calculate total including possible discount fixed amount
                $this->total = $this->total - $this->discountTotalFixedAmount;

                // calculate subtotal with total discounts
                $this->subtotalWithDiscounts    = $this->calculateSubtotalAndTaxOverTotal($this->total);
                $isCalculateTax                 = true;
            }


            // calculate discount subtotal fixed amount
            if($this->discountsSubtotalFixed->sum('fixed') > 0)
            {
                // calculate subtotal less discount fixed amount
                $this->subtotalWithDiscounts -= $this->discountSubtotalFixedAmount;

                $this->total    = $this->calculateTotalAndTaxOverSubtotal($this->subtotalWithDiscounts);
                $isCalculateTax = true;
            }

            //  if tax haven't been calculated before, calculate it now
            if(! $isCalculateTax)
            {
                $this->calculateTaxAmountOverSubtotal($this->subtotalWithDiscounts);
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
    protected function applyDiscountsPercentage()
    {
        // calculate if there are subtotal percentage discount
        if($this->discountsTotalPercentage->count() > 0)
        {
            $this->discountsTotalPercentage->transform(function(Discount $discount, $key) {
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
        if($this->discountsSubtotalPercentage->count() > 0)
        {
            $this->discountsSubtotalPercentage->transform(function(Discount $discount, $key) {
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
    protected function calculateTotalAndTaxOverSubtotal($subtotal)
    {
        $this->calculateTaxAmountOverSubtotal($subtotal);

        // return total
        return $subtotal + $this->taxRules->sum('taxAmount');
    }

    /**
     * Calculate discount and tax over subtotal amount
     *
     * @param   float   $total
     * @return  float
     */
    protected function calculateSubtotalAndTaxOverTotal($total)
    {
        $this->calculateTaxAmountOverTotal($total);

        // return subtotal
        return $this->total - $this->taxRules->sum('taxAmount');
    }

    /**
     * Calculate tax over subtotal
     *
     * @param   $subtotal
     * @return  void
     */
    protected function calculateTaxAmountOverSubtotal($subtotal)
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
    }

    /**
     * Calculate tax over total
     *
     * @param   $total
     * @return  void
     */
    protected function calculateTaxAmountOverTotal($total)
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
    }

    /**
     * Calculate unit price with tax, doesn't account discounts.
     * it is to calculate the result of the function getPrice if display prices are with tax
     *
     * @param   float   $unitPrice
     * @return  float
     */
    protected function calculateUnitPriceWithTax($unitPrice)
    {
        // calculate amounts of each taxRule
        $taxRules       = $this->taxRules->sortBy('priority');
        $lastPriority   = null;
        $unitPriceAux   = $unitPrice;
        $taxAmount      = 0;

        foreach ($taxRules as $taxRule)
        {
            if($lastPriority == null || $lastPriority != $taxRule->priority)
            {
                // if is a different priority, calculate tax over subtotal plus previous tax amounts
                $lastPriority   = $taxRule->priority;
                $unitPriceAux  += $taxAmount;
            }
            $taxAmount += $unitPriceAux * ($taxRule->taxRate / 100);
        }

        return $unitPrice + $taxAmount;
    }

    /**
     * Calculate unit price over price with tax
     * @param   float   $priceWithTax
     * @return  float
     */
    protected function calculateUnitPriceOverPriceWithTax($priceWithTax)
    {
        // calculate amounts of each taxRule
        $taxRules           = $this->taxRules->sortByDesc('priority');  // sort taxRules desc direction to get subtotal
        $lastPriority       = null;
        $priceAuxWithTax    = $priceWithTax;
        $taxAmount          = 0;

        foreach ($taxRules as &$taxRule)
        {
            if($lastPriority === null || $lastPriority != $taxRule->priority)
            {
                // if is a different priority, calculate tax over subtotal plus previous tax amounts
                $lastPriority       = $taxRule->priority;
                $priceAuxWithTax    -= $taxAmount; // attention, reset tax amounts before sum
            }

            $taxAmount += ($priceAuxWithTax * $taxRule->taxRate) / ($taxRule->taxRate + 100);
        }

        return $priceWithTax - $taxAmount;
    }

    /**
     * Reset tax amount before calculate
     *
     * @return void
     */
    public function resetTaxRules()
    {
        $this->taxRules = new CartItemTaxRules();
        $this->resetTaxAmounts();
    }

    /**
     * Reset tax amount before calculate
     *
     * @return void
     */
    protected function resetTaxAmounts()
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
            'initPrice'     => $this->initPrice,
            'transportable' => $this->transportable,
            'weight'        => $this->weight,
            'options'       => $this->options,
        ];
    }
}