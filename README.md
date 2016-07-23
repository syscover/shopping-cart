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

**To run laravel testing**

publish testing files

```
php artisan vendor:publish --provider="Syscover\Shoppingcart\ShoppingcartServiceProvider"
```

and run the test using the following command:
```
phpunit tests/CartProviderTest
```


**The shoppingcart gives you the following methods to use:**

Add row to cart
```
// Basic form, without tax rules
CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, true, 1.000, ['size' => 'large']);

// Basic form, with tax rule
CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, true, 1.000, [], ['name' => 'VAT', 'priority' => 1, 'sortOrder' => 1, 'taxRate' => 21.00]);

// Basic form, with various tax rules
CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, true, 1.000, [], [['name' => 'VAT', 'priority' => 1, 'sortOrder' => 1, 'taxRate' => 21.00], ['name' => 'VAT2', 'priority' => 1, 'sortOrder' => 1, 'taxRate' => 10.00]]);

// Array form
CartProvider::instance()->add(['id' => '293ad', 'name' => 'Product 1', 'qty' => 1, 'price' => 9.99, 'transportable' => true, 'weight' => 1.000, 'options' => ['size' => 'large']]);

// Batch method
CartProvider::instance()->add([
  ['id' => '293ad', 'name' => 'Product 1', 'qty' => 1, 'price' => 10.00, 'transportable' => true, 'weight' => 1.000],
  ['id' => '4832k', 'name' => 'Product 2', 'qty' => 1, 'price' => 10.00, 'transportable' => true, 'weight' => 1.000, 'options' => ['size' => 'large']]
]);
```

To update an item in the cart, you'll first need the rowId of the item. Next you can use the update() method to update it.

If you simply want to update the quantity, you'll pass the update method the rowId and the new quantity: 

```
$rowId = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

CartProvider::instance()->update($rowId, 2);
```

If you want to update more attributes of the item, you can either pass the update method an array or a Buyable as the second parameter. 
This way you can update all information of the item with the given rowId.

```
CartProvider::instance()->update($rowId, ['name' => 'Product 1']); // Will update the name
CartProvider::instance()->update($rowId, $product); // Will update the id, name and price

```

To remove element from the cart, the remove() method on the cart and pass it the rowId

```
$rowId = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

CartProvider::instance()->remove($rowId);
```


If you want to get an item from the cart using its rowId, you can simply call the get() method on the cart and pass it the rowId.

```
$rowId = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

CartProvider::instance()->get($rowId);
```

If you also want to get the cart content. 
This is where you'll use the content method. 
This method will return a Collection of CartItems which you can iterate over and show the content to your customers.

```
CartProvider::instance()->content();
```

If you want to completely remove the content of a cart, you can call the destroy method on the cart. This will remove all CartItems from the cart for the current cart instance.

```
CartProvider::instance()->destroy();
```

Get the price without shipping

```
CartProvider::instance()->subtotal();
```

To get total price

```
CartProvider::instance()->total()

/**
 * Get the price total with shipping
 *
 * @return float
 */

CartProvider::instance()->total();
```

Get the number of items in the cart

```
 CartProvider::instance()->count(); // Total items
```

To find an item in the cart, you can use the search() method.
As you can see the Closure will receive two parameters. The first is the CartItem to perform the check against. The second parameter is the rowId of this CartItem.

The method will return a Collection containing all CartItems that where found

This way of searching gives you total control over the search process and gives you the ability to create very precise and specific searches.

```
  $cart->search(function ($cartItem, $rowId) {
      return $cartItem->id === 1;
  });
```

The package also supports multiple instances of the cart. The way this works is like this:
You can set the current instance of the cart with Cart::instance('newInstance'), at that moment, the active instance of the cart is newInstance, so when you add, remove or get the content of the cart, you work with the newInstance instance of the cart. 
If you want to switch instances, you just call Cart::instance('otherInstance') again, and you're working with the otherInstance again.

The default cart instance is called default, so when you're not using instances, Cart::instance()->content(); is the same as Cart::instance('default')->content().

So a little example:
```
CartProvider::instance('shopping')->add('192ao12', 'Product 1', 1, 9.99);

// Get the content of the 'shopping' cart
CartProvider::instance('shopping')->content();

Cart::instance('wishlist')->add('sdjk922', 'Product 2', 1, 19.95, array('size' => 'medium'));

// Get the content of the 'wishlist' cart
Cart::instance('wishlist')->content();
```

If you want to check, if this cart has shipping, you can use this method

```
CartProvider::instance()->hasShipping();
```

If you want set shipping with true or false, you can use this method setShipping and pass boolean parameter

```
CartProvider::instance()->setShipping(true);
```

You have setShippingAmount to set amount shipping of all cart

```
CartProvider::instance()->setShippingAmount();
```

You have getShippingAmount to get amount shipping of all cart

```
CartProvider::instance()->getShippingAmount();
```