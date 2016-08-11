<?php namespace Syscover\ShoppingCart;

use Illuminate\Support\ServiceProvider;

class ShoppingCartServiceProvider extends ServiceProvider
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
            __DIR__ . '/../../config/shoppingCart.php' => config_path('shoppingCart.php')
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