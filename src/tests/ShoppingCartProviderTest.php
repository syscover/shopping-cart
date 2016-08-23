<?php
use Syscover\ShoppingCart\Facades\CartProvider;
use Syscover\ShoppingCart\Cart;
use Syscover\ShoppingCart\Item;
use Syscover\ShoppingCart\TaxRule;
use Syscover\ShoppingCart\PriceRule;

require_once __DIR__ . '/shopping_cart_tests_helpers/SessionMock.php';
require_once __DIR__ . '/shopping_cart_tests_helpers/ProductModelStub.php';
require_once __DIR__ . '/shopping_cart_tests_helpers/NamespacedProductModelStub.php';

class ShoppingCartProviderTest extends TestCase
{
    public function testCartCanAdd()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('293ad', 'Product 1', 1, 9.99, 1.000, true));

        $this->assertEquals(1, CartProvider::instance()->getCartItems()->count());
    }

    public function testCartCanAddWithOptions()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('293ad', 'Product 1', 1, 9.99, 1.000, true, [], ['size' => 'L']));
    }

    public function testCartCanAddMultipleCartItems()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add([
            new Item('283ad', 'Product 2', 2, 10.00, 1.000),
            new Item('293ad', 'Product 1', 1, 9.99, 1.000),
            new Item('244ad', 'Product 3', 2, 20.50, 1.000)
        ]);

        $this->assertEquals(3, CartProvider::instance()->getCartItems()->count());
        $this->assertEquals(10.00, CartProvider::instance()->getCartItems()->first()->price);
    }

    public function testCartCanAddWithTaxRule()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('293ad', 'Product 1', 1, 9.99, 1.000, true, new TaxRule('VAT', 21.00)));

        $this->assertEquals(1, CartProvider::instance()->getCartItems()->first()->taxRules->count());
        $this->assertEquals(21, CartProvider::instance()->getCartItems()->first()->taxRules->first()->taxRate);
        $this->assertEquals(['21'], CartProvider::instance()->getCartItems()->first()->getTaxRates());
    }

    public function testCartCanAddVariousWithTaxRules()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('293ad', 'Product 1', 2, 100, 1.000, true, [
            new TaxRule('IVA', 21.00, 0, 0),
            new TaxRule('OTHER IVA', 10.00, 1, 1)
        ]));

        $this->assertEquals(2, CartProvider::instance()->getCartItems()->first()->taxRules->count());

        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX)
        {

        }
        elseif(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            $this->assertEquals('150,26', CartProvider::instance()->getCartItems()->first()->getSubtotal());
            $this->assertEquals(150.262960180315559455266338773071765899658203125, CartProvider::instance()->getCartItems()->first()->subtotal);
            //$this->assertEquals('24,87', CartProvider::instance()->getCartItems()->first()->getTaxAmount());
            $this->assertEquals('200,00', CartProvider::instance()->getCartItems()->first()->getTotal());
            $this->assertEquals(200, CartProvider::instance()->getCartItems()->first()->total);

            $this->assertEquals('31,56', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxAmount());
            $this->assertEquals('21', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxRate());
            $this->assertEquals('IVA', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->name);

            $this->assertEquals('18,18', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxAmount());
            $this->assertEquals('10', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxRate());
            $this->assertEquals('OTHER IVA', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->name);
        }
    }

    public function testCartCanAddWithTaxRules()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('293ad', 'Product 1', 1, 100, 1.000, true, [
            new TaxRule('IVA', 21.00, 0, 0),
            new TaxRule('OTHER IVA', 10.00, 1, 1)
        ]));

        $this->assertEquals(2, CartProvider::instance()->getCartItems()->first()->taxRules->count());

        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX)
        {
            $this->assertEquals('100,00', CartProvider::instance()->getCartItems()->first()->getSubtotal());
            $this->assertEquals(100.00, CartProvider::instance()->getCartItems()->first()->subtotal);
            $this->assertEquals('33,10', CartProvider::instance()->getCartItems()->first()->getTaxAmount());
            $this->assertEquals('133,10', CartProvider::instance()->getCartItems()->first()->getTotal());
            $this->assertEquals(133.100, CartProvider::instance()->getCartItems()->first()->total);

            $this->assertEquals('21,00', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxAmount());
            $this->assertEquals('21', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxRate());
            $this->assertEquals('IVA', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->name);

            $this->assertEquals('12,10', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxAmount());
            $this->assertEquals('10', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxRate());
            $this->assertEquals('OTHER IVA', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->name);
        }
        elseif(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            $this->assertEquals('75,13', CartProvider::instance()->getCartItems()->first()->getSubtotal());
            $this->assertEquals(75.1314800901577797276331693865358829498291015625, CartProvider::instance()->getCartItems()->first()->subtotal);
            $this->assertEquals('24,87', CartProvider::instance()->getCartItems()->first()->getTaxAmount());
            $this->assertEquals('100,00', CartProvider::instance()->getCartItems()->first()->getTotal());
            $this->assertEquals(100, CartProvider::instance()->getCartItems()->first()->total);

            $this->assertEquals('15,78', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxAmount());
            $this->assertEquals('21', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxRate());
            $this->assertEquals('IVA', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->name);

            $this->assertEquals('9,09', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxAmount());
            $this->assertEquals('10', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxRate());
            $this->assertEquals('OTHER IVA', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->name);
        }
    }

    public function testCartCanAddWithSameTaxRules()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('293ad', 'Product 1', 1, 110.99, 1.000, true, [
            new TaxRule('VAT', 21.00),
            new TaxRule('VAT', 10.00)
        ]));

        $this->assertEquals(1, CartProvider::instance()->getCartItems()->first()->taxRules->count());
        $this->assertEquals(31, CartProvider::instance()->getCartItems()->first()->taxRules->first()->taxRate);
        $this->assertEquals(['31'], CartProvider::instance()->getCartItems()->first()->getTaxRates());

        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX)
        {
            $this->assertEquals('110,99', CartProvider::instance()->getCartItems()->first()->getSubtotal());
            $this->assertEquals(110.99, CartProvider::instance()->getCartItems()->first()->subtotal);
            $this->assertEquals('34,41', CartProvider::instance()->getCartItems()->first()->getTaxAmount());
            $this->assertEquals('145,40', CartProvider::instance()->getCartItems()->first()->getTotal());
            $this->assertEquals(145.396899999999988040144671685993671417236328125, CartProvider::instance()->getCartItems()->first()->total);
        }
        elseif(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            $this->assertEquals('84,73', CartProvider::instance()->getCartItems()->first()->getSubtotal());
            $this->assertEquals(84.7251908396946618040601606480777263641357421875, CartProvider::instance()->getCartItems()->first()->subtotal);
            $this->assertEquals('26,26', CartProvider::instance()->getCartItems()->first()->getTaxAmount());
            $this->assertEquals('110,99', CartProvider::instance()->getCartItems()->first()->getTotal());
            $this->assertEquals(110.99, CartProvider::instance()->getCartItems()->first()->total);
        }
    }

    public function testCartCanAddWithTaxRulesWithDifferentPrioritiesAndDiscountSubtotalPercentage()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('293ad', 'Product 1', 1, 100, 1.000, true, [
            new TaxRule('IVA', 21.00, 0, 0),
            new TaxRule('OTHER IVA', 10.00, 1, 1)
        ]));

        CartProvider::instance()->addCartPriceRule(
            new PriceRule(
                'My first price rule',
                'For being a good customer',
                PriceRule::DISCOUNT_SUBTOTAL_PERCENTAGE,
                false,
                null,
                10.00
            )
        );

        $this->assertEquals(2, CartProvider::instance()->getCartItems()->first()->taxRules->count());

        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX)
        {
            $this->assertEquals('100,00', CartProvider::instance()->getCartItems()->first()->getSubtotal());
            $this->assertEquals(100.00, CartProvider::instance()->getCartItems()->first()->subtotal);

            $this->assertEquals('29,79', CartProvider::instance()->getCartItems()->first()->getTaxAmount());
            $this->assertEquals('119,79', CartProvider::instance()->getCartItems()->first()->getTotal());
            $this->assertEquals(119.789999999999992041921359486877918243408203125, CartProvider::instance()->getCartItems()->first()->total);

            $this->assertEquals('18,90', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxAmount());
            $this->assertEquals('21', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxRate());
            $this->assertEquals('IVA', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->name);

            $this->assertEquals('10,89', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxAmount());
            $this->assertEquals('10', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxRate());
            $this->assertEquals('OTHER IVA', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->name);
        }

        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            $this->assertEquals('75,13', CartProvider::instance()->getCartItems()->first()->getSubtotal());
            $this->assertEquals(75.1314800901577797276331693865358829498291015625, CartProvider::instance()->getCartItems()->first()->subtotal);

            $this->assertEquals(10, CartProvider::instance()->getCartItems()->first()->discountsSubtotalPercentage->sum('percentage'));
            $this->assertEquals('10', CartProvider::instance()->getCartItems()->first()->getDiscountSubtotalPercentage());
            $this->assertEquals(7.51314800902, CartProvider::instance()->getCartItems()->first()->discountAmount);
            $this->assertEquals('7,51', CartProvider::instance()->getCartItems()->first()->getDiscountAmount());

            $this->assertEquals('22,38', CartProvider::instance()->getCartItems()->first()->getTaxAmount());

            $this->assertEquals('90,00', CartProvider::instance()->getCartItems()->first()->getTotal());
            $this->assertEquals(90, CartProvider::instance()->getCartItems()->first()->total);

            $this->assertEquals('14,20', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxAmount());
            $this->assertEquals('21', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->getTaxRate());
            $this->assertEquals('IVA', CartProvider::instance()->getTaxRules()->get(md5('IVA' . '0'))->name);

            $this->assertEquals('8,18', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxAmount());
            $this->assertEquals('10', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->getTaxRate());
            $this->assertEquals('OTHER IVA', CartProvider::instance()->getTaxRules()->get(md5('OTHER IVA' . '1'))->name);
        }
    }

    public function testCartCanAddWithTaxRulesWithDiscountTotalPercentage()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('294ad', 'Product 1', 1, 100, 1.000, true, [
            new TaxRule('IVA', 21.00, 0, 0)
        ]));
        CartProvider::instance()->add(new Item('295ad', 'Product 2', 1, 107.69, 1.000, true, [
            new TaxRule('IVA', 21.00, 0, 0)
        ]));

        $this->assertEquals(2, CartProvider::instance()->getCartItems()->count());

        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX)
        {

        }

        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            $this->assertEquals('171,64', CartProvider::instance()->getSubtotal());
            $this->assertEquals('36,05', CartProvider::instance()->getTaxAmount());
            $this->assertEquals('0,00', CartProvider::instance()->getDiscountAmount());
            $this->assertEquals('207,69', CartProvider::instance()->getTotal());

            // apply 10% percentage discount over total
            CartProvider::instance()->addCartPriceRule(
                new PriceRule(
                    'discount 10% percentage',
                    'For being a good customer',
                    PriceRule::DISCOUNT_TOTAL_PERCENTAGE,
                    false,
                    null,
                    10.00
                )
            );

            // check new amounts
            $this->assertEquals('10,00', CartProvider::instance()->getCartItems()->get('92f38118c1830f0893f9d3135bbcc705')->getDiscountAmount());
            $this->assertEquals('10,77', CartProvider::instance()->getCartItems()->get('4213a65a817336f9e62699ee2c1d16f6')->getDiscountAmount());
            $this->assertEquals('171,64', CartProvider::instance()->getSubtotal());
            $this->assertEquals('32,44', CartProvider::instance()->getTaxAmount());
            $this->assertEquals('20,77', CartProvider::instance()->getDiscountAmount());
            $this->assertEquals('186,92', CartProvider::instance()->getTotal());
        }
    }

    public function testCartCanAddVariousProducts()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('294ad', 'Product 1', 1, 100, 1.000, true, [
            new TaxRule('IVA', 21.00, 0, 0)
        ]));
        CartProvider::instance()->add(new Item('295ad', 'Product 2', 1, 107.69, 1.000, true, [
            new TaxRule('IVA', 21.00, 0, 0)
        ]));

        $this->assertEquals(2, CartProvider::instance()->getCartItems()->count());

        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX)
        {

        }
        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            $this->assertEquals('171,64', CartProvider::instance()->getSubtotal());
            $this->assertEquals('36,05', CartProvider::instance()->getTaxAmount());
            $this->assertEquals('0,00', CartProvider::instance()->getDiscountAmount());
            $this->assertEquals('207,69', CartProvider::instance()->getTotal());
        }

        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item('294ad', 'Product 1', 1, 100, 1.000, true, [
            new TaxRule('IVA', 21.00, 0, 0)
        ]));
        CartProvider::instance()->add(new Item('295ad', 'Product 2', 1, 107.69, 1.000, true, [
            new TaxRule('IVA', 21.00, 0, 0)
        ]));

        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITHOUT_TAX)
        {

        }
        if(config('shoppingCart.taxProductPrices') == Cart::PRICE_WITH_TAX)
        {
            $this->assertEquals('343,29', CartProvider::instance()->getSubtotal());
            $this->assertEquals('72,09', CartProvider::instance()->getTaxAmount());
            $this->assertEquals('0,00', CartProvider::instance()->getDiscountAmount());
            $this->assertEquals('415,38', CartProvider::instance()->getTotal());
        }
    }

    public function testCartCanAddMultiple()
    {
        $this->expectsEvents('cart.added');

        for($i = 1; $i <= 5; $i++)
            CartProvider::instance()->add(new Item('293ad' . $i, 'Product', 2, 9.99, 1.000, true));

        $this->assertEquals(5, CartProvider::instance()->getCartItems()->count());
        $this->assertEquals(10, CartProvider::instance()->getQuantity());
    }

    public function testCartCanAddWithNumericId()
    {
        $this->expectsEvents('cart.added');

        CartProvider::instance()->add(new Item(12345, 'Product', 2, 9.99, 1.000, true));
    }

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
//        $this->assertInstanceOf('Syscover\ShoppingCart\Libraries\CartRowOptionsCollection', $cartRow->options);
//        $this->assertEquals('large', $cartRow->options->size);
//        $this->assertEquals('red', $cartRow->options->color);
//    }
//
//    /**
//     * @expectedException Syscover\ShoppingCart\Exceptions\ShoppingcartInvalidItemException
//     */
//    public function testCartThrowsExceptionOnEmptyItem()
//    {
//        $this->expectsEvents('cart.add');
//
//        CartProvider::instance()->add('', '', '', '');
//    }
//
//    /**
//     * @expectedException Syscover\ShoppingCart\Exceptions\ShoppingcartInvalidQtyException
//     */
//    public function testCartThrowsExceptionOnNoneNumericQty()
//    {
//        $this->expectsEvents('cart.add');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 'none-numeric', 9.99);
//    }
//
//    /**
//     * @expectedException Syscover\ShoppingCart\Exceptions\ShoppingcartInvalidPriceException
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
//        $this->assertEquals(2, CartProvider::instance()->getCartItems()->first()->qty);
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
//        $this->assertEquals(2, CartProvider::instance()->getCartItems()->first()->qty);
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
//        $this->assertEquals('Product 2', CartProvider::instance()->getCartItems()->first()->name);
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
//        $this->assertEquals(12345, CartProvider::instance()->getCartItems()->first()->id);
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
//        $this->assertEquals('L', CartProvider::instance()->getCartItems()->first()->options->size);
//    }
//
//    /**
//     * @expectedException Syscover\ShoppingCart\Exceptions\ShoppingcartInvalidRowIDException
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
//        $this->assertTrue(CartProvider::instance()->getCartItems()->isEmpty());
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
//        $this->assertTrue(CartProvider::instance()->getCartItems()->isEmpty());
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
//        $this->assertTrue(CartProvider::instance()->getCartItems()->isEmpty());
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
//        $this->assertInstanceOf('Syscover\ShoppingCart\Libraries\CartCollection', CartProvider::instance()->getCartItems());
//        $this->assertFalse(CartProvider::instance()->getCartItems()->isEmpty());
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
//        $this->assertInstanceOf('Syscover\ShoppingCart\Libraries\CartCollection', CartProvider::instance()->getCartItems());
//        $this->assertTrue(CartProvider::instance()->getCartItems()->isEmpty());
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
//        $this->assertInstanceOf('Syscover\ShoppingCart\Libraries\CartCollection', CartProvider::instance()->getCartItems());
//        $this->assertTrue(CartProvider::instance()->getCartItems()->isEmpty());
//
//        $this->assertInstanceOf('Syscover\ShoppingCart\Libraries\CartCollection', CartProvider::instance('testing')->getCartItems());
//        $this->assertFalse(CartProvider::instance('testing')->getCartItems()->isEmpty());
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
//        $this->assertTrue(CartProvider::instance('firstInstance')->getCartItems()->has('8cbf215baa3b757e910e5305ab981172'));
//        $this->assertFalse(CartProvider::instance('firstInstance')->getCartItems()->has('22eae2b9c10083d6631aaa023106871a'));
//        $this->assertTrue(CartProvider::instance('secondInstance')->getCartItems()->has('22eae2b9c10083d6631aaa023106871a'));
//        $this->assertFalse(CartProvider::instance('secondInstance')->getCartItems()->has('8cbf215baa3b757e910e5305ab981172'));
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
//     * @expectedException Syscover\ShoppingCart\Exceptions\ShoppingcartInstanceException
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
//        $this->assertInstanceOf('Syscover\ShoppingCart\Libraries\CartCollection', CartProvider::instance()->getCartItems());
//    }
//
//    public function testCartCollectionHasCartRowCollection()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//
//        $this->assertInstanceOf('Syscover\ShoppingCart\Libraries\CartRowCollection', CartProvider::instance()->getCartItems()->first());
//    }
//
//    public function testCartRowCollectionHasCartRowOptionsCollection()
//    {
//        $this->expectsEvents('cart.add');
//        $this->expectsEvents('cart.added');
//
//        CartProvider::instance()->add('293ad', 'Product 1', 1, 9.99);
//
//        $this->assertInstanceOf('Syscover\ShoppingCart\Libraries\CartRowOptionsCollection', CartProvider::instance()->getCartItems()->first()->options);
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
//     * @expectedException Syscover\ShoppingCart\Exceptions\ShoppingcartUnknownModelException
//     */
//    public function testCartThrowsExceptionOnUnknownModel()
//    {
//        CartProvider::instance()->associate('NoneExistingModel');
//    }
}