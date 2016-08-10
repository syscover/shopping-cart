<?php namespace Syscover\ShoppingCart;

use Closure;
use Syscover\ShoppingCart\Exceptions\ShoppingCartNotCombinablePriceRuleException;

/**
 * Class Cart
 *
 * This object obtain yours data about amounts on fly, each petition calculate your result
 *
 * @package Syscover\ShoppingCart
 */

class Cart 
{
    const PRICE_WITHOUT_TAX = 1;
    const PRICE_WITH_TAX    = 2;

	/**
	 * Current cart instance
	 *
	 * @var string
	 */
	protected $instance;

	/**
	 * object that contain all cart items
	 *
	 * @var \Syscover\ShoppingCart\CartItems
	 */
	protected $cartItems;

	/**
	 * all discounts applied to cart
	 *
	 * @var \Syscover\ShoppingCart\CartPriceRules
	 */
	protected $cartPriceRules;

    /**
     * check if inside $cartPriceRulesContent has a not combinable rule.
     *
     * @var boolean
     */
    protected $hasCartPriceRuleNotCombinable;

    /**
     * check if cart has shipping products
     *
     * @var boolean
     */
    protected $hasShipping;

    /**
     * data of shipping
     *
     * @var array
     */
    private $shipping;

    /**
     * check if cart has free shipping
     *
     * @var boolean
     */
    protected $hasFreeShipping;

	/**
	 * shipping amount
	 *
	 * @var double
	 */
	protected $shippingAmount;

    /**
     * check if user require billing for this cart
     *
     * @var boolean
     */
    public $hasBilling;

    /**
     * data of billing
     *
     * @var array
     */
    private $billing;


	/**
	 * Cart constructor.
     *
	 * @param   string  $instance
	 */
	public function __construct($instance)
	{
		$this->instance 					    = $instance;
		$this->cartItems 			            = new CartItems();
		$this->cartPriceRules 		            = new CartPriceRules();
        $this->hasCartPriceRuleNotCombinable 	= false;
	}

    //*****************
    // Getters
    //*****************

    /**
     * Magic method to access private attributes
     *
     * @param   string $attribute
     * @return  float|null
     */
    public function __get($attribute)
    {
        if($attribute === 'total') {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($total, Item $item) {
                return $total + $item->total;
            }, 0);
        }

        if($attribute === 'taxAmount') {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($taxAmount, Item $item) {
                return $taxAmount + $item->taxAmount;
            }, 0);
        }

        if($attribute === 'subtotal')
        {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($subTotal, Item $item) {
                return $subTotal + $item->subtotal;
            }, 0);
        }

        if($attribute === 'discountSubtotalPercentageAmount')
        {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($discountSubtotalPercentageAmount, Item $item) {
                return $discountSubtotalPercentageAmount + $item->discountSubtotalPercentageAmount;
            }, 0);
        }

        if($attribute === 'discountTotalPercentageAmount')
        {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($discountTotalPercentageAmount, Item $item) {
                return $discountTotalPercentageAmount + $item->discountTotalPercentageAmount;
            }, 0);
        }

        if($attribute === 'discountSubtotalAmount')
        {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($discountSubtotalAmount, Item $item) {
                return $discountSubtotalAmount + $item->discountSubtotalPercentageAmount + $item->discountSubtotalFixedAmount;
            }, 0);
        }

        if($attribute === 'discountTotalAmount')
        {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($discountTotalAmount, Item $item) {
                return $discountTotalAmount + $item->discountTotalPercentageAmount + $item->discountTotalFixedAmount;
            }, 0);
        }

        if($attribute === 'discountAmount')
        {
            return $this->discountSubtotalAmount + $this->discountTotalAmount;
        }

        if($attribute === 'shippingAmount')
        {
            return $this->shippingAmount;
        }

        if($attribute === 'weight')
        {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($weight, Item $item) {
                return $weight + ($item->weight * $item->quantity);
            }, 0);
        }

        if($attribute === 'transportableWeight')
        {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($weight, Item $item) {
                if($item->transportable === true)
                    return $weight + ($item->weight * $item->quantity);
            }, 0);
        }

        return null;
    }

    /**
     * Get the cart items
     *
     * @return \Syscover\ShoppingCart\CartItems
     */
    public function getCartItems()
    {
        return $this->cartItems;
    }

    /**
     * Get the subtotal formated of the items in the cart.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  float
     */
    public function getSubtotal($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->subtotal, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the taxAmount formated of the items in the cart.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  float
     */
    public function getTaxAmount($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->taxAmount, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the subtotal formated of the items in the cart.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  float
     */
    public function getTotal($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->total, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the discount amount formated from all items in the cart.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  float
     */
    public function getDiscountAmount($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->discountAmount, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the shipping amount formated.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  float
     */
    public function getShippingAmount($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->shippingAmount, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the number of items in the cart
     *
     * @return float
     */
    public function getQuantity()
    {
        return $this->cartItems->reduce(function($quantity, $item){
            return $quantity += $item->quantity;
        }, 0);
    }

    /**
     * Get Collection with tax rules objects
     *
     * @return \Syscover\ShoppingCart\CartItemTaxRules
     */
    public function getTaxRules()
    {
        $taxRules = new CartItemTaxRules();

        foreach ($this->cartItems as $cartItem)
        {
            foreach ($cartItem->taxRules as $taxRule)
            {
                if($taxRules->has($taxRule->id))
                {
                    // if find any tax with the same ID, sum yours rates
                    $taxRules->get($taxRule->id)->taxAmount += $taxRule->taxAmount;
                }
                else
                {
                    // add new tax rule, clone object because otherwise object save reference with taxRule from carItem
                    // everytime that we change taxAmount it would be changed in cartItem
                    $taxRules->put($taxRule->id, clone $taxRule);
                }
            }
        }

        return $taxRules;
    }

    /**
     * Get Array with price rules objects
     *
     * @return \Syscover\ShoppingCart\CartPriceRules
     */
    public function getPriceRules()
    {
        return $this->cartPriceRules;
    }

    /**
     * Get shipping data
     *
     * @return array
     */
    public function getShipping()
    {
        return $this->shipping;
    }

    /**
     * Get billing data
     *
     * @return array
     */
    public function getBilling()
    {
        return $this->billing;
    }


    //*****************
    // Setters
    //*****************

    /**
     * Magic method to set private attributes
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        if($key === 'shippingAmount')
        {
            $this->shippingAmount = $value;
        }
    }

    /**
     * Set the number of items of a item cart
     *
     * @param int|string    $rowId
     * @param int|float     $quantity
     */
    public function setQuantity($rowId, $quantity)
    {
        $this->cartItems->get($rowId)->setQuantity($quantity);

        // if quantity is less than zere, remove item
        if ($this->cartItems->get($rowId)->quantity <= 0)
        {
            $this->remove($rowId);
        }

        // update discounts on priceRules collection
        $this->updateCartPercentageDiscounts();
    }

    /**
     * Set shipping
     *
     * @return array
     */
    public function setShipping($shipping)
    {
        $this->shipping = $shipping;
    }

    /**
     * Set billing
     *
     * @return array
     */
    public function setBilling($billing)
    {
        $this->billing = $billing;
    }


    //*****************
    // Methods
    //*****************

    /**
     * Set cart instance in session
     *
     * @return void
     */
    private function storeCartInstance()
    {
        session()->put($this->instance, $this);
    }

    /**
     * Destroy cart instance
     *
     * @return boolean
     */
    public function destroy()
    {
        // fire the cart.destroy event
        event('cart.destroy');

        $response = session()->remove($this->instance);

        // fire the cart.destroyed event
        event('cart.destroyed');

        return $response;
    }

    /**
     * @param   array|\Syscover\ShoppingCart\CartItem   $cartItem
     * @return  array|CartItem
     */
    public function add($cartItem)
    {
        // If it is a array, we call recursively the add function
        if(is_array($cartItem))
        {
            return array_map(function ($item) {
                event('cart.batch', $item);
                return $this->add($item);
            }, $cartItem);
        }

        // increment quantity if exist a product with de same rowId
        if($this->cartItems->has($cartItem->rowId))
        {
            $this->cartItems->get($cartItem->rowId)->setQuantity($cartItem->quantity + $this->cartItems->get($cartItem->rowId)->quantity);
        }
        else
        {
            // add cartItem
            $this->cartItems->put($cartItem->rowId, $cartItem);

            // apply cart rules to new cartItem
            $this->applyCartPricesRulesWithPercentageDiscountsToCartItem($cartItem->rowId);
        }

        // update discounts on priceRules collection
        $this->updateCartPercentageDiscounts();

        $this->checkHasShipping();

        event('cart.added', $cartItem);

        if(! session()->has($this->instance))
            $this->storeCartInstance();

        return $cartItem;
    }

    /**
     * Update the quantity of one row of the cart
     *
     * @param  string                           $rowId      The rowid of the Item object you want to update
     * @param  \Syscover\ShoppingCart\Item      $item       New Item object
     * @return void
     */
    public function update($rowId, Item $item)
    {
        // delete object with all data to add new object later
        $this->cartItems->pull($rowId);
        $this->cartItems->put($item->rowId, $item);

        // update discounts on priceRules collection
        $this->updateCartPercentageDiscounts();

        $this->checkHasShipping();
    }

    /**
     * Remove a row, if cart is empty after remove row it will be destroyed
     *
     * @param   $rowId
     * @return  \Syscover\ShoppingCart\CartItems
     */
    public function remove($rowId)
    {
        $cartItem = $this->cartItems->get($rowId);

        // fire the cart.remove event
        event('cart.remove', $cartItem);

        $this->cartItems->forget($rowId);

        // fire the cart.removed event
        event('cart.removed', $cartItem);

        // destroy all properties of cart, if is empty
        if($this->cartItems->count() === 0)
        {
            $this->destroy();
        }
        else
        {
            // update discounts on priceRules collection
            $this->updateCartPercentageDiscounts();

            $this->checkHasShipping();
        }
    }

    /**
     * Check if cart has products to shipping
     *
     * @return boolean | void
     */
    public function hasShipping()
    {
        return $this->hasShipping;
    }

    /**
     * Search inside carItems a cartItem, matching the given search closure.
     *
     * @param   \Closure $search
     * @return  \Illuminate\Support\Collection
     */
    public function search(Closure $search)
    {
        return $this->cartItems->filter($search);
    }

    /**
     * Add CartPriceRule to collection CartPriceRuleCollection
     *
     * @param   PriceRule $priceRule
     * @throws  ShoppingCartNotCombinablePriceRuleException
     * @return void
     */
    public function addCartPriceRule(PriceRule $priceRule)
    {
        // check if id cart price rule exist
        if($this->cartPriceRules->has($priceRule->id))
        {
            throw new \InvalidArgumentException('This coupon already exist in cart prices rules.');
        }
        else
        {
            if($this->hasCartPriceRuleNotCombinable)
                throw new ShoppingCartNotCombinablePriceRuleException('You can\'t apply price rule, you have a not combinable price rule in shopping cart.');

            if($priceRule->discountType === PriceRule::DISCOUNT_SUBTOTAL_PERCENTAGE && $this->cartPriceRules->where('discountType', PriceRule::DISCOUNT_TOTAL_PERCENTAGE)->count() > 0)
                throw new \InvalidArgumentException('You can\'t apply discount over subtotal, when you already have discounts over total.');

            if($priceRule->discountType === PriceRule::DISCOUNT_TOTAL_PERCENTAGE && $this->cartPriceRules->where('discountType', PriceRule::DISCOUNT_SUBTOTAL_PERCENTAGE)->count() > 0)
                throw new \InvalidArgumentException('You can\'t apply discount over total, when you already have discounts over subtotal.');

            // add object to cart price rules
            $this->cartPriceRules->put($priceRule->id, $priceRule);

            $this->applyCartPriceRuleToCartItems($priceRule);
            $this->updateCartPercentageDiscounts();
        }
    }

    /**
     * Implement PriceRule in all cartItems
     *
     * @param   \Syscover\ShoppingCart\PriceRule    $priceRule
     * @return  void
     */
    private function applyCartPriceRuleToCartItems(PriceRule $priceRule)
    {
        // discount by percentage over subtotal
        if($priceRule->discountType == PriceRule::DISCOUNT_SUBTOTAL_PERCENTAGE)
        {
            $this->cartItems->transform(function ($item, $key) use ($priceRule) {
                // add discount percentage to item discount subtotal percentage
                return $item->setDiscountSubtotalPercentage(
                    clone $priceRule->discount // add discount object to cart item, clone discount object to loose reference to same object
                );
            });
        }

        // discount by percentage over total
        if($priceRule->discountType == PriceRule::DISCOUNT_TOTAL_PERCENTAGE)
        {
            $this->cartItems->transform(function ($item, $key) use ($priceRule) {
                // add discount percentage to item discount total percentage
                return $item->setDiscountTotalPercentage(
                    clone $priceRule->discount // add discount object to cart item, clone discount object to loose reference to same object
                );
            });
        }

        // set fixed discounts over subtotal
        if($priceRule->discountType == PriceRule::DISCOUNT_SUBTOTAL_FIXED_AMOUNT)
        {
            // sorts cartItems from highest to lowest tax rate value and sorts lowest to highest subtotal
            $cartItems = $this->cartItems->sortByDesc(function ($cartItem, $key) {
                return $cartItem->taxRules->sum('taxRate');
            })->groupBy(function($cartItem, $key) {
                return strval($cartItem->taxRules->sum('taxRate'));
            })->map(function($cartItems, $key){
                return $cartItems->sortBy('subtotal');
            });

            // get discount amount to discount
            $discountAmount = $priceRule->discount->fixed;

            // we go over cartItems discount fixed amount
            foreach ($cartItems as $cartItemsGroup)
            {
                foreach ($cartItemsGroup as $cartItem)
                {
                    if($cartItem->subtotalWithDiscounts - $discountAmount >= 0)
                    {
                        // set amount to discount is less or equal than subtotal
                        $priceRule->discount->amount = $discountAmount;

                        $cartItem->setDiscountSubtotalFixed(
                            clone $priceRule->discount // add discount to cart item
                        );
                        $discountAmount = 0;
                        break;
                    }
                    else
                    {
                        // amount to discount is highest than subtotal
                        $discountAmount -= $cartItem->subtotalWithDiscounts;
                        // set amount to discount is less or equal than subtotal
                        $priceRule->discount->amount = $discountAmount;

                        $cartItem->setDiscountSubtotalFixed(
                            clone $priceRule->discount // add discount to cart item
                        );
                    }
                }
                if($discountAmount == 0)
                    break;
            }
            // this variable is instance in session
            $priceRule->discountAmount = $priceRule->discount->fixed - $discountAmount;
        }

        // set fixed discounts over total
        if($priceRule->discountType == PriceRule::DISCOUNT_TOTAL_FIXED_AMOUNT)
        {
            // sorts cartItems from highest to lowest tax rate value and sorts lowest to highest total
            $cartItems = $this->cartItems->sortByDesc(function ($cartItem, $key) {
                return $cartItem->taxRules->sum('taxRate');
            })->groupBy(function($cartItem, $key) {
                return strval($cartItem->taxRules->sum('taxRate'));
            })->map(function($cartItems, $key){
                return $cartItems->sortBy('total');
            });

            // get discount amount to discount
            $discountAmount = $priceRule->discount->fixed;

            // we go over cartItems discount fixed amount
            foreach ($cartItems as $cartItemsGroup)
            {
                foreach ($cartItemsGroup as $cartItem)
                {
                    if($cartItem->total - $discountAmount >= 0)
                    {
                        // set amount to discount is less or equal than total
                        $priceRule->discount->amount = $discountAmount;

                        // amount to discount is less or equal than total
                        $cartItem->setDiscountTotalFixed(
                            clone $priceRule->discount // add discount to cart item
                        );
                        $discountAmount = 0;
                        break;
                    }
                    else
                    {
                        // amount to discount is highest than subtotal
                        $discountAmount -= $cartItem->total;
                        // set amount to discount is less or equal than total
                        $priceRule->discount->amount = $discountAmount;

                        $cartItem->setDiscountTotalFixed(
                            clone $priceRule->discount
                        );
                    }
                }
                if($discountAmount == 0)
                    break;
            }
            // this variable is instance in session
            $priceRule->discountAmount = $priceRule->discount->fixed - $discountAmount;
        }
    }

    /**
     * Implement all percentages PriceRules in one cartItem.
     * This method is used when add new carItem to existing cartItemCollection
     *
     * @return void
     */
    private function applyCartPricesRulesWithPercentageDiscountsToCartItem($rowId)
    {
        foreach($this->cartPriceRules as $cartPriceRule)
        {
            // discount subtotal percentage
            if($cartPriceRule->discountType == PriceRule::DISCOUNT_SUBTOTAL_PERCENTAGE)
            {
                $this->cartItems->get($rowId)->setDiscountSubtotalPercentage($cartPriceRule->discount);
            }

            // discount subtotal percentage
            if($cartPriceRule->discountType == PriceRule::DISCOUNT_TOTAL_PERCENTAGE)
            {
                $this->cartItems->get($rowId)->setDiscountTotalPercentage($cartPriceRule->discount);
            }
        }
    }

    /**
     * Update and create discount amounts, inside all cartPriceRules
     * This function set all data about rules, is called with every change
     *
     * @return void
     */
    private function updateCartPercentageDiscounts()
    {
        // reset properties shopping cart
        $this->hasCartPriceRuleNotCombinable = false;
        $this->hasFreeShipping = false;

        // calculate for each cart price rule, amount to discount
        foreach($this->cartPriceRules as &$cartPriceRule)
        {
            // discount percentage over subtotal
            if($cartPriceRule->discountType == PriceRule::DISCOUNT_SUBTOTAL_PERCENTAGE)
            {
                // set discount amount to price rule for each cart item
                $cartPriceRule->discountAmount = $this->cartItems->reduce(function ($discountAmount, Item $cartItem) use ($cartPriceRule){
                    return $discountAmount + $cartItem->discountSubtotalPercentage->where('id', $cartPriceRule->id)->sum('amount');
                }, 0);
            }

            // discount percentage over total
            if($cartPriceRule->discountType == PriceRule::DISCOUNT_TOTAL_PERCENTAGE)
            {
                // set discount amount to price rule for each cart item
                $cartPriceRule->discountAmount = $this->cartItems->reduce(function ($discountAmount, Item $cartItem) use ($cartPriceRule){
                    return $discountAmount + $cartItem->discountTotalPercentage->where('id', $cartPriceRule->id)->sum('amount');
                }, 0);
            }

            // check if price rule has not combinable
            if(! $cartPriceRule->combinable)
                $this->hasCartPriceRuleNotCombinable = true;

            // check if price rule has free shipping
            if($cartPriceRule->freeShipping)
                $this->hasFreeShipping = true;
        }
    }

    /**
     * Check if cart contain any transportable item
     *
     * @return void
     */
    private function checkHasShipping()
    {
        foreach($this->cartItems as $item)
        {
            if($item->transportable === true)
            {
                $this->hasShipping = true;
                break;
            }
        }
    }
}