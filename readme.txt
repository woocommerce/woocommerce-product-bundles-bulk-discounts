=== Product Bundles - Bulk Discounts ===

Contributors: franticpsyx, SomewhereWarm
Tags: woocommerce, product, bundles, bulk, discount, quantity, tiers, rules
Requires at least: 4.4
Tested up to: 6.3
Requires PHP: 5.6
Stable tag: 1.4.1
WC requires at least: 3.1
WC tested up to: 8.0
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Free mini-extension for WooCommerce Product Bundles that allows you to offer bulk quantity discounts.


== Description ==

Free mini-extension for the official [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/) extension that allows you to create [bulk discount](https://docs.woocommerce.com/document/bundles/bundles-use-case-sell-in-bulk/) rules. Use it to offer higher discounts when customers purchase more items in a Product Bundle.

Compared to rule-based or coupon-based approaches, Product Bundles with bulk discount rules are:

* Easier to discover. Each Product Bundle is an individual WooCommerce product with its own page in your catalog.
* Easier to set up. Rule-based discount plugins are more complicated and usually require a higher up-front investment.

Additionally, WooCommerce product revenue reports make it easier for you to track the performance of bundles with bulk discount rules.

**Important**: This plugin requires the official [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/) extension. Before installing this plugin, please ensure that you are running the latest versions of both **WooCommerce** and **WooCommerce Product Bundles**.

**Note**: This experimental plugin has been created to validate and refine a feature that may be rolled into WooCommerce Product Bundles -- or dropped! -- in the future.

**Important**: The code in this plugin is provided "as is". Support via the WordPress.org forum is provided on a **voluntary** basis only. If you have an active subscription for WooCommerce Product Bundles, please be aware that WooCommerce Support may not be able to assist you with this experimental plugin.

== Documentation ==

To add bulk discounts to a Product Bundle:

* Ensure that you are running the latest versions of both **WooCommerce** and **WooCommerce Product Bundles**.
* Install and activate this plugin.
* Navigate to **Product Data > Bundled Products**
* Create some discount tiers using the **Bulk Discounts** field.

Discount tiers are “rules” that associate quantities with discounts. Each rule consists of two parts, separated by a pipe `|` character:

1. A quantity value or range of values, e.g. `1 - 5`,
2. A discount value expressed in `%`, e.g. `10`.

Quantities can be entered in either:

* quantity range format, for example `1 - 5`,
* single quantity format, for example `6`, or
* “equal to or higher” format, for example `7+`.

Here's a ruleset:

`
4 - 5 | 5
6 - 9 | 10
10 + | 15
`

This means that:

* If 4-5 items are chosen, the discount is 5%.
* If 6-9 items are chosen, the discount is bumped to 10%.
* If 10 or more items are chosen, the discount goes up to 15%.

When a Product Bundle with bulk discounts is configured, the applicable discount and total is dynamically calculated and displayed.

By default, bulk discounts are applied to the prices of all bundled products that are **Priced Individually**. Base Regular/Sale Prices are not be discounted. To discount base prices, use the following snippet:

`
add_filter( 'wc_pb_bulk_discount_apply_to_base_price', '__return_true' );
`

Want to contribute? Please submit issue reports and pull requests on [GitHub](https://github.com/somewherewarm/woocommerce-product-bundles-bulk-discounts).


== Installation ==

This plugin requires the official [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/) extension. Before installing this plugin, please ensure that you are running the latest versions of both **WooCommerce** and **WooCommerce Product Bundles**.


== Screenshots ==

1. Creating bulk discount tiers.
2. Purchasing a Product Bundle with bulk quantity discounts.


== Changelog ==

= 1.4.1 =
* Tweak - The Bulk Discounts mini-extension now requires Product Bundles 7.0+.

= 1.4.0 =
* Feature - Added compatibility with the new block-based Single Product Template.
* Feature - Declared compatibility with the new High-Performance Order Tables. 

= 1.3.9 =
* Feature - Added support for the WooCommerce Importer/Exporter.
* Fix - Total Bundle price mismatch between the single product page and the cart when discounts are calculated over bundled items' Regular Prices. 

= 1.3.8 =
* Fix - Undefined 'bundle.price_data.bulk_discount_data' error.

= 1.3.7 =
* Fix - Fixed an issue that could cause unselected optional items to be counted when calculating bulk discounts.
* Fix - Keep running total visible when discounting base prices.

= 1.3.6 =
* Tweak - Made some further changes to round discounted JS prices using 'WC_PB_Product_Prices::get_discounted_price_precision'.

= 1.3.5 =
* Tweak - Round discounted prices using 'WC_PB_Product_Prices::get_discounted_price_precision'.

= 1.3.4 =
* Tweak - Updated supported WordPress and WooCommerce versions.

= 1.3.3 =
* Fix - Catalog price issues affecting products with subscription plans created using All Products for WooCommerce Subscriptions.

= 1.3.2 =
* Tweak - Declared WooCommerce 4.2 compatibility.
* Fix - Bundled product Add-On prices are calculated before discounts in the cart (instead of after).

= 1.3.1 =
* Fix - Declared compatibility with WooCommerce 4.0.
* Fix - Initialized plugin text domain.
* Fix - Moved correct changelog from v1.2.

= 1.3.0 =
* Tweak - Renamed plugin to comply with WordPress.org guidelines.

= 1.2.0 =
* Fix - Added support for Product Bundles 6.0+.

= 1.1.0 =
* Fix - Added support for Product Bundles 5.10+.
* Fix - Added support for Bulk Discounts in Bundles contained in Composite Products.

= 1.0.6 =
* Fix - Updated Bundled items minimum quantity calculation.

= 1.0.5 =
* Tweak - Declare WooCommerce 3.5 compatibility.

= 1.0.4 =
* Tweak - Declare WooCommerce 3.3 compatibility.

= 1.0.3 =
* Fix - Client-side totals calculation incorrect when discount amount resets to 0 after a quantity threshold.

= 1.0.2 =
* Fix - JS error when viewing bundles with empty discount data.

= 1.0.1 =
* Tweak - "Bulk discounts" admin option tooltip.

= 1.0.0 =
* Initial Release.


== Upgrade Notice ==

= 1.3.9 =
Added support for the WooCommerce Importer/Exporter.
