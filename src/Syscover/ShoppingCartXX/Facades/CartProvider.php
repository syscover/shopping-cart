<?php namespace Syscover\ShoppingCart\Facades;

use Illuminate\Support\Facades\Facade;

class CartProvider extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() { return 'cartProvider'; }

}