<?php namespace Syscover\Shoppingcart;

use Syscover\Shoppingcart\Exceptions\ShoppingcartInstanceException;

class CartProvider
{
    const DEFAULT_INSTANCE = 'default';

	/**
	 * Current cart instance
	 *
	 * @var string
	 */
	protected $instance;

	/**
	 * Get the carts content, if there is no cart content set yet, return a new empty Collection
	 *
	 * @return \Syscover\Shoppingcart\Libraries\CartCollection
	 */
	protected function getCart()
	{
		$content = (session()->has($this->instance)) ? session()->get($this->instance) : new Cart($this->instance);

		return $content;
	}

	/**
	 * Set the current cart instance
	 *
	 * @param  string  $instance  Cart instance name
	 * @return \Syscover\Shoppingcart\Libraries\Cart
	 * @throws ShoppingcartInstanceException
	 */
	public function instance($instance = null)
	{
        $instance = $instance ?: self::DEFAULT_INSTANCE;

		$this->instance = sprintf('%s.%s', 'cart', $instance);

		// Return cart instance
		return $this->getCart();
	}
}