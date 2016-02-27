<?php namespace Syscover\Shoppingcart\Libraries;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInstanceException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartUnknownModelException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidItemException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidPriceException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidRowIDException;
use Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidQtyException;

class Cart {

	/**
	 * Current cart instance
	 *
	 * @var string
	 */
	protected $instance;

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
	 * content to rows of cart
	 *
	 * @var string
	 */
	//protected $cartCollection;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		$this->instance = 'main';
	}

	/**
	 * Set the current cart instance
	 *
	 * @param  string  $instance  Cart instance name
	 * @return Syscover\Shoppingcart\Libraries\Cart
	 * @throws ShoppingcartInstanceException
	 */
	public function instance($instance = null)
	{
		if(empty($instance)) throw new ShoppingcartInstanceException;

		$this->instance = $instance;

		// Return self so the method is chainable
		return $this;
	}

	/**
	 * Set the associated model
	 *
	 * @param  string    $modelName        The name of the model
	 * @param  string    $modelNamespace   The namespace of the model
	 * @return Syscover\Shoppingcart\Libraries\Cart
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
	 * Add a row to the cart
	 *
	 * @param string|array  $id       Unique ID of the item|Item formated as array|Array of items
	 * @param string 	    $name     Name of the item
	 * @param int    	    $qty      Item qty to add to the cart
	 * @param float  	    $price    Price of one item
	 * @param array  	    $options  Array of additional options, such as 'size' or 'color'
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
	 * @throws ShoppingcartInvalidRowIDException
	 */
	public function remove($rowId)
	{
		if( ! $this->hasRowId($rowId)) throw new ShoppingcartInvalidRowIDException;

		$cart = $this->getContent();

		// Fire the cart.remove event
		event('cart.remove', $rowId);

		$cart->forget($rowId);

		// Fire the cart.removed event
		event('cart.removed', $rowId);

		return $this->updateCart($cart);
	}

	/**
	 * Get a row of the cart by its ID
	 *
	 * @param  string  $rowId  The ID of the row to fetch
	 * @return Syscover\Shoppingcart\Libraries\CartCollection
	 */
	public function get($rowId)
	{
		$cart = $this->getContent();

		return ($cart->has($rowId)) ? $cart->get($rowId) : NULL;
	}

	/**
	 * Get the cart content
	 *
	 * @return Syscover\Shoppingcart\Libraries\CartRowCollection
	 */
	public function content()
	{
		$cart = $this->getContent();

		return (empty($cart)) ? NULL : $cart;
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

		$result = session()->put($this->getInstance(), null);

		// Fire the cart.destroyed event
		event('cart.destroyed');

		return $result;
	}

	/**
	 * Get the price total
	 *
	 * @return float
	 */
	public function total()
	{
		$total = 0;
		$cart = $this->getContent();

		if(empty($cart))
		{
			return $total;
		}

		foreach($cart AS $row)
		{
			$total += $row->subtotal;
		}

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
		$cart = $this->getContent();

		if( ! $totalItems)
		{
			return $cart->count();
		}

		$count = 0;

		foreach($cart AS $row)
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

		foreach($this->getContent() as $item)
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
	 * Add row to the cart
	 *
	 * @param string  $id       Unique ID of the item
	 * @param string  $name     Name of the item
	 * @param int     $qty      Item qty to add to the cart
	 * @param float   $price    Price of one item
	 * @param array   $options  Array of additional options, such as 'size' or 'color'
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

		$cart 	= $this->getContent();

		$rowId 	= $this->generateRowId($id, $options);

		if($cart->has($rowId))
		{
			$row 	= $cart->get($rowId);
			$cart 	= $this->updateRow($rowId, ['qty' => $row->qty + $qty]);
		}
		else
		{
			$cart 	= $this->createRow($rowId, $id, $name, $qty, $price, $options);
		}

		return $this->updateCart($cart);
	}

	/**
	 * Generate a unique id for the new row
	 *
	 * @param  string  $id       Unique ID of the item
	 * @param  array   $options  Array of additional options, such as 'size' or 'color'
	 * @return boolean
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
		return $this->getContent()->has($rowId);
	}

	/**
	 * Update the cart
	 *
	 * @param  Syscover\Shoppingcart\Libraries\CartCollection  $cart  The new cart content
	 * @return Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function updateCart($cart)
	{
		//return session()->put($this->getInstance(), $cart);

		// set cartCollection
		$obj = new CartWrapper();
		$obj->hola = 'hola mundo '.$this->getInstance();
		$obj->cartCollection = $cart;

		return session()->put($this->getInstance(), $obj);
	}

	/**f
	 * Get the carts content, if there is no cart content set yet, return a new empty Collection
	 *
	 * @return Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function getContent()
	{
		$content = (session()->has($this->getInstance())) ? session()->get($this->getInstance())->cartCollection : new CartCollection;

		return $content;
	}

	/**
	 * Get the current cart instance
	 *
	 * @return string
	 */
	protected function getInstance()
	{
		return 'cart.' . $this->instance;
	}

	/**
	 * Update a row if the rowId already exists
	 *
	 * @param  string   $rowId  The ID of the row to update
	 * @param  array  	$attributes    The quantity and price to add to the row
	 * @return Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function updateRow($rowId, $attributes)
	{
		$cart = $this->getContent();

		$row = $cart->get($rowId);

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

		$cart->put($rowId, $row);

		return $cart;
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
	 * @return Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function createRow($rowId, $id, $name, $qty, $price, $options)
	{
		$cart = $this->getContent();

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

		$cart->put($rowId, $newRow);

		return $cart;
	}

	/**
	 * Update the quantity of a row
	 *
	 * @param  string  $rowId  The ID of the row
	 * @param  int     $qty    The qty to add
	 * @return Syscover\Shoppingcart\Libraries\CartCollection
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
	 * @return Syscover\Shoppingcart\Libraries\CartCollection
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

}
