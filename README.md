# ShoppingCart to Laravel 5.2

## Installation

**1 - After install Laravel framework, insert on file composer.json, inside require object this value**
```
"syscover/shoppingcart": "dev-master"
```

and execute on console:
```
composer update
```

**2 - Register service provider, on file config/app.php add to providers array**

```
Syscover\Shoppingcart\ShoppingcartServiceProvider::class,

```

**3 - Register alias, on file config/app.php add to aliases array**

```
'CartProvider' => Syscover\Shoppingcart\Facades\CartProvider::class,

```

** To run laravel testing **
publish testin files

```
php artisan vendor:publish --provider="Syscover\Shoppingcart\ShoppingcartServiceProvider"
```

and run the test using the following command:
```
phpunit tests/CartProviderTest
```


**The shoppingcart gives you the following methods to use:**

CartProvider::instance()->add()
```
/**
 * Add a row to the cart
 *
 * @param string|Array $id      Unique ID of the item|Item formated as array|Array of items
 * @param string       $name    Name of the item
 * @param int          $qty     Item qty to add to the cart
 * @param float        $price   Price of one item
 * @param Array        $options Array of additional options, such as 'size' or 'color'
 */

// Basic form
CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, array('size' => 'large'));

// Array form
CartProvider::instance()->(['id' => '293ad', 'name' => 'Product 1', 'qty' => 1, 'price' => 9.99, 'options' => array('size' => 'large')]);

// Batch method
CartProvider::instance()->([
  array('id' => '293ad', 'name' => 'Product 1', 'qty' => 1, 'price' => 10.00),
  array('id' => '4832k', 'name' => 'Product 2', 'qty' => 1, 'price' => 10.00, 'options' => array('size' => 'large'))
]);
```

CartProvider::instance()->update()

```
/**
 * Update the quantity of one row of the cart
 *
 * @param  string        $rowId       The rowid of the item you want to update
 * @param  integer|Array $attribute   New quantity of the item|Array of attributes to update
 * @return boolean
 */
 $rowId = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

CartProvider::instance()->update($rowId, 2);
```

OR

CartProvider::instance()->update($rowId, array('name' => 'Product 1'));


CartProvider::instance()->remove()

```
/**
 * Remove a row from the cart
 *
 * @param  string  $rowId The rowid of the item
 * @return boolean
 */

 $rowId = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

CartProvider::instance()->remove($rowId);
```

CartProvider::instance()->get()

/**
 * Get a row of the cart by its ID
 *
 * @param  string $rowId The ID of the row to fetch
 * @return CartRowCollection
 */

$rowId = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

CartProvider::instance()->get($rowId);


CartProvider::instance()->content()

/**
 * Get the cart content
 *
 * @return CartCollection
 */

CartProvider::instance()->content();

CartProvider::instance()->destroy()

/**
 * Empty the cart
 *
 * @return boolean
 */

CartProvider::instance()->destroy();


CartProvider::instance()->subtotal()

/**
 * Get the price without shipping
 *
 * @return float
 */

CartProvider::instance()->subtotal();


CartProvider::instance()->total()

/**
 * Get the price total with shipping
 *
 * @return float
 */

CartProvider::instance()->total();


CartProvider::instance()->count()

/**
 * Get the number of items in the cart
 *
 * @param  boolean $totalItems Get all the items (when false, will return the number of rows)
 * @return int
 */

 CartProvider::instance()->count();     // Total items
 CartProvider::instance()->(false);     // Total rows


 CartProvider::instance()->search()

 /**
  * Search if the cart has a item
  *
  * @param  Array  $search An array with the item ID and optional options
  * @return Array|boolean
  */

  CartProvider::instance()->search(array('id' => 1, 'options' => array('size' => 'L'))); // Returns an array of rowid(s) of found item(s) or false on failure



CartProvider::instance()->hasShippinh();
CartProvider::instance()->getShippinh();
CartProvider::instance()->setShippinh();


Instances

Now the packages also supports multiple instances of the cart. The way this works is like this:

You can set the current instance of the cart with Cart::instance('newInstance'), at that moment, the active instance of the cart is newInstance, so when you add, remove or get the content of the cart, you work with the newInstance instance of the cart. If you want to switch instances, you just call Cart::instance('otherInstance') again, and you're working with the otherInstance again.

So a little example:

CartProvider::instance('shopping')->add('192ao12', 'Product 1', 1, 9.99);

// Get the content of the 'shopping' cart
CartProvider::instance('shopping')->content();

Cart::instance('wishlist')->add('sdjk922', 'Product 2', 1, 19.95, array('size' => 'medium'));

// Get the content of the 'wishlist' cart
Cart::instance('wishlist')->content();

The default cart instance is called main, so when you're not using instances, Cart::instance()->content(); is the same as Cart::instance('main')->content().