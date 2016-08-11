<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shopping Cart
    |--------------------------------------------------------------------------
    |
    | Config file
    | If you have installed market package, productPricesValues and shippingPricesValues translations are defined on market config file
    |
    */

    // 1 excluding tax, 2 including tax
    'taxProductPrices'              => env('TAX_PRODUCT_PRICES', 1),
    'taxShippingPrices'             => env('TAX_SHIPPING_PRICES', 1),

    // Display prices
    'taxProductDisplayPrices'       => env('TAX_PRODUCT_DISPLAY_PRICES', 1),
    'taxShippingDisplayPrices'      => env('TAX_SHIPPING_DISPLAY_PRICES', 1),
];