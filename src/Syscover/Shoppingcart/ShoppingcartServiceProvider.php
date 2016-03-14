<?php namespace Syscover\Shoppingcart;

use Illuminate\Support\ServiceProvider;
use Syscover\Shoppingcart\Libraries\CartProvider;

class ShoppingcartServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		// register tests
		$this->publishes([
			__DIR__ . '/../../tests/' => base_path('/tests')
		], 'tests');
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
			return new CartProvider($app);
		});
	}
}