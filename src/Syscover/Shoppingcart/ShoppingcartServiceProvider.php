<?php namespace Syscover\Shoppingcart;

use Illuminate\Support\ServiceProvider;

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

        // register config files
        $this->publishes([
            __DIR__ . '/../../config/shoppingcart.php' 			=> config_path('shoppingcart.php')
        ]);
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind('cartProvider', CartProvider::class);
	}
}