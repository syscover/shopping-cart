<?php namespace Syscover\ShoppingCart;

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
	 * Get the Cart object, if there is no Cart object set yet, return a new Cart object
	 *
	 * @return \Syscover\ShoppingCart\Cart
	 */
	protected function getCart()
	{
		$content = (session()->has($this->instance)) ? session()->get($this->instance) : new Cart($this->instance);

		return $content;
	}

	/**
	 * Set the current cart instance
	 *
	 * @param   string                                  $instance  Cart instance name
	 * @return  \Syscover\ShoppingCart\Cart
	 */
	public function instance($instance = null)
	{
        $instance = $instance ?: self::DEFAULT_INSTANCE;

		$this->instance = sprintf('%s.%s', 'cart', $instance);

		// Return cart instance
		return $this->getCart();
	}
}