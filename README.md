# WooCommerce Product Bundles - Bulk Discounts

### What's This?

Mini-extension for [WooCommerce Product Bundles](https://woocommerce.com/products/product-bundles/) that allows you offer bulk discounts in Product Bundles by associating bundled product quantities with discount values.

### Adding Bulk Discounts

To add bulk discounts to a Product Bundle, navigate to **Product Data > Bundled Products** and locate the **Bulk discounts** field. Add one discount rule per line using:

* quantity range format - e.g. **1 - 5 | 5**
* single quantity format - e.g. **6 | 10**
* "equal to or higher" format - e.g. **7+ | 15**

<img width="804" alt="Adding bulk discount rules." src="https://user-images.githubusercontent.com/1783726/32772873-fecc3c3e-c92f-11e7-96a5-a3626589ea12.png">

### Bulk Discounts in Product Bundles

When a Product Bundle with bulk discounts is configured, the price total that's normally displayed above the add-to-cart button changes slightly to include some extra information:

* The current **Subtotal** (price before discount).
* The applicable **Discount** amount.
* The final **Total** (price after discount).

<img width="1168" alt="Purchasing a Bundle with bulk discounts." src="https://user-images.githubusercontent.com/1783726/32772911-1d0c0bc0-c930-11e7-9bac-8727a7def01f.png">

### Note

By default, bulk discounts are applied to the prices of all bundled products that are **Priced Individually**.

If a Product Bundle with bulk discount rules has a static/base **Regular Price** and/or **Sale Price**, its static/base price component will remain unchanged.

To discount static/base bundle prices, use the following snippet:

`add_filter( 'wc_pb_bulk_discount_apply_to_base_price', '__return_true' );`

### Important

Requires WooCommerce Product Bundles 5.10+.
