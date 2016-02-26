<?php namespace Syscover\Forms;

use Illuminate\Support\ServiceProvider;
use Syscover\Shoppingcart\Facades\Cart;

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
		$this->app->bind('cart', function($app)
		{
			return new Cart($app['session']);
		});
	}

}
