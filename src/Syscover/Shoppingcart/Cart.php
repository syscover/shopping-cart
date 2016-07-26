<?php namespace Syscover\Shoppingcart;

use Closure;
use Syscover\Shoppingcart\Exceptions\InvalidRowIDException;

class Cart 
{
    const PRICE_WITHOUT_TAX = '1';
    const PRICE_WITH_TAX    = '2';

	/**
	 * Current cart instance
	 *
	 * @var string
	 */
	protected $instance;

	/**
	 * cart content items
	 *
	 * @var \Syscover\Shoppingcart\CartItems
	 */
	protected $cartItems;

	/**
	 * all discounts applied to cart
	 *
	 * @var \Syscover\Shoppingcart\CartPriceRules
	 */
	protected $cartPriceRules;

	/**
	 * shipping amount
	 *
	 * @var double
	 */
	protected $shippingAmount;

	/**
	 * check if cart has shipping products
	 *
	 * @var boolean
	 */
	protected $shipping;

	/**
	 * check if cart has free shipping
	 *
	 * @var boolean
	 */
	protected $freeShipping;

	/**
	 * check if inside $cartPriceRulesContent have a not combinable rule.
	 *
	 * @var boolean
	 */
	protected $cartPriceRuleNotCombinable;


	/**
	 * Cart constructor.
     *
	 * @param   string  $instance
	 */
	public function __construct($instance)
	{
		$this->instance 					= $instance;
		$this->cartItems 			        = new CartItems();
		$this->cartPriceRulesContent 		= new CartPriceRules();



		$this->freeShipping					= false;
		$this->shippingAmount				= 0;
		$this->shipping						= false;
		$this->cartPriceRuleNotCombinable	= false;
	}

    /**
     * Set cart instance in session
     *
     * @return void
     */
    private function setCart()
    {
        // before set session, update amounts from cartPriceRulesContent
        $this->updateAmountsCartPriceRuleCollection();

        session()->put($this->instance, $this);
    }

    /**
     * set CartItems Collection and instance in session Cart object
     *
     * @param  \Syscover\Shoppingcart\CartItems  $cartItems  The new cart content
     * @return \Syscover\Shoppingcart\CartItems
     */
    protected function setCartItems($cartItems)
    {
        $this->cartItems = $cartItems;

        // save current changes
        $this->setCart();

        return $this->cartItems;
    }

    /**
     * Get the cart items
     *
     * @return \Syscover\Shoppingcart\CartItems
     */
    public function content()
    {
        return (empty($this->cartItems)) ? null : $this->cartItems;
    }

    /**
     * Add a row to the cart
     *
     * @param $mixed        $mixed          Unique ID of the item|Item formated as array|Array of items
     * @param string 	    $name           Name of the item
     * @param int|float    	$qty            Item qty to add to the cart
     * @param float  	    $price          Price of one item
     * @param boolean  	    $transportable  Set if product has to be transported
     * @param float  	    $weight         Weight of one item
     * @param array  	    $options        Array of additional options, such as 'size' or 'color
     * @param array  	    $taxRules       Array that content every rules to calculate tax
     *
     * @return \Syscover\Shoppingcart\Libraries\CartCollection|void
     */
    public function add($mixed, $name = null, $qty = null, $price = null, $transportable = null, $weight = null, array $options = [], array $taxRules = [])
    {

        // If it is a multidimensional array, we  call recursively the add function
        if ($this->isMulti($mixed))
        {
            return array_map(function ($item) {
                event('cart.batch', $item);
                return $this->add($item);
            }, $mixed);
        }

        $cartItem = $this->createCartItem($mixed, $name, $qty, $price, $transportable, $weight, $options, $taxRules);

        $cartItems = $this->cartItems;

        // increment quantity if exist a product with de same rowId
        if($cartItems->has($cartItem->rowId))
        {
            $cartItem->setQuantity($cartItem->getQuantity() + $cartItems->get($cartItem->rowId)->getQuantity());
        }

        $cartItems->put($cartItem->rowId, $cartItem);

        event('cart.added', $cartItem);

        $this->setCartItems($cartItems);

        return $cartItem;
    }

    /**
     * Get the number of items in the cart
     *
     * @return int
     */
    public function count()
    {
        return $this->cartItems->reduce(function($nProducts, $item){
            return $nProducts += $item->getQuantity();
        }, 0);
    }

    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param   string $attribute
     * @return  float|null
     */
    public function __get($attribute)
    {
        if($attribute === 'total') {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($total, CartItem $cartItem) {
                return $total + $cartItem->total;
            }, 0);
        }
        if($attribute === 'taxAmount') {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($taxAmount, CartItem $cartItem) {
                return $taxAmount + $cartItem->taxAmount;
            }, 0);
        }
        if($attribute === 'subtotal')
        {
            $cartItems = $this->cartItems;
            return $cartItems->reduce(function ($subTotal, CartItem $cartItem) {
                return $subTotal + $cartItem->subtotal;
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
     * Get Array with tax rules objects
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTaxRules()
    {
        $cartItems              = $this->cartItems;
        $taxRulesShoppingCart   = collect();
        foreach ($cartItems as $cartItem)
        {
           foreach ($cartItem->taxRules as $taxRule)
           {
               if($taxRulesShoppingCart->has($taxRule->id))
               {
                   // if find any tax with the same ID, sum yours rates
                   $taxRulesShoppingCart->get($taxRule->id)->taxAmount += $taxRule->taxAmount;
               }
               else
               {
                   // add new tax rule
                   $taxRulesShoppingCart->put($taxRule->id, $taxRule);
               }
           }
        }
        return $taxRulesShoppingCart;
    }

    /**
     * Get a row of the cart by ID
     *
     * @param   string  $rowId  The ID of the row to fetch
     * @return  \Syscover\Shoppingcart\CartItem
     * @throws  InvalidRowIDException
     */
    public function get($rowId)
    {
        if(! $this->cartItems->has($rowId))
            throw new InvalidRowIDException("The cart does not contain rowId {$rowId}.");

        return $this->cartItems->get($rowId);
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param \Closure $search
     * @return \Illuminate\Support\Collection
     */
    public function search(Closure $search)
    {
        $content = $this->content();
        return $content->filter($search);
    }

    /**
     * Remove a row, if cart is empty after remove row it will be destroyed
     *
     * @param   $rowId
     * @return  \Syscover\Shoppingcart\CartItems
     */
    public function remove($rowId)
    {
        $cartItem   = $this->get($rowId);
        $cartItems  = $this->content();

        // Fire the cart.remove event
        event('cart.remove', $cartItem);

        $cartItems->forget($rowId);

        // destroy all properties of cart
        if($cartItems->count() === 0)
            $this->destroy();

        // Fire the cart.removed event
        event('cart.removed', $cartItem);

        return $this->setCartItems($cartItems);
    }

	/**
	 * Destroy cart instance
	 *
	 * @return boolean
	 */
	public function destroy()
	{
		// Fire the cart.destroy event
		event('cart.destroy');

		$response = session()->remove($this->instance);

		// Fire the cart.destroyed event
		event('cart.destroyed');

		return $response;
	}

    /**
     * Create a new CartItem from the supplied attributes.
     *
     * @param mixed     $id
     * @param mixed     $name
     * @param int|float $qty
     * @param float     $price
     * @param boolean   $transportable
     * @param float     $weight
     * @param array     $options
     * @param array  	$taxRules
     * @return \Syscover\Shoppingcart\CartItem
     */
    private function createCartItem($id, $name, $qty, $price, $transportable, $weight, array $options, array $taxRules)
    {
        if (is_array($id))
        {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($id['qty']);
        }
        else
        {
            $cartItem = CartItem::fromAttributes($id, $name, $price, $transportable, $weight, $options, $taxRules);
            $cartItem->setQuantity($qty);
        }

        return $cartItem;
    }

    /**
     * Update the quantity of one row of the cart
     *
     * @param  string           $rowId       The rowid of the item you want to update
     * @param  mixed            $mixed      New quantity of the item | Array of attributes to update
     * @return void
     */
    public function update($rowId, $mixed)
    {
        $cartItem = $this->get($rowId);

        $cartItems = $this->cartItems;

        if (is_array($mixed))
        {
            $cartItem->update($mixed);
        }
        else
        {
            $cartItem->setQuantity($mixed);
        }

        // if after update from array, change rowId
        if ($rowId !== $cartItem->rowId)
        {
            // delete object with all data to add new object later
            $cartItems->pull($rowId);

            // if, there is other car item, add new quantity to existing car item
            if ($cartItems->has($cartItem->rowId))
            {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->getQuantity() + $cartItem->getQuantity());
            }
        }


        if ($cartItem->getQuantity() <= 0)
        {
            $this->remove($cartItem->rowId);
            return;
        }
        else
        {
            // add new car iten to content
            $cartItems->put($cartItem->rowId, $cartItem);
        }
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
	 * @return float
	 */
	public function discount()
	{
		$cartPriceRulesContent 	= $this->getCartPriceRuleCollection();
		$discountAmount			= 0;

		foreach($cartPriceRulesContent as $cartPriceRule)
			$discountAmount +=  $cartPriceRule->discount_amount;

		return $discountAmount;
	}

	/**
	 * Get the price total, include shipping amount
	 *
	 * @return float
	 */
	public function total()
	{
		$total 			= 0;
		$cartItems = $this->cartItems;

		if(empty($cartItems))
		{
			return $total;
		}

		foreach($cartItems as $row)
		{
			$total += $row->subtotal;
		}

		// check that, don't have free shipping
		if( ! $this->hasFreeShipping())
		{
			// sum shipping amount
			$total += $this->getShippingAmount();
		}

		$total -= $this->discount();

		return $total;
	}













	/**
	 * Check if a rowid exists in the current cart instance
	 *
	 * @param  string  $rowId  Unique ID of the item
	 * @return boolean
	 */
	protected function hasRowId($rowId)
	{
		return $this->cartItems->has($rowId);
	}

	/**
	 * Update a row if the rowId already exists
	 *
	 * @param  string   $rowId  The ID of the row to update
	 * @param  array  	$attributes    The quantity and price to add to the row
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function updateRow($rowId, $attributes)
	{
		$cartItems = $this->cartItems;

		$row = $cartItems->get($rowId);

		foreach($attributes as $key => $value)
		{
			if($key == 'options')
			{
				$options = $row->options->merge($value);
				$row->put($key, $options);
			}
			else
			{
				$row->put($key, $value);
			}
		}

		if( ! is_null(array_keys($attributes, ['qty', 'price'])))
		{
			$row->put('subtotal', $row->qty * $row->price);
		}

		$cartItems->put($rowId, $row);

		return $cartItems;
	}





	/**
	 * Update an attribute of the row
	 *
	 * @param  string  $rowId       The ID of the row
	 * @param  array   $attributes  An array of attributes to update
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function updateAttribute($rowId, $attributes)
	{
		return $this->updateRow($rowId, $attributes);
	}



	/**
	 * return shipping amount
	 *
	 * @return integer
	 */
	public function getShippingAmount()
	{
		if(isset($this->shippingAmount))
			return $this->shippingAmount;
		else
			return 0;
	}

	/**
	 * set shipping amount
	 *
	 * @return void
	 */
	public function setShippingAmount($shippingAmount)
	{
		$this->shippingAmount = $shippingAmount;
		$this->setCart();
	}

	/**
	 * check if cart has products to shipping
	 *
	 * @return boolean | void
	 */
	public function hasShipping()
	{
		return $this->shipping;
	}

	/**
	 * set cart has products to shipping
	 *
	 * @param  boolean		$shipping
	 * @throws ShoppingcartInvalidDataTypeException
	 */
	public function setShipping($shipping)
	{
		if(is_bool($shipping))
		{
			$this->shipping = $shipping;
			$this->setCart();
		}
		else
		{
			throw new ShoppingcartInvalidDataTypeException;
		}
	}

	/**
	 * Generate a unique id for the new cartPriceRule
	 *
	 * @param  \Syscover\Market\Models\CartPriceRule   	$cartPriceRule
	 * @return string
	 */
	protected function generateCartPriceRuleId($cartPriceRule)
	{
		return md5($cartPriceRule->id_120 . serialize($cartPriceRule));
	}

	/**
	 * Get the DiscountCollection
	 *
	 * @return \Syscover\Shoppingcart\Libraries\CartPriceRuleCollection
	 */
	public function getCartPriceRuleCollection()
	{
		return $this->cartPriceRulesContent;
	}

	/**
	 * count cartPriceRulesContent
	 *
	 * @return integer
	 */
	public function countCartPriceRuleCollection()
	{
		return $this->cartPriceRulesContent->count();
	}

	/**
	 * get if cart has free shipping
	 *
	 * @return boolean
	 */
	public function hasFreeShipping()
	{
		return $this->freeShipping;
	}

	/**
	 * set free shipping property
	 *
	 * @param 	boolean 	$freeShipping
	 * @throws 	ShoppingcartInvalidDataTypeException
	 */
	public function setFreeShipping($freeShipping)
	{
		if(is_bool($freeShipping))
		{
			$this->freeShipping = $freeShipping;
		}
		else
		{
			throw new ShoppingcartInvalidDataTypeException;
		}
	}

	/**
	 * get if cart has any rule not combinable
	 *
	 * @return boolean
	 */
	public function hasCartPriceRuleNotCombinable()
	{
		return $this->cartPriceRuleNotCombinable;
	}

	/**
	 * get rule not combinable from cart, there can only be one
	 *
	 * @return mixed|null
	 */
	public function getCartPriceRuleNotCombinable()
	{
		$cartPriceRulesContent = $this->getCartPriceRuleCollection();

		foreach($cartPriceRulesContent as $cartPriceRule)
		{
			if($cartPriceRule->combinable_120 == false)
			{
				return $cartPriceRule;
			}
		}
		return null;
	}

	/**
	 * set cart any rule not combinable
	 *
	 * @param 	boolean	$cartPriceRuleNotCombinable
	 * @throws 	ShoppingcartInvalidDataTypeException
	 */
	public function setCartPriceRuleNotCombinable($cartPriceRuleNotCombinable)
	{
		if(is_bool($cartPriceRuleNotCombinable))
		{
			$this->cartPriceRuleNotCombinable = $cartPriceRuleNotCombinable;
		}
		else
		{
			throw new ShoppingcartInvalidDataTypeException;
		}
	}


    /**
     * Set the DiscountCollection
     *
     * @param \Syscover\Shoppingcart\Libraries\CartPriceRuleCollection $cartPriceRulesContent
     * @return \Syscover\Shoppingcart\Libraries\CartPriceRuleCollection
     */
	protected function setCartPriceRuleCollection($cartPriceRulesContent)
	{
		$this->cartPriceRulesContent = $cartPriceRulesContent;

		// save current changes
		$this->setCart();

		return $this->cartPriceRulesContent;
	}


	/**
	 * check if any cartPriceRule exist in CartPriceRuleCollection
	 *
	 * @param  \Syscover\Market\Models\CartPriceRule  $cartPriceRule
	 * @return boolean
	 */
	public function hasCartPriceRule($cartPriceRule)
	{
		$cartPriceRulesContent 	= $this->getCartPriceRuleCollection();
		$cartPriceRuleId 			= $this->generateCartPriceRuleId($cartPriceRule);

		// comprobamos que el id de descuento no existe en el carro
		if($cartPriceRulesContent->has($cartPriceRuleId))
		{
			return true;
		}
		return false;
	}

	/**
	 * add CartPriceRule to collection CartPriceRuleCollection
	 *
	 * @param  \Syscover\Market\Models\CartPriceRule  $cartPriceRule
	 * @return void
	 */
	public function addCartPriceRule($cartPriceRule)
	{
		// comprobamos que el id de descuento no existe en el carro
		if($this->hasCartPriceRule($cartPriceRule))
		{
			// error, este descuento ya existe en el carro
		}
		else
		{
			$cartPriceRulesContent 	= $this->getCartPriceRuleCollection();
			$cartPriceRuleId 			= $this->generateCartPriceRuleId($cartPriceRule);

			// add object to cart price collection
			$cartPriceRulesContent->put($cartPriceRuleId, $cartPriceRule);

			// save cartPriceRulesContent
			$this->setCartPriceRuleCollection($cartPriceRulesContent);
		}
	}

	/**
	 * Update and create all amounts, inside all cartPriceRules
	 * This function set all data about rules, is called with every change
	 */
	protected function updateAmountsCartPriceRuleCollection()
	{
		$cartPriceRulesContent = $this->getCartPriceRuleCollection();

		// in this step, add property discount_amount, inside cartPriceRule object
		foreach($cartPriceRulesContent as &$cartPriceRule)
		{
			// discount by percentage
			if($cartPriceRule->discount_type_id_120 == 2)
			{
				// check if discount is with shipping amount
				if($cartPriceRule->apply_shipping_amount_120)
				{
					$discountAmount = (($this->subtotal() + $this->getShipping()) * $cartPriceRule->discount_percentage_120) / 100;
				}
				else
				{
					$discountAmount = ($this->subtotal() * $cartPriceRule->discount_percentage_120) / 100;
				}

				// check if discount is lower that maximum discount allowed
				if($cartPriceRule->maximum_discount_amount_120 != null && $discountAmount > $cartPriceRule->maximum_discount_amount_120)
				{
					$discountAmount = $cartPriceRule->maximum_discount_amount_120;
				}

				$cartPriceRule->discount_amount = $discountAmount;
			}

			// discount by fixed amount
			if($cartPriceRule->discount_type_id_120 == 3)
			{
				$cartPriceRule->discount_amount = $cartPriceRule->discount_fixed_amount_120;
			}

			// check if there is any cartPriceRule with free shipping
			if($cartPriceRule->free_shipping_120)
			{
				$this->setFreeShipping(true);
			}

			// check if there is this rule is combinable
			if( ! $cartPriceRule->combinable_120)
			{
				$this->setCartPriceRuleNotCombinable(true);
			}
		}
	}
}