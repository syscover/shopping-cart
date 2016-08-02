# Advanced ShoppingCart to Laravel 5.2

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
Syscover\ShoppingCart\ShoppingCartServiceProvider::class,

```

**3 - Register alias, on file config/app.php add to aliases array**

```
'CartProvider' => Syscover\ShoppingCart\Facades\CartProvider::class,

```


##To run laravel testing

publish testing files
```
php artisan vendor:publish --provider="Syscover\ShoppingCart\ShoppingcartServiceProvider"
```

and run the test using the following command:
```
phpunit tests/CartProviderTest
```


##The shoppingcart gives you the following methods to use:

Add car item to cart, you need create Item objects to add cart, you can use a Item object or Items array if you want add various elements
```
// Basic add Item to Cart
CartProvider::instance()->add(new Item('293ad', 'Product 1', 1, 9.99, 1.000, true));

// Multiple add Items to Cart
CartProvider::instance()->add([
    new Item('293ad', 'Product 1', 1, 9.99, 1.000, true),
    new Item('979ze', 'Product 2', 1, 12.90, 1.000, true)
]);

// Multiple add Items with options to Cart
CartProvider::instance()->add([
    new Item('293ad', 'Product 1', 1, 9.99, 1.000, true, [], ['size' => 'L']),
    new Item('979ze', 'Product 2', 1, 12.90, 1.000, true, [], ['size' => 'M'])
]);
```


We have created TaxRule object to calculate tax from shopping cart, this object has this properties
* name:string = Name of tax
* taxRate:float = Percentage of tax
* priority:int [default 0] = Order to calculate tax over subtotal. If are different priorities, the highest tax is calculated on the subtotal more taxes lower priority 
* sortOrder:int [default 0] = Order to appear tax on screen

Cart is ready to TaxRule object, you can add tax rules to each cartItem object. You can add only one TaxRule or various with a array tax rules
```
// Multiple add Items to Cart with options and one tax rule
CartProvider::instance()->add([
    new Item('293ad', 'Product 1', 1, 9.99, 1.000, true, new TaxRule('IVA', 21), ['size' => 'L']),
    new Item('979ze', 'Product 2', 1, 12.90, 1.000, true, new TaxRule('IVA', 21), ['size' => 'M'])
]);

// Multiple add Items to Cart with options and various tax rules
CartProvider::instance()->add([
    new Item('293ad', 'Product 1', 1, 9.99, 1.000, true, [
            new TaxRule('IVA', 21, 0, 0),
            new TaxRule('Customs', 10, 1, 1),
        ], ['size' => 'L']),
    new Item('979ze', 'Product 2', 1, 12.90, 1.000, true, 
            new TaxRule('IVA', 21, 0, 0),
            new TaxRule('Customs', 10, 1, 1),
        ], ['size' => 'M'])
]);
```


To update quantity from a item you have setQuantity method, you'll pass the update method the rowId and the new quantity
```
$rowId = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

CartProvider::instance()->setQuantity($rowId, 2);
```


If you want to update more attributes of the item, you can pass Item object to the update method with rowId from item to update.
```
CartProvider::instance()->update($rowId, new Item('293ad', 'Product 1', 1, 19.99, 1.000, true, new TaxRule('IVA', 21), ['size' => 'L']));
```


To remove element from the cart, the remove() method on the cart and pass it the rowId
```
$rowId = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

CartProvider::instance()->remove($rowId);
```


If you want to get the cart items, you have getCartItems method. 
This method will return a Collection of CartItems which you can iterate over and show the content to your customers.
```
CartProvider::instance()->getCartItems();
```


If you want to get quantity from each items, you can use getQuantity method over each item
```
foreach(CartProvider::instance()->getCartItems() as $item)
{
  $item->getQuantity();
}
```


If you want to get an item from the cart using its rowId, you can simply call the get() method on the cart and pass it the rowId.
```
$rowId = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

CartProvider::instance()->getCartItems()->get($rowId);
```


If you want to completely remove the content of a cart, you can call the destroy method on the cart. This will remove all CartItems from the cart for the current cart instance.
```
CartProvider::instance()->destroy();
```


Get the subtotal price, without tax 
```
CartProvider::instance()->subtotal;

// or subtotal price formatted
CartProvider::instance()->getSubtotal();
```


To get total price
```
CartProvider::instance()->total();

// or total price formatted
CartProvider::instance()->getTotal();
```


Get the number of items in the cart, total items
```
 CartProvider::instance()->getQuantity();
```


To find an item in the cart, you can use the search() method.
As you can see the Closure will receive two parameters. The first is the CartItem to perform the check against. The second parameter is the rowId of this CartItem.

The method will return a Collection containing all Items that where found

This way of searching gives you total control over the search process and gives you the ability to create very precise and specific searches.
```
  CartProvider::instance()->search(function ($cartItem, $rowId) {
      return $cartItem->id === 1;
  });
```


The package also supports multiple instances of the cart. The way this works is like this:
You can set the current instance of the cart with CartProvider::instance('newInstance'), at that moment, the active instance of the cart is newInstance, so when you add, remove or get the content of the cart, you work with the newInstance instance of the cart. 
If you want to switch instances, you just call CartProvider::instance('otherInstance') again, and you're working with the otherInstance again.

The default cart instance is called default, so when you're not using instances, CartProvider::instance()->getCartItems(); is the same as CartProvider::instance('default')->getCartItems().

So a little example:
```
CartProvider::instance('shopping')->add(new Item('192ao12', 'Product 1', 1, 9.99));

// Get items of the 'shopping' cart
CartProvider::instance('shopping')->getCartItems();

CartProvider::instance('wishlist')->add(new Item('sdjk922', 'Product 2', 1, 19.95));

// Get items of the 'wishlist' cart
CartProvider::instance('wishlist')->getCartItems();
```


We have created PriceRule object to apply discounts over items cart
* name:string = Name of price rule
* description:string = Description of rule
* discountType:int = You have various options, below you have all options 
* combinable:boolean [default true] = Set if this price rule can to have other one in the same cart 
* discountPercentage [default null] = Set a rate percentage to discount
* discountFixed [default null] = Set a discount amount fixed
* maximumDiscountAmount [default null] = If you choose a discount percentage, you can set a maximum amount to discount
* applyShippingAmount [default false] = Check this option if you want apply discount to shipping amount too
* freeShipping [default false] = Check this option to set a rule whith free shipping

With this constants from PriceRule class, you can define discount type
```
WITHOUT_DISCOUNT
DISCOUNT_SUBTOTAL_PERCENTAGE
DISCOUNT_SUBTOTAL_FIXED_AMOUNT
DISCOUNT_TOTAL_PERCENTAGE
DISCOUNT_TOTAL_FIXED_AMOUNT
```

To set price rules you can use addCartPriceRule methop
```
    CartProvider::instance()->addCartPriceRule(
        new PriceRule(
            'My first price rule',
            'For being a good customer',
            PriceRule::DISCOUNT_SUBTOTAL_PERCENTAGE,
            true,
            10.00
        )
    );
```

Scenarios to consider when applying to the pricing rules
* We have created discounts over subtotal and total price, but you can not apply both discounts to the same time.










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