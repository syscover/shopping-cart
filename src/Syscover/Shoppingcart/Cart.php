<?php namespace Syscover\ShoppingCart;

use Closure;

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
     * check if cart has free shipping
     *
     * @var boolean
     */
    protected $hasFreeShipping;

    /**
     * check if cart has shipping products
     *
     * @var boolean
     */
    protected $hasShipping;

	/**
	 * shipping amount
	 *
	 * @var double
	 */
	protected $shippingAmount;


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



		//$this->freeShipping					= false;
		//$this->shippingAmount				    = 0;
		//$this->shipping						= false;
	}

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
     * Get the cart items
     *
     * @return \Syscover\ShoppingCart\CartItems
     */
    public function getCartItems()
    {
        return $this->cartItems;
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
            $this->cartItems->get($cartItem->rowId)->setQuantity($cartItem->getQuantity() + $this->cartItems->get($cartItem->rowId)->getQuantity());
        }
        else
        {
            // add cartItem
            $this->cartItems->put($cartItem->rowId, $cartItem);

            // recalculate all rules
            $this->applyCartPricesRulesToCartItem($cartItem->rowId);
            $this->updateCartDiscounts();
        }

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
    }

    /**
     * Remove a row, if cart is empty after remove row it will be destroyed
     *
     * @param   $rowId
     * @return  \Syscover\ShoppingCart\CartItems
     */
    public function remove($rowId)
    {
        $cartItem   = $this->cartItems->get($rowId);

        // fire the cart.remove event
        event('cart.remove', $cartItem);

        $this->cartItems->forget($rowId);

        // fire the cart.removed event
        event('cart.removed', $cartItem);

        // destroy all properties of cart
        if($this->cartItems->count() === 0)
        {
            $this->destroy();
        }
    }

    /**
     * magic method to make accessing the total, tax and subtotal properties
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
        if($attribute === 'discountAmount')
        {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($discountAmount, Item $item) {
                return $discountAmount + $item->discountAmount;
            }, 0);
        }

        return null;
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
     * Get the taxAmount formated of the items in the cart.
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
     * Get the discount formated of the cart price rules.
     *
     * @param   int     $decimals
     * @param   string  $decimalPoint
     * @param   string  $thousandSeperator
     * @return  float
     */
    public function getDiscount($decimals = 2, $decimalPoint = ',', $thousandSeperator = '.')
    {
        return number_format($this->discount, $decimals, $decimalPoint, $thousandSeperator);
    }

    /**
     * Get the number of items in the cart
     *
     * @return float
     */
    public function getQuantity()
    {
        return $this->cartItems->reduce(function($quantity, $item){
            return $quantity += $item->getQuantity();
        }, 0);
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

        $this->updateCartDiscounts();

        // if quantity is less than zere, remove item
        if ($this->cartItems->get($rowId)->getQuantity() <= 0)
        {
            $this->remove($rowId);
        }
    }

    /**
     * Get Array with tax rules objects
     *
     * @return \Syscover\ShoppingCart\CartItemTaxRules
     */
    public function getTaxRules()
    {
        $cartItems  = $this->cartItems;
        $taxRules   = new CartItemTaxRules();

        foreach ($cartItems as $cartItem)
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
                    // add new tax rule
                    $taxRules->put($taxRule->id, $taxRule);
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
     * @param  \Syscover\ShoppingCart\PriceRule  $priceRule
     * @return void
     */
    public function addCartPriceRule(PriceRule $priceRule)
    {
        // check if id cart price rule exist
        if($this->cartPriceRules->has($priceRule->id))
        {
            // error, este descuento ya existe en el carro
            dd('cupon ya existe');
        }
        else
        {
            // add object to cart price rules
            $this->cartPriceRules->put($priceRule->id, $priceRule);

            $this->applyCartPriceRuleToCartItems($priceRule);
            $this->updateCartDiscounts();
        }
    }

    /**
     * update and create discount amounts, inside all cartPriceRules
     * This function set all data about rules, is called with every change
     *
     * @return void
     */
    private function updateCartDiscounts()
    {
        // reset discounts cart paramenters
        $this->hasCartPriceRuleNotCombinable    = false;
        $this->hasFreeShipping                  = false;

        foreach($this->cartPriceRules as &$cartPriceRule)
        {
            // discount by percentage
            if($cartPriceRule->discountType == PriceRule::DISCOUNT_PERCENTAGE_SUBTOTAL)
            {
                // check if discount is with shipping amount
                if($cartPriceRule->applyShippingAmount && $this->hasShipping && ! $this->hasFreeShipping)
                {
                    $discountAmount = (($this->subtotal + $this->shippingAmount) * $cartPriceRule->discountPercentage) / 100;
                }
                else
                {
                    $discountAmount = ($this->subtotal * $cartPriceRule->discountPercentage) / 100;
                }




                // check if discount is lower that maximum discount allowed
                if($cartPriceRule->maximumDiscountAmount != null && $discountAmount > $cartPriceRule->maximumDiscountAmount)
                {
                    $discountAmount = $cartPriceRule->maximumDiscountAmount;
                }




                $cartPriceRule->discountAmount = $discountAmount;
            }

            // discount by fixed amount
            if($cartPriceRule->discountType == PriceRule::DISCOUNT_FIXED_AMOUNT_SUBTOTAL)
            {
                $cartPriceRule->discountAmount = $cartPriceRule->discountFixed;
            }

            // check if price rule is combinable
            if(! $cartPriceRule->combinable)
                $this->hasCartPriceRuleNotCombinable = true;

            // check if price rule has free shipping
            if($cartPriceRule->freeShipping)
                $this->hasFreeShipping = true;
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
        // discount by percentage
        if($priceRule->discountType == PriceRule::DISCOUNT_PERCENTAGE_SUBTOTAL)
        {
            $this->cartItems->transform(function ($item, $key) use ($priceRule) {
                // to set discount percentage, we calculate all amounts too
                return $item->setDiscountPercentage($priceRule->discountPercentage + $item->getDiscountPercentage());
            });
        }
    }

    /**
     * Implement all PriceRules in one cartItem
     *
     * @return void
     */
    private function applyCartPricesRulesToCartItem($rowId)
    {
        foreach($this->cartPriceRules as $cartPriceRule)
        {
            // discount by percentage
            if($cartPriceRule->discountType == PriceRule::DISCOUNT_PERCENTAGE_SUBTOTAL)
            {
                $this->cartItems->get($rowId)->setDiscountPercentage($cartPriceRule->discountPercentage);
            }
        }
    }












    ////////////////////////////////////////////////////

//	/**
//	 * Get the price total, include shipping amount
//	 *
//	 * @return float
//	 */
//	public function total()
//	{
//		$total 			= 0;
//		$cartItems = $this->cartItems;
//
//		if(empty($cartItems))
//		{
//			return $total;
//		}
//
//		foreach($cartItems as $row)
//		{
//			$total += $row->subtotal;
//		}
//
//		// check that, don't have free shipping
//		if( ! $this->hasFreeShipping())
//		{
//			// sum shipping amount
//			$total += $this->getShippingAmount();
//		}
//
//		$total -= $this->discount();
//
//		return $total;
//	}
//
//
//	/**
//	 * return shipping amount
//	 *
//	 * @return integer
//	 */
//	public function getShippingAmount()
//	{
//		if(isset($this->shippingAmount))
//			return $this->shippingAmount;
//		else
//			return 0;
//	}
//
//	/**
//	 * set shipping amount
//	 *
//	 * @return void
//	 */
//	public function setShippingAmount($shippingAmount)
//	{
//		$this->shippingAmount = $shippingAmount;
//		$this->storeCartInstance();
//	}
//
//	/**
//	 * check if cart has products to shipping
//	 *
//	 * @return boolean | void
//	 */
//	public function hasShipping()
//	{
//		return $this->shipping;
//	}
//
//	/**
//	 * set cart has products to shipping
//	 *
//	 * @param  boolean		$shipping
//	 * @throws ShoppingcartInvalidDataTypeException
//	 */
//	public function setShipping($shipping)
//	{
//		if(is_bool($shipping))
//		{
//			$this->shipping = $shipping;
//			$this->storeCartInstance();
//		}
//		else
//		{
//			throw new ShoppingcartInvalidDataTypeException;
//		}
//	}
//
//
//	/**
//	 * get rule not combinable from cart, there can only be one
//	 *
//	 * @return mixed|null
//	 */
//	public function getCartPriceRuleNotCombinable()
//	{
//		$cartPriceRulesContent = $this->getCartPriceRuleCollection();
//
//		foreach($cartPriceRulesContent as $cartPriceRule)
//		{
//			if($cartPriceRule->combinable_120 == false)
//			{
//				return $cartPriceRule;
//			}
//		}
//		return null;
//	}
}