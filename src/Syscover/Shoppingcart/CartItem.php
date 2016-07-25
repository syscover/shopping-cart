<?php namespace Syscover\Shoppingcart;

use Illuminate\Contracts\Support\Arrayable;

class CartItem implements Arrayable
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
     * @var \Syscover\Shoppingcart\CartItemTaxRules;
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
    private $qty;

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
     * CartItem constructor.
     *
     * @param int|string    $id
     * @param string        $name
     * @param float         $price
     * @param boolean       $transportable
     * @param float         $weight
     * @param array         $options
     * @param array         $taxRules
     */
    public function __construct($id, $name, $price, $transportable, $weight, array $options = [], array $taxRules = [])
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
        $this->taxRules         = $this->createArrayCartItemTaxRule($taxRules);
        $this->rowId            = $this->generateRowId($id, $options);
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int|string $id
     * @param string     $name
     * @param float      $price
     * @param boolean    $transportable
     * @param float      $weight
     * @param array      $taxRules
     * @param array      $options
     * @return \Syscover\Shoppingcart\CartItem
     */
    public static function fromAttributes($id, $name, $price, $transportable, $weight, array $options = [], array $taxRules = [])
    {
        return new self($id, $name, $price, $transportable, $weight, $options, $taxRules);
    }

    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     * @return \Syscover\Shoppingcart\CartItem
     */
    public static function fromArray(array $attributes)
    {
        $options    = array_get($attributes, 'options', []);
        $taxRules   = array_get($attributes, 'taxRules', []);

        return new self($attributes['id'], $attributes['name'], $attributes['price'], $attributes['transportable'], $attributes['weight'], $options, $taxRules);
    }

    private function calculateAmounts()
    {
        // subtotal calculate
        if(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX || $this->taxRules === null || $this->taxRules->count() == 0)
        {
            $this->subtotal = $this->qty * $this->price;

            if($this->taxRules === null || $this->taxRules->count() == 0)
            {
                $this->taxAmount = 0;
            }
            else
            {
                // calculate amounts of each taxRule
                $taxRules       = $this->taxRules->sortBy('priority');
                $lastPriority   = 0;
                $baseAux        = $this->subtotal;
                foreach ($taxRules as $taxRule)
                {
                    if($lastPriority == $taxRule->priority)
                    {
                        $taxRule->taxAmount = $baseAux * ($taxRule->taxRate / 100);
                    }
                    else
                    {
                        $lastPriority = $taxRule->priority;

                        // if is a different priority, calculate tax over subtotal plus previous tax amounts
                        $baseAux            += $taxRules->sum('taxAmount');
                        $taxRule->taxAmount = $baseAux * ($taxRule->taxRate / 100);
                    }
                }

                // set total tax from this car item
                $this->taxAmount = $this->taxRules->sum('taxAmount');
            }
            $this->total = $this->subtotal + $this->taxAmount;
        }
        elseif(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {

            $taxRules       = $this->taxRules->sortByDesc('priority');
            $lastPriority   = null;
            $this->total    = $this->qty * $this->price;
            $totalAux       = $this->total;
            $taxRateAux     = 0;
            $taxAmountAux   = 0;

            foreach ($taxRules as $taxRule)
            {
                if($lastPriority === null || $lastPriority != $taxRule->priority)
                {
                    $lastPriority = $taxRule->priority;
                    $totalAux     -= $taxAmountAux;
                }

                $taxRateAux         = $taxRules->where('priority', $taxRule->priority)->sum('taxRate');
                $taxAmountAux       = ($taxRateAux * $totalAux) / ($taxRateAux + 100);
                $taxRule->taxAmount = ($taxAmountAux * $taxRule->taxRate) / $taxRateAux;


//                if($lastPriority == $taxRule->priority)
//                {
//
//                }
//                else
//                {
//                    $totalAux           -= $taxAmountAux;
//
//
//                    $lastPriority       = $taxRule->priority;
//                    $taxRule->taxAmount = ($this->total * 100) / ($this->taxRules->sum('taxRate') + 100);
//                    $base               = $this->total - $taxRule->taxAmount;
//                }
            }

            $this->taxAmount = $taxRules->sum('taxAmount');
            $this->subtotal = $this->total - $this->taxAmount;


            //$this->subtotal     = (($this->qty * $this->price) * 100) / ($this->taxRules->sum('taxRate') + 100);
            //$this->taxAmount    = (($this->qty * $this->price) * $this->taxRules->sum('taxRate')) / ($this->taxRules->sum('taxRate') + 100);

        }
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
        return $this->qty;
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int|float $qty
     */
    public function setQuantity($qty)
    {
        if($qty !== 0 && (empty($qty) || ! is_numeric($qty)))
            throw new \InvalidArgumentException('Please supply a valid quantity.');

        $this->qty = $qty;
        $this->calculateAmounts();
    }

    /**
     * Update the cart item from an array.
     *
     * @param array| $attributes
     * @return void
     */
//    public function update($attributes)
//    {
//        if(is_array($attributes))
//        {
//            $this->id       = array_get($attributes, 'id', $this->id);
//            $this->qty      = array_get($attributes, 'qty', $this->qty);
//            $this->name     = array_get($attributes, 'name', $this->name);
//            $this->price    = array_get($attributes, 'price', $this->price);
//
//            //$this->priceTax = $this->price + $this->tax;
//
//            $this->options  = new CartItemOptions(array_get($attributes, 'options', []));
//            $this->rowId    = $this->generateRowId($this->id, $this->options->all());
//        }
//    }


    /**
     * Create a new TaxRule for CartItem
     *
     * @param   array    $taxRules
     * @return  \Syscover\Shoppingcart\CartItemTaxRules
     */
    private function createArrayCartItemTaxRule(array $taxRules = [])
    {
        if(! is_array($taxRules))
            throw new \InvalidArgumentException('Please supply a valid value to tax rules.');

        if(count($taxRules) == 0)
            return null;

        if(! $this->isMulti($taxRules))
        {
            return new CartItemTaxRules([new CartItemTaxRule($taxRules['name'], $taxRules['priority'], $taxRules['sortOrder'], $taxRules['taxRate'])]);
        }

        // Manipulate data with collections object
        $taxRules = new CartItemTaxRules($taxRules);
        $response = new CartItemTaxRules();

        foreach ($taxRules as $taxRule)
        {
            $newTaxRule = new CartItemTaxRule($taxRule['name'], $taxRule['priority'], $taxRule['sortOrder'], $taxRule['taxRate']);

            if($response->where('id', $newTaxRule->id)->count() > 0)
            {
                // If is exist a same taxRule, sum the tax rates
                $response->where('id', $newTaxRule->id)->first()->taxRate += $newTaxRule->taxRate;
            }
            else
            {
                // add new tax rule
                $response->put($newTaxRule->id, $newTaxRule);
            }
        }

        return $response;
    }

    /**
     * Check if the item is a multidimensional array or an array of Buyables.
     *
     * @param   mixed $item
     * @return  bool
     */
    protected function isMulti($item)
    {
        if ( ! is_array($item)) return false;

        return is_array(head($item));
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
            'qty'           => $this->qty,
            'price'         => $this->price,
            'transportable' => $this->transportable,
            'weight'        => $this->weight,
            'options'       => $this->options,
        ];
    }
}