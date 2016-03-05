<?php namespace Syscover\Shoppingcart;

use Illuminate\Support\ServiceProvider;
use Syscover\Shoppingcart\Facades\CartProvider;

class ShoppingcartServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		//
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind('cartProvider', function($app)
		{
			return new CartProvider();
		});
	}

}
