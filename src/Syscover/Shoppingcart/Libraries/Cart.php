<?php namespace Syscover\Shoppingcart\Libraries;

use Syscover\Shoppingcart\Exceptions\ShoppingcartUnknownModelException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidItemException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidPriceException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidRowIDException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidQtyException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidDataTypeException;

class Cart {

	/**
	 * Current cart instance
	 *
	 * @var string
	 */
	protected $instance;

	/**
	 * cart collection instance
	 *
	 * @var \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected $cartCollection;

	/**
	 * all discounts applied to cart
	 *
	 * @var \Syscover\Shoppingcart\Libraries\DiscountCollection
	 */
	protected $cartPriceRuleCollection;

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
	 * check if inside $cartPriceRuleCollection have ant not combinale rule
	 *
	 * @var boolean
	 */
	protected $cartPriceRuleNotCombinable;

	/**
	 * The Eloquent model a cart is associated with
	 *
	 * @var string
	 */
	protected $associatedModel;

	/**
	 * An optional namespace for the associated model
	 *
	 * @var string
	 */
	protected $associatedModelNamespace;

	/**
	 * Cart constructor.
	 * @param string $instance
	 */
	public function __construct($instance)
	{
		$this->instance 					= $instance;
		$this->cartCollection 				= new CartCollection;
		$this->cartPriceRuleCollection 		= new CartPriceRuleCollection;
		$this->freeShipping					= false;
		$this->shippingAmount				= 0;
		$this->shipping						= false;
		$this->cartPriceRuleNotCombinable	= false;
	}

	/**
	 * Empty the cart
	 *
	 * @return boolean
	 */
	public function destroy()
	{
		// Fire the cart.destroy event
		event('cart.destroy');

		$result = session()->put($this->instance, null);

		// Fire the cart.destroyed event
		event('cart.destroyed');

		return $result;
	}

	/**
	 * Get the CarCollection
	 *
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	private function setCart()
	{
		// before set session, update amounts from cartPriceRuleCollection
		$this->updateAmountsCartPriceRuleCollection();

		session()->put($this->instance, $this);
	}

	/**
	 * Get the CarCollection
	 *
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function getCartCollection()
	{
		return $this->cartCollection;
	}

	/**
	 * Update the CarCollection
	 *
	 * @param  \Syscover\Shoppingcart\Libraries\CartCollection  $cartCollection  The new cart content
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function setCartCollection($cartCollection)
	{
		$this->cartCollection = $cartCollection;

		// save current changes
		$this->setCart();

		return $this->getCartCollection();
	}

	/**
	 * Add a row to the cart
	 *
	 * @param string|array  $id       Unique ID of the item|Item formated as array|Array of items
	 * @param string 	    $name     Name of the item
	 * @param int    	    $qty      Item qty to add to the cart
	 * @param float  	    $price    Price of one item
	 * @param array  	    $options  Array of additional options, such as 'size' or 'color
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection|void
	 * @throws ShoppingcartInvalidItemException
	 * @throws ShoppingcartInvalidPriceException
	 * @throws ShoppingcartInvalidQtyException'
	 */
	public function add($id, $name = null, $qty = null, $price = null, array $options = [])
	{
		// If the first parameter is an array we need to call the add() function again
		if(is_array($id))
		{
			// And if it's not only an array, but a multidimensional array, we need to
			// recursively call the add function
			if($this->is_multi($id))
			{
				// Fire the cart.batch event
				event('cart.batch', $id);

				foreach($id as $item)
				{
					$options = array_get($item, 'options', []);
					$this->addRow($item['id'], $item['name'], $item['qty'], $item['price'], $options);
				}

				// Fire the cart.batched event
				event('cart.batched', $id);

				return;
			}

			$options = array_get($id, 'options', []);

			// Fire the cart.add event
			event('cart.add', array_merge($id, ['options' => $options]));

			$result = $this->addRow($id['id'], $id['name'], $id['qty'], $id['price'], $options);

			// Fire the cart.added event
			event('cart.added', array_merge($id, ['options' => $options]));

			return $result;
		}

		// Fire the cart.add event
		event('cart.add', compact('id', 'name', 'qty', 'price', 'options'));

		$result = $this->addRow($id, $name, $qty, $price, $options);

		// Fire the cart.added event
		event('cart.added', compact('id', 'name', 'qty', 'price', 'options'));

		return $result;
	}

	/**
	 * Add row to the cart
	 *
	 * @param string  $id       Unique ID of the item
	 * @param string  $name     Name of the item
	 * @param int     $qty      Item qty to add to the cart
	 * @param float   $price    Price of one item
	 * @param array   $options  Array of additional options, such as 'size' or 'color'
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 * @throws ShoppingcartInvalidItemException
	 * @throws ShoppingcartInvalidPriceException
	 * @throws ShoppingcartInvalidQtyException
	 */
	protected function addRow($id, $name, $qty, $price, array $options = [])
	{
		if(empty($id) || empty($name) || empty($qty) || ! isset($price))
		{
			throw new ShoppingcartInvalidItemException;
		}

		if( ! is_numeric($qty))
		{
			throw new ShoppingcartInvalidQtyException;
		}

		if( ! is_numeric($price))
		{
			throw new ShoppingcartInvalidPriceException;
		}

		$cartCollection 	= $this->getCartCollection();

		$rowId 				= $this->generateRowId($id, $options);

		if($cartCollection->has($rowId))
		{
			$row 			= $cartCollection->get($rowId);
			$cartCollection = $this->updateRow($rowId, ['qty' => $row->qty + $qty]);
		}
		else
		{
			$cartCollection = $this->createRow($rowId, $id, $name, $qty, $price, $options);
		}

		return $this->setCartCollection($cartCollection);
	}

	/**
	 * Update the quantity of one row of the cart
	 *
	 * @param  string         $rowId       The rowid of the item you want to update
	 * @param  integer|array  $attribute   New quantity of the item|Array of attributes to update
	 * @return boolean
	 * @throws ShoppingcartInvalidRowIDException
	 */
	public function update($rowId, $attribute)
	{
		if( ! $this->hasRowId($rowId)) throw new ShoppingcartInvalidRowIDException;

		if(is_array($attribute))
		{
			// Fire the cart.update event
			event('cart.update', $rowId);

			$result = $this->updateAttribute($rowId, $attribute);

			// Fire the cart.updated event
			event('cart.updated', $rowId);

			return $result;
		}

		// Fire the cart.update event
		event('cart.update', $rowId);

		$result = $this->updateQty($rowId, $attribute);

		// Fire the cart.updated event
		event('cart.updated', $rowId);

		return $result;
	}

	/**
	 * @param $rowId
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 * @throws ShoppingcartInvalidRowIDException
	 */
	public function remove($rowId)
	{
		if( ! $this->hasRowId($rowId)) throw new ShoppingcartInvalidRowIDException;

		$cartCollection = $this->getCartCollection();

		// Fire the cart.remove event
		event('cart.remove', $rowId);

		$cartCollection->forget($rowId);

		if($cartCollection->count() == 0)
			$this->resetCartProperties();

		// Fire the cart.removed event
		event('cart.removed', $rowId);

		return $this->setCartCollection($cartCollection);
	}

	/**
	 * reset cart properties when cart collection is empty
	 */
	private function resetCartProperties()
	{
		$this->cartPriceRuleCollection 		= new CartPriceRuleCollection;
		$this->freeShipping					= false;
		$this->shippingAmount				= 0;
		$this->shipping						= false;
		$this->cartPriceRuleNotCombinable	= false;
	}

	/**
	 * Get a row of the cart by its ID
	 *
	 * @param  string  $rowId  The ID of the row to fetch
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	public function get($rowId)
	{
		$cartCollection = $this->getCartCollection();

		return ($cartCollection->has($rowId)) ? $cartCollection->get($rowId) : null;
	}

	/**
	 * Get the cart content
	 *
	 * @return \Syscover\Shoppingcart\Libraries\CartRowCollection
	 */
	public function content()
	{
		$cartCollection = $this->getCartCollection();

		return (empty($cartCollection)) ? null : $cartCollection;
	}

	/**
	 * Get the price subtotal, sum of all rows except transport
	 *
	 * @return float
	 */
	public function subtotal()
	{
		$total 			= 0;
		$cartCollection = $this->getCartCollection();

		if(empty($cartCollection))
		{
			return $total;
		}

		foreach($cartCollection as $row)
		{
			$total += $row->subtotal;
		}

		return $total;
	}

	/**
	 * @return decimal
	 */
	public function discount()
	{
		$cartPriceRuleCollection 	= $this->getCartPriceRuleCollection();
		$discountAmount				= 0;

		foreach($cartPriceRuleCollection as $cartPriceRule)
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
		$cartCollection = $this->getCartCollection();

		if(empty($cartCollection))
		{
			return $total;
		}

		foreach($cartCollection as $row)
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
	 * Get the number of items in the cart
	 *
	 * @param  boolean  $totalItems  Get all the items (when false, will return the number of rows)
	 * @return int
	 */
	public function count($totalItems = true)
	{
		$cartCollection = $this->getCartCollection();

		if( ! $totalItems)
		{
			return $cartCollection->count();
		}

		$count = 0;

		foreach($cartCollection as $row)
		{
			$count += $row->qty;
		}

		return $count;
	}

	/**
	 * Search if the cart has a item
	 *
	 * @param  array  $search  An array with the item ID and optional options
	 * @return array|boolean
	 */
	public function search(array $search)
	{
		if(empty($search)) return false;

		foreach($this->getCartCollection() as $item)
		{
			$found = $item->search($search);

			if($found)
			{
				$rows[] = $item->rowid;
			}
		}

		return (empty($rows)) ? false : $rows;
	}

	/**
	 * Generate a unique id for the new row
	 *
	 * @param  string  $id       Unique ID of the item
	 * @param  array   $options  Array of additional options, such as 'size' or 'color'
	 * @return string
	 */
	protected function generateRowId($id, $options)
	{
		ksort($options);

		return md5($id . serialize($options));
	}

	/**
	 * Check if a rowid exists in the current cart instance
	 *
	 * @param  string  $rowId  Unique ID of the item
	 * @return boolean
	 */
	protected function hasRowId($rowId)
	{
		return $this->getCartCollection()->has($rowId);
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
		$cartCollection = $this->getCartCollection();

		$row = $cartCollection->get($rowId);

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

		$cartCollection->put($rowId, $row);

		return $cartCollection;
	}

	/**
	 * Create a new row Object
	 *
	 * @param  string  $rowId    The ID of the new row
	 * @param  string  $id       Unique ID of the item
	 * @param  string  $name     Name of the item
	 * @param  int     $qty      Item qty to add to the cart
	 * @param  float   $price    Price of one item
	 * @param  array   $options  Array of additional options, such as 'size' or 'color'
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function createRow($rowId, $id, $name, $qty, $price, $options)
	{
		$cartCollection = $this->getCartCollection();

		$newRow = new CartRowCollection([
			'rowid' 	=> $rowId,
			'id' 		=> $id,
			'name' 		=> $name,
			'qty' 		=> $qty,
			'price' 	=> $price,
			'options' 	=> new CartRowOptionsCollection($options),
			'subtotal' 	=> $qty * $price,

			// para implementar
			'tax'		=> null,
			'total'		=> null,
			'discount'	=> null,
		], $this->associatedModel, $this->associatedModelNamespace);

		$cartCollection->put($rowId, $newRow);

		return $cartCollection;
	}

	/**
	 * Update the quantity of a row
	 *
	 * @param  string  $rowId  The ID of the row
	 * @param  int     $qty    The qty to add
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function updateQty($rowId, $qty)
	{
		if($qty <= 0)
		{
			return $this->remove($rowId);
		}

		return $this->updateRow($rowId, ['qty' => $qty]);
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
	 * Check if the array is a multidimensional array
	 *
	 * @param  array   $array  The array to check
	 * @return boolean
	 */
	protected function is_multi(array $array)
	{
		return is_array(head($array));
	}

	/**
	 * Set the associated model
	 *
	 * @param  string    $modelName        The name of the model
	 * @param  string    $modelNamespace   The namespace of the model
	 * @return \Syscover\Shoppingcart\Libraries\Cart
	 * @throws ShoppingcartUnknownModelException
	 */
	public function associate($modelName, $modelNamespace = null)
	{
		$this->associatedModel 			= $modelName;
		$this->associatedModelNamespace = $modelNamespace;

		if( ! class_exists($modelNamespace . '\\' . $modelName)) throw new ShoppingcartUnknownModelException;

		// Return self so the method is chainable
		return $this;
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
		return $this->cartPriceRuleCollection;
	}

	/**
	 * count cartPriceRuleCollection
	 *
	 * @return integer
	 */
	public function countCartPriceRuleCollection()
	{
		return $this->cartPriceRuleCollection->count();
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
		$cartPriceRuleCollection = $this->getCartPriceRuleCollection();

		foreach($cartPriceRuleCollection as $cartPriceRule)
		{
			if($cartPriceRule->combinable_120 == false)
			{
				return $cartPriceRule;
			}
		}
		return null;
	}

	/**
	 * set cart has any rule not combinable
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
	 * Get the DiscountCollection
	 *
	 * @return \Syscover\Shoppingcart\Libraries\CartPriceRuleCollection
	 */
	protected function setCartPriceRuleCollection($cartPriceRuleCollection)
	{
		$this->cartPriceRuleCollection = $cartPriceRuleCollection;

		// save current changes
		$this->setCart();

		return $this->cartPriceRuleCollection;
	}


	/**
	 * check if any cartPriceRule exist in CartPriceRuleCollection
	 *
	 * @param  \Syscover\Market\Models\CartPriceRule  $cartPriceRule
	 * @return boolean
	 */
	public function hasCartPriceRule($cartPriceRule)
	{
		$cartPriceRuleCollection 	= $this->getCartPriceRuleCollection();
		$cartPriceRuleId 			= $this->generateCartPriceRuleId($cartPriceRule);

		// comprobamos que el id de descuento no existe en el carro
		if($cartPriceRuleCollection->has($cartPriceRuleId))
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
			$cartPriceRuleCollection 	= $this->getCartPriceRuleCollection();
			$cartPriceRuleId 			= $this->generateCartPriceRuleId($cartPriceRule);

			// add object to cart price collection
			$cartPriceRuleCollection->put($cartPriceRuleId, $cartPriceRule);

			// save cartPriceRuleCollection
			$this->setCartPriceRuleCollection($cartPriceRuleCollection);
		}
	}

	/**
	 * Update and create all amounts, inside all cartPriceRules
	 * This function set all data about rules, is called with every change
	 */
	protected function updateAmountsCartPriceRuleCollection()
	{
		$cartPriceRuleCollection = $this->getCartPriceRuleCollection();

		// in this step, add property discount_amount, inside cartPriceRule object
		foreach($cartPriceRuleCollection as &$cartPriceRule)
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