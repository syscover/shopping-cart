<?php
use \Syscover\Shoppingcart\Facades\CartProvider;
use \Syscover\Shoppingcart\Cart;

require_once __DIR__ . '/shopping_cart_tests_helpers/SessionMock.php';
require_once __DIR__ . '/shopping_cart_tests_helpers/ProductModelStub.php';
require_once __DIR__ . '/shopping_cart_tests_helpers/NamespacedProductModelStub.php';

class CartProviderTest extends TestCase
{
    public function testCartCanAdd()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, true, 1.000);
    }

    public function testCartCanAddWithOptions()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, true, 1.000, ['size' => 'large']);
    }

    public function testCartCanAddWithTaxRule()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, true, 1.000, [], ['name' => 'VAT', 'priority' => 1, 'sortOrder' => 1, 'taxRate' => 21.00]);

        $this->assertEquals(1, CartProvider::instance()->content()->first()->taxRules->count());
        $this->assertEquals(21, CartProvider::instance()->content()->first()->taxRules->first()->taxRate);
        $this->assertEquals('21,00', CartProvider::instance()->content()->first()->getTaxRate());
    }

    public function testCartCanAddWithTaxRules()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, true, 1.000, [], [['name' => 'VAT', 'priority' => 1, 'sortOrder' => 1, 'taxRate' => 21.00], ['name' => 'VAT2', 'priority' => 1, 'sortOrder' => 1, 'taxRate' => 10.00]]);

        $this->assertEquals(2, CartProvider::instance()->content()->first()->taxRules->count());
    }

    public function testCartCanAddWithSameTaxRules()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add('293ad', 'Product 1', 1, 110.99, true, 1.000, [], [['name' => 'VAT', 'priority' => 1, 'sortOrder' => 1, 'taxRate' => 21.00], ['name' => 'VAT', 'priority' => 1, 'sortOrder' => 1, 'taxRate' => 10.00]]);

        $this->assertEquals(1, CartProvider::instance()->content()->first()->taxRules->count());
        $this->assertEquals(31, CartProvider::instance()->content()->first()->taxRules->first()->taxRate);
        $this->assertEquals('31,00', CartProvider::instance()->content()->first()->getTaxRate());

        if(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX)
        {
            $this->assertEquals('110,99', CartProvider::instance()->content()->first()->getSubtotal());
            $this->assertEquals(110.99, CartProvider::instance()->content()->first()->subtotal);
            $this->assertEquals('34,41', CartProvider::instance()->content()->first()->getTaxAmount());
            $this->assertEquals('145,40', CartProvider::instance()->content()->first()->getTotal());
            $this->assertEquals(145.396899999999988040144671685993671417236328125, CartProvider::instance()->content()->first()->total);
        }
        elseif(config('shoppingcart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            $this->assertEquals('84,73', CartProvider::instance()->content()->first()->getSubtotal());
            $this->assertEquals(84.7251908396946618040601606480777263641357421875, CartProvider::instance()->content()->first()->subtotal);
            $this->assertEquals('26,26', CartProvider::instance()->content()->first()->getTaxAmount());
            $this->assertEquals('110,99', CartProvider::instance()->content()->first()->getTotal());
            $this->assertEquals(110.99, CartProvider::instance()->content()->first()->total);
        }
    }

    public function testCartCanAddBatch()
    {
        $this->expectsEvents('cart.batch');

        CartProvider::instance()->add([
            ['id' => '293ad', 'name' => 'Product 1', 'qty' => 3, 'price' => 10.00, 'transportable' => true, 'weight' => 1.000],
            ['id' => '4832k', 'name' => 'Product 2', 'qty' => 2, 'price' => 10.00, 'transportable' => true, 'weight' => 1.000, 'options' => ['size' => 'large']]
        ]);

        $this->assertEquals(5, CartProvider::instance()->count());
        $this->assertEquals('30,00', CartProvider::instance()->content()->first()->getSubtotal());
    }

//    public function testCartCanAddMultiple()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        for($i = 1; $i <= 5; $i++)
//        {
//            CartProvider::instance()->add('293ad' . $i, 'Product ' . $i, 1, 9.99, true, 1.000);
//        }
//
//        $this->assertEquals(5, CartProvider::instance()->count());
//    }
//
//    public function testCartCanAddWithNumericId()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add(12345, 'Product 1', 1, 9.99, array('size' => 'large'));
//    }
//
//    public function testCartCanAddArray()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add(array('id' => '293ad', 'name' => 'Product 1', 'qty' => 1, 'price' => 9.99, 'options' => array('size' => 'large')));
//    }
//
//    public function testCartCanAddBatch()
//    {
//        $this->expectsEvents('cart.batch');
//        $this->expectsEvents('cart.batched');
//
//        CartProvider::instance()->add(array(
//            array('id' => '293ad', 'name' => 'Product 1', 'qty' => 1, 'price' => 10.00),
//            array('id' => '4832k', 'name' => 'Product 2', 'qty' => 1, 'price' => 10.00, 'options' => array('size' => 'large'))
//        ));
//    }
//
//    public function testCartCanAddMultipleOptions()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, array('size' => 'large', 'color' => 'red'));
//
//        $cartRow = CartProvider::instance()->get('c5417b5761c7fb837e4227a38870dd4d');
//
//        $this->assertInstanceOf('Syscover\Shoppingcart\Libraries\CartRowOptionsCollection', $cartRow->options);
//        $this->assertEquals('large', $cartRow->options->size);
//        $this->assertEquals('red', $cartRow->options->color);
//    }
//
//    /**
//     * @expectedException Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidItemException
//     */
//    public function testCartThrowsExceptionOnEmptyItem()
//    {
//        $this->expectsEvents('cart.add');
//
//        CartProvider::instance()->add('', '', '', '');
//    }
//
//    /**
//     * @expectedException Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidQtyException
//     */
//    public function testCartThrowsExceptionOnNoneNumericQty()
//    {
//        $this->expectsEvents('cart.add');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 'none-numeric', 9.99);
//    }
//
//    /**
//     * @expectedException Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidPriceException
//     */
//    public function testCartThrowsExceptionOnNoneNumericPrice()
//    {
//        $this->expectsEvents('cart.add');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 'none-numeric');
//    }
//
//    public function testCartCanUpdateExistingItem()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//
//        $this->assertEquals(2, CartProvider::instance()->content()->first()->qty);
//    }
//
//    public function testCartCanUpdateQty()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//        $this->expectsEvents('cart.update');
//        $this->expectsEvents('cart.updated');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->update('8cbf215baa3b757e910e5305ab981172', 2);
//
//        $this->assertEquals(2, CartProvider::instance()->content()->first()->qty);
//    }
//
//    public function testCartCanUpdateItem()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//        $this->expectsEvents('cart.update');
//        $this->expectsEvents('cart.updated');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->update('8cbf215baa3b757e910e5305ab981172', array('name' => 'Product 2'));
//
//        $this->assertEquals('Product 2', CartProvider::instance()->content()->first()->name);
//    }
//
//    public function testCartCanUpdateItemToNumericId()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//        $this->expectsEvents('cart.update');
//        $this->expectsEvents('cart.updated');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->update('8cbf215baa3b757e910e5305ab981172', array('id' => 12345));
//
//        $this->assertEquals(12345, CartProvider::instance()->content()->first()->id);
//    }
//
//    public function testCartCanUpdateOptions()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//        $this->expectsEvents('cart.update');
//        $this->expectsEvents('cart.updated');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99, array('size' => 'S'));
//        CartProvider::instance()->update('9be7e69d236ca2d09d2e0838d2c59aeb', array('options' => array('size' => 'L')));
//
//        $this->assertEquals('L', CartProvider::instance()->content()->first()->options->size);
//    }
//
//    /**
//     * @expectedException Syscover\Shoppingcart\Exceptions\ShoppingcartInvalidRowIDException
//     */
//    public function testCartThrowsExceptionOnInvalidRowId()
//    {
//        CartProvider::instance()->update('invalidRowId', 1);
//    }
//
//    public function testCartCanRemove()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//        $this->expectsEvents('cart.remove');
//        $this->expectsEvents('cart.removed');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->remove('8cbf215baa3b757e910e5305ab981172');
//
//        $this->assertTrue(CartProvider::instance()->content()->isEmpty());
//    }
//
//    public function testCartCanRemoveOnUpdate()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//        $this->expectsEvents('cart.update');
//        $this->expectsEvents('cart.updated');
//        $this->expectsEvents('cart.remove');
//        $this->expectsEvents('cart.removed');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->update('8cbf215baa3b757e910e5305ab981172', 0);
//
//        $this->assertTrue(CartProvider::instance()->content()->isEmpty());
//    }
//
//    public function testCartCanRemoveOnNegativeUpdate()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//        $this->expectsEvents('cart.update');
//        $this->expectsEvents('cart.updated');
//        $this->expectsEvents('cart.remove');
//        $this->expectsEvents('cart.removed');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->update('8cbf215baa3b757e910e5305ab981172', -1);
//
//        $this->assertTrue(CartProvider::instance()->content()->isEmpty());
//    }
//
//    public function testCartCanGet()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        $item = CartProvider::instance()->get('8cbf215baa3b757e910e5305ab981172');
//
//        $this->assertEquals('293ad', $item->id);
//    }
//
//    public function testCartCanGetContent()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//
//        $this->assertInstanceOf('Syscover\Shoppingcart\Libraries\CartCollection', CartProvider::instance()->content());
//        $this->assertFalse(CartProvider::instance()->content()->isEmpty());
//    }
//
//    public function testCartCanDestroy()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//        $this->expectsEvents('cart.destroy');
//        $this->expectsEvents('cart.destroyed');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->destroy();
//
//        $this->assertInstanceOf('Syscover\Shoppingcart\Libraries\CartCollection', CartProvider::instance()->content());
//        $this->assertTrue(CartProvider::instance()->content()->isEmpty());
//    }
//
//    public function testCartCanDestroyOnlyOneInstance()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//        $this->expectsEvents('cart.destroy');
//        $this->expectsEvents('cart.destroyed');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance('testing')->add('963bb', 'Product 2', 1, 19.90);
//        CartProvider::instance()->destroy();
//
//        $this->assertInstanceOf('Syscover\Shoppingcart\Libraries\CartCollection', CartProvider::instance()->content());
//        $this->assertTrue(CartProvider::instance()->content()->isEmpty());
//
//        $this->assertInstanceOf('Syscover\Shoppingcart\Libraries\CartCollection', CartProvider::instance('testing')->content());
//        $this->assertFalse(CartProvider::instance('testing')->content()->isEmpty());
//    }
//
//    public function testCartCanGetTotal()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->add('986se', 'Product 2', 1, 19.99);
//
//        $this->assertEquals(29.98, CartProvider::instance()->total());
//    }
//
//    public function testCartCanGetItemCount()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->add('986se', 'Product 2', 2, 19.99);
//
//        $this->assertEquals(3, CartProvider::instance()->count());
//    }
//
//    public function testCartCanGetRowCount()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance()->add('986se', 'Product 2', 2, 19.99);
//
//        $this->assertEquals(2, CartProvider::instance()->count(false));
//    }
//
//    public function testCartCanSearch()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//
//        $searchResult = CartProvider::instance()->search(array('id' => '293ad'));
//        $this->assertEquals('8cbf215baa3b757e910e5305ab981172', $searchResult[0]);
//    }
//
//    public function testCartCanHaveMultipleInstances()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance('firstInstance')->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance('secondInstance')->add('986se', 'Product 2', 1, 19.99);
//
//        $this->assertTrue(CartProvider::instance('firstInstance')->content()->has('8cbf215baa3b757e910e5305ab981172'));
//        $this->assertFalse(CartProvider::instance('firstInstance')->content()->has('22eae2b9c10083d6631aaa023106871a'));
//        $this->assertTrue(CartProvider::instance('secondInstance')->content()->has('22eae2b9c10083d6631aaa023106871a'));
//        $this->assertFalse(CartProvider::instance('secondInstance')->content()->has('8cbf215baa3b757e910e5305ab981172'));
//    }
//
//    public function testCartCanSearchInMultipleInstances()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance('firstInstance')->add('293ad', 'Product 1', 1, 9.99);
//        CartProvider::instance('secondInstance')->add('986se', 'Product 2', 1, 19.99);
//
//        $this->assertEquals(CartProvider::instance('firstInstance')->search(array('id' => '293ad')), array('8cbf215baa3b757e910e5305ab981172'));
//        $this->assertEquals(CartProvider::instance('secondInstance')->search(array('id' => '986se')), array('22eae2b9c10083d6631aaa023106871a'));
//    }
//
//    /**
//     * @expectedException Syscover\Shoppingcart\Exceptions\ShoppingcartInstanceException
//     */
//    public function testCartThrowsExceptionOnEmptyInstance()
//    {
//        CartProvider::instance('');
//    }
//
//    public function testCartReturnsCartCollection()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//
//        $this->assertInstanceOf('Syscover\Shoppingcart\Libraries\CartCollection', CartProvider::instance()->content());
//    }
//
//    public function testCartCollectionHasCartRowCollection()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//
//        $this->assertInstanceOf('Syscover\Shoppingcart\Libraries\CartRowCollection', CartProvider::instance()->content()->first());
//    }
//
//    public function testCartRowCollectionHasCartRowOptionsCollection()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//
//        $this->assertInstanceOf('Syscover\Shoppingcart\Libraries\CartRowOptionsCollection', CartProvider::instance()->content()->first()->options);
//    }
//
//    public function testCartCanAssociateWithModel()
//    {
//        $cart = CartProvider::instance()->associate('TestProduct');
//
//        $this->assertEquals('TestProduct', PHPUnit_Framework_Assert::readAttribute($cart, 'associatedModel'));
//    }
//
//    public function testCartCanAssociateWithNamespacedModel()
//    {
//        $cart = CartProvider::instance()->associate('TestProduct', 'Acme\Test\Models');
//
//        $this->assertEquals('TestProduct', PHPUnit_Framework_Assert::readAttribute($cart, 'associatedModel'));
//        $this->assertEquals('Acme\Test\Models', PHPUnit_Framework_Assert::readAttribute($cart, 'associatedModelNamespace'));
//    }
//
//    public function testCartCanReturnModelProperties()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->associate('TestProduct')->add('293ad', 'Product 1', 1, 9.99);
//
//        $this->assertEquals('This is the description of the test model', CartProvider::instance()->get('8cbf215baa3b757e910e5305ab981172')->testproduct->description);
//    }
//
//    public function testCartCanReturnNamespadedModelProperties()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->associate('TestProduct', 'Acme\Test\Models')->add('293ad', 'Product 1', 1, 9.99);
//
//        $this->assertEquals('This is the description of the namespaced test model', CartProvider::instance()->get('8cbf215baa3b757e910e5305ab981172')->testproduct->description);
//    }
//
//    /**
//     * @expectedException Syscover\Shoppingcart\Exceptions\ShoppingcartUnknownModelException
//     */
//    public function testCartThrowsExceptionOnUnknownModel()
//    {
//        CartProvider::instance()->associate('NoneExistingModel');
//    }
}