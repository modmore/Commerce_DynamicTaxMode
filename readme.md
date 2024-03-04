# Dynamic Tax Mode for Commerce


This is an extension for modmore's [Commerce](https://modmore.com/commerce/). 

This module allows a product to switch between inclusive and exclusive tax modes during 
checkout based on a session variable, and provides a custom product type called DynamicTaxModeProduct 
that allows setting both normal pricing and business pricing. The pricing shown to the customer 
depends on the tax mode currently active for that product.

It is available under the MIT License.

Requirements:

- Commerce 1.5+
- PHP 7.4+

To use, install the package from the [modmore package provider](https://modmore.com/about/package-provider/), and enable the module under Extras > Commerce > Configuration > Modules.

## Detailed usage

**Set the session key**

The tax mode changes between `exclusive` and `inclusive` depending on a session value. The default
key for the session variable is `commerce_dynamictaxmode`. If you would like to change it to something
else, you can set it in the module configuration window. Commerce > Configuration > Modules.

**Create products with business pricing**

Create a new product with the type `DynamicTaxModeProduct`, or you could also edit a current product and 
change to that type. Click on the pricing tab and you'll see fields for both normal pricing and business
pricing. Enter the prices and hit save.

**Set the session variable**

If no session variable is set, Dynamic Tax Mode Products will fall back to using the global system setting
`commerce.tax_calculation`. 

Use your custom session key, or the default `commerce_dynamictaxmode`. The value should be set as either `inclusive` or `exclusive`. 

A basic snippet on a product page might be one way of setting the session variable. e.g. 
```
<?php
$_SESSION['commerce_dynamictaxmode'] = 'inclusive';
return '';
```

**Public methods**

`DynamicTaxModeProduct` includes two public methods to assist with getting and saving business pricing programmatically:

- getBusinessPricing(comCurrency $currency): ?ItemPricingInterface
- saveBusinessPricing(ItemPricingInterface $pricing): bool