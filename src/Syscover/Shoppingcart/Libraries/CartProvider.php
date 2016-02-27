<?php namespace Syscover\Shoppingcart\Libraries;

use Syscover\Shoppingcart\Exceptions\ShoppingcartInstanceException;

class CartProvider {

	/**
	 * Current cart instance
	 *
	 * @var string
	 */
	protected $instance;

	/**
	 * Get the carts content, if there is no cart content set yet, return a new empty Collection
	 *
	 * @return Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function getCart()
	{
		$content = (session()->has($this->getInstance())) ? session()->get($this->getInstance()) : new Cart($this->getInstance());

		return $content;
	}

	/**
	 * Set the current cart instance
	 *
	 * @param  string  $instance  Cart instance name
	 * @return Syscover\Shoppingcart\Libraries\Cart
	 * @throws ShoppingcartInstanceException
	 */
	public function instance($instance = 'main')
	{
		if(empty($instance)) throw new ShoppingcartInstanceException;

		$this->instance = $instance;

		// Return cart instance
		return $this->getCart();
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
}