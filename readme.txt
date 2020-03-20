=== Product Bundles - Bulk Discounts for WooCommerce ===

Contributors: franticpsyx, SomewhereWarm
Tags: woocommerce, product, bundles, dynamic, pricing, bulk, discount, quantity, tiers, rules
Requires at least: 4.4
Tested up to: 5.3
Requires PHP: 5.6
Stable tag: 1.3.0
WC requires at least: 3.1
WC tested up to: 4.0
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Free mini-extension for WooCommerce Product Bundles that allows you to create dynamic pricing rules and offer bulk quantity discounts.


== Description ==

Free mini-extension for the official [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/?aff=46147&cid=5972457) extension that allows you to create [dynamic pricing](https://docs.woocommerce.com/document/bundles/bundles-use-case-sell-in-bulk/) rules. Use it to offer higher discounts when customers purchase more items in a Product Bundle.

Compared to rule-based or coupon-based approaches, Product Bundles with dynamic pricing rules are:

* Easier to discover. Each Product Bundle is an individual WooCommerce product with its own page in your catalog.
* Easier to set up. Rule-based discount plugins are more complicated and usually require a higher up-front investment.

Additionally, WooCommerce product revenue reports make it easier for you to track the performance of bundles with bulk discount rules.

**Important**: This plugin requires the official [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/?aff=46147&cid=5972457) extension. Before installing this plugin, please ensure that you are running the latest versions of both **WooCommerce** and **WooCommerce Product Bundles**.


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

Want to contribute? Please submit issue reports and pull requests on [GitHub](https://github.com/somewherewarm/woocommerce-product-bundles-bulk-discounts).


== Installation ==

This plugin requires the official [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/?aff=46147&cid=5972457) extension. Before installing this plugin, please ensure that you are running the latest versions of both **WooCommerce** and **WooCommerce Product Bundles**.


== Screenshots ==

1. Creating bulk discount tiers.
2. Purchasing a Product Bundle with bulk quantity discounts.


== Changelog ==

= 1.4.0 =
* Important - Renamed plugin to comply with WordPress.org guidelines.

= 1.3.6 =
* Tweak - Declared support for WP 5.3 and WooCommerce 3.9.

= 1.3.5 =
* Tweak - Updated supported WP/WC versions.

= 1.3.4 =
* Tweak - Removed admin options wrapper div.

= 1.3.3 =
* Tweak - Declare WC 3.5 support.

= 1.3.2 =
* Tweak - Fixed an incorrect gettext string in validation messages.
* Tweak - Added WC 3.3 support.

= 1.3.1 =
* Tweak - Updated plugin headers.
* Tweak - Renamed 'Bundled Products' tab option labels.

= 1.3.0 =
* Fix - Cart validation.
* Tweak - Re-designed validation messages.
* Tweak - Updated validation message strings.

= 1.2.0 =
* Important - Product Bundles v5.5+ required.
* Fix - Product Bundles v5.5 compatibility.

= 1.1.1 =
* Fix - Add-to-cart validation failure when bundle quantity > 1.

= 1.1.0 =
* Fix - WooCommerce v3.0 support.
* Fix - Product Bundles v5.2 support.
* Important - Product Bundles v5.1 support dropped.

= 1.0.6 =
* Fix - Product Bundles v5.0 support.

= 1.0.5 =
* Fix - Load plugin textdomain on init.

= 1.0.4 =
* Fix - Composite Products v3.6 support.
* Fix - Product Bundles v4.14 support. Fix validation notices not displaying on first page load. Requires Product Bundles v4.14.3+.

= 1.0.3 =
* Fix - Composite Products support.

= 1.0.2 =
* Tweak - Bundles with min/max constraints require input: 'Add to cart' button text and behaviour changed.

= 1.0.1 =
* Fix - Accurate 'from:' price calculation based on the defined qty constraints.

= 1.0.0 =
* Initial Release.



== Upgrade Notice ==

= 1.4.0 =
Renamed plugin to comply with WordPress.org guidelines.
