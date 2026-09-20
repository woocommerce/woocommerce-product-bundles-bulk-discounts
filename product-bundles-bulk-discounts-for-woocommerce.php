<?php
/**
* Plugin Name: Product Bundles - Bulk Discounts
* Plugin URI: https://docs.woocommerce.com/document/bundles/bundles-extensions/#bulk-discounts
* Description: Bulk quantity discounts for WooCommerce Product Bundles.
* Version: 2.0.2
* Author: WooCommerce
* Author URI: https://woocommerce.com/
*
* Text Domain: woocommerce-product-bundles-bulk-discounts
* Domain Path: /languages/
*
* Requires at least: 6.2
* Tested up to: 6.6
* Requires PHP: 7.4

* WC requires at least: 8.2
* WC tested up to: 9.1
*
* Copyright: © 2017-2024 WooCommerce.
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_PB_Bulk_Discounts {

	// Limit new input without truncating discounts already stored on products.
	const MAX_DISCOUNT_RULES = 100;
	const MAX_DISCOUNT_BYTES = 32768;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public static $version = '2.0.2';

	/**
	 * Min required PB version.
	 *
	 * @var string
	 */
	public static $req_pb_version = '8.0';

	/**
	 * PB URL.
	 *
	 * @var string
	 */
	private static $pb_url = 'https://woocommerce.com/products/product-bundles/';

	/**
	 * Discount data array for access via filter callbacks -- internal use only.
	 *
	 * @var array
	 */
	public static $discount_data_array = array();

	/**
	 * Total min Qty for access via filter callbacks -- internal use only.
	 *
	 * @var array
	 */
	public static $total_min_quantity = 0;

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename(__FILE__) );
	}

	/**
	 * Plugin path.
	 *
	 * @return string
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Fire in the hole!
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_plugin' ) );
	}

	/**
	 * Hooks.
	 */
	public static function load_plugin() {

		// Check dependencies.
		if ( ! function_exists( 'WC_PB' ) || version_compare( WC_PB()->version, self::$req_pb_version ) < 0 ) {
			add_action( 'admin_notices', array( __CLASS__, 'version_notice' ) );
			return false;
		}

		/*
		 * Admin.
		 */

		// Display bundle quantity discount option.
		add_action( 'woocommerce_bundled_products_admin_config', array( __CLASS__, 'product_bundles_bulk_discount' ), 16 );

		// Save discount data.
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_meta' ) );

		// Export bulk discounts as formatted meta data.
		add_filter( 'woocommerce_product_export_meta_value', array( __CLASS__, 'export_bulk_discounts' ), 10, 2 );

		// Parse and import bulk discounts.
		add_filter( 'woocommerce_product_importer_parsed_data', array( __CLASS__, 'import_bulk_discounts' ), 10, 1 );
		add_filter( 'woocommerce_product_import_process_item_data', array( __CLASS__, 'validate_import_bulk_discounts' ) );

		/*
		 * Cart.
		 */

		// Apply discount to bundled cart items.
		add_filter( 'woocommerce_bundled_cart_item', array( __CLASS__, 'bundled_cart_item_discount' ), -10, 2 );

		// Apply discount to bundle container cart items.
		add_filter( 'woocommerce_bundle_container_cart_item', array( __CLASS__, 'bundle_container_cart_item_discount' ), 10, 2 );

		if ( 'filters' === WC_PB_Product_Prices::get_bundled_cart_item_discount_method() ) {

			// Calculate bundled item bulk discounted price.
			add_filter( 'woocommerce_bundled_item_discount', array( __CLASS__, 'filter_bundled_item_discount' ), 100, 3 );

			// Calculate container item bulk discounted price.
			add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_get_price_cart' ), 100, 2 );
			add_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_get_price_cart' ), 100, 2 );
		}

		/*
		 * Products / Catalog.
		 */

		// Modify the catalog price to include discounts for the default min quantities.
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'bundle_get_discounted_price_html' ), 10000, 2 );

		// Add a suffix to bundled product prices.
		add_action( 'woocommerce_bundled_product_price_filters_added', array( __CLASS__, 'add_bundle_discount_price_html_suffix' ), 10, 1 );
		add_action( 'woocommerce_bundled_product_price_filters_removed', array( __CLASS__, 'remove_bundle_discount_price_html_suffix' ), 10, 1 );

		// Bootstrapping.
		add_action( 'woocommerce_bundle_add_to_cart', array( __CLASS__, 'script' ) );
		add_action( 'woocommerce_composite_add_to_cart', array( __CLASS__, 'script' ) );

		add_filter( 'woocommerce_pb_script_dependencies', array( __CLASS__, 'add_script_dependency' ) );
		add_filter( 'woocommerce_composite_script_dependencies', array( __CLASS__, 'add_script_dependency' ) );

		// Add parameters to bundle price data.
		add_filter( 'woocommerce_bundle_price_data', array( __CLASS__, 'add_discount_data' ), 10, 2 );

		// Parameters passed to the script.
		add_filter( 'woocommerce_bundle_front_end_params', array( __CLASS__, 'add_front_end_params' ), 10, 1 );

		// Declare HPOS compatibility.
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );

		// Localization.
		add_action( 'init', array( __CLASS__, 'localize_plugin' ) );
	}

	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public static function localize_plugin() {
		load_plugin_textdomain( 'woocommerce-product-bundles-bulk-discounts', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Declare HPOS( Custom Order tables) compatibility.
	 *
	 */
	public static function declare_hpos_compatibility() {

		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( __FILE__ ), true );
	}

	/*
	|--------------------------------------------------------------------------
	| Application layer.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Calculates a discounted price based on quantity and a discount data array.
	 *
	 * @param  integer  $total_quantity
	 * @param  array    $discount_data_array
	 * @return mixed
	 */
	public static function get_discount( $total_quantity, $discount_data_array ) {

		$discount = 0.0;

		foreach ( $discount_data_array as $lines ) {

			if ( isset( $lines[ 'quantity_min' ], $lines[ 'quantity_max' ], $lines[ 'discount' ] ) ) {

				$quantity_min = $lines[ 'quantity_min' ];
				$quantity_max = $lines[ 'quantity_max' ];

				if ( $total_quantity >= $quantity_min && $total_quantity <= $quantity_max ) {
					$discount = $lines[ 'discount' ];
					break;
				}
			}
		}

		return $discount;
	}

	/**
	 * Calculates a discounted price based on quantity and a discount data array.
	 *
	 * @param  mixed  $price
	 * @param  float  $discount
	 * @return mixed
	 */
	public static function get_discounted_price( $price, $discount ) {
		return $discount ? round( ( double ) ( ( 100 - $discount ) * $price ) / 100, WC_PB_Product_Prices::get_discounted_price_precision() ) : $price;
	}

	/**
	 * Decodes a discount data array to a human-readable format.
	 *
	 * @param  array   $discount_data_array
	 * @return string  $discount_data_string
	 */
	private static function decode( $discount_data_array ) {

		$discount_data_string = '';

		if ( ! empty( $discount_data_array ) ) {
			foreach ( $discount_data_array as $i => $discount_line) {

				if ( $discount_line[ 'quantity_min' ] === $discount_line[ 'quantity_max'] ) {
					$discount_data_string = $discount_data_string . $discount_line[ 'quantity_min' ] . ' ' . '|' . ' ' . $discount_line[ 'discount' ]. "\n";
				} elseif ( is_infinite( $discount_line[ 'quantity_max' ] ) ) {
					$discount_data_string = $discount_data_string . $discount_line[ 'quantity_min' ] . '+' . ' ' . '|' . ' ' . $discount_line[ 'discount' ]. "\n";
				} else {
					$discount_data_string = $discount_data_string . $discount_line[ 'quantity_min' ] . ' ' . '-' . ' ' . $discount_line[ 'quantity_max' ] . ' ' . '|' . ' ' . $discount_line[ 'discount' ]. "\n";
				}
			}
		}

		return $discount_data_string;
	}

	/**
	 * Encodes $input_data string to array by separating quantity_min, quantity_max and discount.
	 *
	 * @param  string  $input_data
	 * @return array|WP_Error $parsed_discount_data
	 */
	private static function encode( $input_data ) {

		if ( ! is_string( $input_data ) || strlen( $input_data ) > self::MAX_DISCOUNT_BYTES ) {
			return new WP_Error( 'bulk_discount_size', __( 'Bulk discounts were not saved. Use at most 100 rules and 32 KiB of text.', 'woocommerce-product-bundles-bulk-discounts' ) );
		}

		$lines = array_filter( array_map( 'trim', explode( "\n", wc_sanitize_textarea( $input_data ) ) ), 'strlen' );
		if ( count( $lines ) > self::MAX_DISCOUNT_RULES ) {
			return new WP_Error( 'bulk_discount_size', __( 'Bulk discounts were not saved. Use at most 100 rules and 32 KiB of text.', 'woocommerce-product-bundles-bulk-discounts' ) );
		}

		$rules = array();
		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( 2 !== count( $parts ) ) {
				return new WP_Error( 'bulk_discount_format', __( 'Bulk discounts were not saved. Check the quantity ranges and percentages.', 'woocommerce-product-bundles-bulk-discounts' ) );
			}
			$range = array_map( 'trim', explode( '-', $parts[0] ) );
			$min   = $range[0];
			$max   = isset( $range[1] ) ? $range[1] : $min;
			if ( 1 === count( $range ) && '+' === substr( $min, -1 ) ) {
				$min = trim( substr( $min, 0, -1 ) );
				$max = INF;
			}
			if ( count( $range ) > 2 ) {
				return new WP_Error( 'bulk_discount_format', __( 'Bulk discounts were not saved. Check the quantity ranges and percentages.', 'woocommerce-product-bundles-bulk-discounts' ) );
			}
			$rules[] = array( 'quantity_min' => $min, 'quantity_max' => $max, 'discount' => $parts[1] );
		}

		$validated = self::validate_discount_rules( $rules );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		foreach ( $rules as &$rule ) {
			$rule['quantity_min'] = (int) $rule['quantity_min'];
			$rule['quantity_max'] = is_infinite( (float) $rule['quantity_max'] ) ? INF : (int) $rule['quantity_max'];
			$rule['discount']     = (float) $rule['discount'];
		}
		return $rules;
	}

	/**
	 * Validate bounded tier data without changing stored order or numeric types.
	 *
	 * @param array $rules Discount tiers.
	 * @return array|WP_Error
	 */
	private static function validate_discount_rules( $rules ) {

		if ( ! is_array( $rules ) || count( $rules ) > self::MAX_DISCOUNT_RULES || array_values( $rules ) !== $rules ) {
			return new WP_Error( 'bulk_discount_size', __( 'Bulk discounts were not saved. Use at most 100 rules and 32 KiB of text.', 'woocommerce-product-bundles-bulk-discounts' ) );
		}
		foreach ( $rules as $index => $rule ) {
			if ( ! is_array( $rule ) || 3 !== count( $rule ) || ! isset( $rule['quantity_min'], $rule['quantity_max'], $rule['discount'] ) ) {
				return new WP_Error( 'bulk_discount_format', __( 'Bulk discounts were not saved. Check the quantity ranges and percentages.', 'woocommerce-product-bundles-bulk-discounts' ) );
			}
			$min      = $rule['quantity_min'];
			$max      = '' === $rule['quantity_max'] ? INF : $rule['quantity_max'];
			$rules[ $index ]['quantity_max'] = $max;
			$discount = $rule['discount'];
			if ( ! is_numeric( $min ) || ! is_numeric( $max ) || ! is_numeric( $discount )
				|| ! is_finite( (float) $min ) || $min < 0 || $min > PHP_INT_MAX || (int) $min < 0 || floor( (float) $min ) != $min
				|| $max < $min || ( INF !== $max && ( ! is_finite( (float) $max ) || $max > PHP_INT_MAX || (int) $max < 0 || floor( (float) $max ) != $max ) )
				|| ! is_finite( (float) $discount ) || $discount < 0 || $discount > 100 ) {
				return new WP_Error( 'bulk_discount_format', __( 'Bulk discounts were not saved. Check the quantity ranges and percentages.', 'woocommerce-product-bundles-bulk-discounts' ) );
			}
		}

		// Sort a copy: valid CSV round trips retain their original order and types.
		$sorted = $rules;
		usort( $sorted, static function ( $left, $right ) {
			return $left['quantity_min'] <=> $right['quantity_min'];
		} );
		$previous_max = -1;
		foreach ( $sorted as $rule ) {
			if ( $rule['quantity_min'] <= $previous_max ) {
				return new WP_Error( 'bulk_discount_overlap', __( 'Bulk discounts were not saved. Quantity ranges must not overlap.', 'woocommerce-product-bundles-bulk-discounts' ) );
			}
			$previous_max = $rule['quantity_max'];
		}
		return $rules;
	}

	/**
	 * Whether to apply discounts to the base price.
	 *
	 * @param  array    $bundle
	 * @return boolean
	 */
	public static function apply_discount_to_base_price( $bundle ) {
		/**
		 * 'wc_pb_bulk_discount_apply_to_base_price' filter.
		 *
		 * @param  bool               $apply
		 * @param  WC_Product_Bundle  $bundle
		 */
		return apply_filters( 'wc_pb_bulk_discount_apply_to_base_price', false, $bundle );
	}

	/*
	|--------------------------------------------------------------------------
	| Admin and Metaboxes.
	|--------------------------------------------------------------------------
	*/

	/**
	 * PB version check notice.
	 */
	public static function version_notice() {
		echo '<div class="error"><p>' . sprintf( __( '<strong>Product Bundles &ndash; Bulk Discounts</strong> requires <a href="%1$s" target="_blank">WooCommerce Product Bundles</a> version <strong>%2$s</strong> or higher.', 'woocommerce-product-bundles-bulk-discounts' ), self::$pb_url, self::$req_pb_version ) . '</p></div>';
	}

	/**
	 * Add bundle quantity discount option.
	 *
	 * @param  WC_Product  $product_bundle_object
	 * @return void
	 */
	public static function product_bundles_bulk_discount( $product_bundle_object ) {

		$discount_data_array  = $product_bundle_object->get_meta( '_wc_pb_quantity_discount_data', true );
		$discount_data_string = self::decode( $discount_data_array );
		woocommerce_wp_textarea_input( array(
			'id'            => '_wc_pb_quantity_discount_data',
			'wrapper_class' => 'bundled_product_data_field',
			'value'         => $discount_data_string,
			'label'         => __( 'Bulk Discounts', 'woocommerce-product-bundles-bulk-discounts' ),
			'description'   => __( 'Define bulk discounts by adding one discount rule per line in either: i) quantity range format, e.g. <strong>1 - 5 | 5</strong>, ii) single quantity format, e.g. <strong>6 | 10</strong>, or iii) "equal to or higher" format, e.g. <strong>7+ | 15</strong>.<br>Note: Discount amounts are expressed in % only.', 'woocommerce-product-bundles-bulk-discounts' ),
			'rows'          => 3,
			'desc_tip'      => true
		) );
	}

	/**
	 * Save meta.
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function save_meta( $product ) {

		if ( ! isset( $_POST['_wc_pb_quantity_discount_data'] ) ) {
			return;
		}
		$input_data = $_POST['_wc_pb_quantity_discount_data'];
		if ( is_string( $input_data ) && strlen( $input_data ) <= self::MAX_DISCOUNT_BYTES ) {
			$input_data = wp_unslash( $input_data );
		}
		$parsed_discount_data = self::encode( $input_data );
		if ( is_wp_error( $parsed_discount_data ) ) {
			WC_PB_Meta_Box_Product_Data::add_admin_notice( esc_html( $parsed_discount_data->get_error_message() ), 'error' );
			return;
		}
		if ( ! empty( $parsed_discount_data ) ) {
			$product->add_meta_data( '_wc_pb_quantity_discount_data', $parsed_discount_data, true );
		} else {
			$product->delete_meta_data( '_wc_pb_quantity_discount_data' );
		}
	}

	/**
	 * Export bulk discounts as formatted meta data.
	 *
	 * @param  string        $meta_value
	 * @param  WC_Meta_Data  $meta
	 * @return string        $meta_value
	 */
	public static function export_bulk_discounts( $meta_value, $meta ) {

		if ( '_wc_pb_quantity_discount_data' === $meta->key && is_array( $meta_value ) ) {
			foreach ( $meta_value as $index => $rule ) {
				if ( is_array( $rule ) && isset( $rule['quantity_max'] ) && INF === $rule['quantity_max'] ) {
					$meta_value[ $index ]['quantity_max'] = '';
				}
			}
			$meta_value = json_encode( $meta_value );

		}

		return $meta_value;
	}

	/**
	 * Parse and import bulk discounts.
	 *
	 * @param  array  $parsed_data
	 * @return array  $parsed_data
	 */
	public static function import_bulk_discounts( $parsed_data ) {

		if ( empty( $parsed_data['meta_data'] ) ) {
			return $parsed_data;
		}
		foreach ( $parsed_data['meta_data'] as $index => $meta_data ) {
			if ( '_wc_pb_quantity_discount_data' !== $meta_data['key'] ) {
				continue;
			}
			$value = $meta_data['value'];
			if ( ! is_string( $value ) || strlen( $value ) > self::MAX_DISCOUNT_BYTES ) {
				$rules = new WP_Error( 'bulk_discount_size', __( 'Bulk discounts were not saved. Use at most 100 rules and 32 KiB of text.', 'woocommerce-product-bundles-bulk-discounts' ) );
			} else {
				$rules = self::validate_discount_rules( '' === $value ? array() : json_decode( $value, true, 4 ) );
			}
			if ( is_wp_error( $rules ) ) {
				unset( $parsed_data['meta_data'][ $index ] );
				$parsed_data['wc_pb_bulk_discounts_error'] = $rules->get_error_message();
			} else {
				$parsed_data['meta_data'][ $index ]['value'] = $rules;
			}
		}
		return $parsed_data;
	}

	/**
	 * Report invalid tiers through the importer's native per-row error handling.
	 *
	 * @param array $parsed_data Parsed product data.
	 * @return array
	 * @throws Exception When a discount field is invalid.
	 */
	public static function validate_import_bulk_discounts( $parsed_data ) {
		if ( is_array( $parsed_data ) && ! empty( $parsed_data['wc_pb_bulk_discounts_error'] ) && is_string( $parsed_data['wc_pb_bulk_discounts_error'] ) ) {
			throw new Exception( $parsed_data['wc_pb_bulk_discounts_error'] );
		}
		return $parsed_data;
	}


	/*
	|--------------------------------------------------------------------------
	| Cart.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Applies discount on bundled cart items based on overall cart quantity.
	 *
	 * @param  array  $cart_item
	 * @param  array  $bundle
	 * @return array  $cart_item
	 */
	public static function bundled_cart_item_discount( $cart_item, $bundle ) {

		if (  wc_pb_is_bundled_cart_item( $cart_item ) ) {

			$container = wc_pb_get_bundled_cart_item_container( $cart_item );

			$discount_data_array = $container[ 'data' ]->get_meta( '_wc_pb_quantity_discount_data', true );
			$price               = $cart_item[ 'data' ]->get_price();

			if ( $price && ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

				$bundled_items_data = $container[ 'stamp' ];
				$total_quantity     = 0;

				foreach ( $bundled_items_data as $bundled_item_data ) {
					if ( isset( $bundled_item_data[ 'quantity' ] ) && ( ! isset( $bundled_item_data[ 'optional_selected' ] ) || $bundled_item_data[ 'optional_selected' ] === 'yes' ) ) {
						$total_quantity += $bundled_item_data[ 'quantity' ];
					}
				}

				if ( $discount = self::get_discount( $total_quantity, $discount_data_array ) ) {

					if ( 'filters' === WC_PB_Product_Prices::get_bundled_cart_item_discount_method() ) {

						// Store a unique copy of the bundled item to avoid caching issues -- see 'WC_Product_Bundle::get_bundled_item'.

						$bundled_cart_item                = $container[ 'data' ]->get_bundled_item( $cart_item[ 'bundled_item_id' ], 'view', array( 'configuration' => $bundled_items_data ) );
						$bundled_cart_item->bulk_discount = $discount;

						WC_PB()->product_data->set( $cart_item[ 'data' ], 'bundled_cart_item', $bundled_cart_item );

					} else {

						$discounted_price = self::get_discounted_price( $price, $discount );
						$cart_item[ 'data' ]->set_price( $discounted_price );
					}
				}
			}
		}

		return $cart_item;
	}

	/**
	 * Applies discount on bundle container cart item based on overall cart quantity.
	 *
	 * @param  array  $cart_item
	 * @param  array  $bundle
	 * @return array  $cart_item
	 */
	public static function bundle_container_cart_item_discount( $cart_item, $bundle ) {

		if ( wc_pb_is_bundle_container_cart_item( $cart_item ) && self::apply_discount_to_base_price( $bundle ) ) {

			$discount_data_array = $cart_item[ 'data' ]->get_meta( '_wc_pb_quantity_discount_data', true );
			$price               = $cart_item[ 'data' ]->get_price();

			if ( $price && ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

				$bundled_items_data = $cart_item[ 'stamp' ];
				$total_quantity     = 0;

				foreach ( $bundled_items_data as $bundled_item_data ) {
					if ( isset( $bundled_item_data[ 'quantity' ] ) && ( ! isset( $bundled_item_data[ 'optional_selected' ] ) || $bundled_item_data[ 'optional_selected' ] === 'yes' ) ) {
						$total_quantity += $bundled_item_data[ 'quantity' ];
					}
				}
				if ( $discount = self::get_discount( $total_quantity, $discount_data_array ) ) {
					if ( 'filters' === WC_PB_Product_Prices::get_bundled_cart_item_discount_method() ) {
						WC_PB()->product_data->set( $cart_item[ 'data' ], 'bundle_bulk_discount', $discount );
					} else {
						$cart_item[ 'data' ]->set_price( self::get_discounted_price( $price, $discount ) );
					}
				}
			}
		}

		return $cart_item;
	}

	/*
	|--------------------------------------------------------------------------
	| Products / Catalog.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns discounted price based on default min quantities.
	 *
	 * @param  string  $price
	 * @param  object  $product
	 * @return string  $price
	 */
	public static function bundle_get_discounted_price_html( $price, $product ) {

		// If product is bundle then get discount_data_array.
		if ( $product->is_type( 'bundle' ) ) {

			$discount_data_array = $product->get_meta( '_wc_pb_quantity_discount_data', true );

			// If there exists a discount then get all min_quantities of the bundled items.
			if ( ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

				$total_min_quantity = 0;
				$discount_applies   = false;
				$bundled_items      = $product->get_bundled_items();

				// Calculate minimum possible bundled items quantity.
				foreach ( $bundled_items as $bundled_item ) {

					$total_min_quantity += $bundled_item->get_quantity( 'min', array(
						'context'        => 'price',
						'check_optional' => true
					) );
				}

				// Check if the sum of min_quantity exists in a disount line.
				foreach ( $discount_data_array as $line ) {
					if ( isset( $line[ 'quantity_min' ] ) && $total_min_quantity >= $line[ 'quantity_min' ] && $line[ 'discount' ] > 0 ) {
						$discount_applies = true;
					}
				}

				if ( $discount_applies ) {

					self::$discount_data_array = $discount_data_array;
					self::$total_min_quantity  = $total_min_quantity;

					self::add_filters();

					// Remove to prevent infinite loop.
					remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'bundle_get_discounted_price_html' ), 10000, 2 );

					$price = $product->get_price_html();

					// Add again.
					add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'bundle_get_discounted_price_html' ), 10000, 2 );

					self::remove_filters();

					self::$discount_data_array = array();
					self::$total_min_quantity  = 0;
				}
			}
		}

		return $price;
	}

	/**
	 * Add filters to modify products when contained in Bundles.
	 *
	 * @return void
	 */
	public static function add_filters() {
		add_filter( 'woocommerce_bundle_prices_hash', array( __CLASS__, 'filter_bundle_prices_hash' ), 10, 2 );
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 16, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_price' ), 16, 2 );
	}

	/**
	 * Remove filters - @see 'add_filters'.
	 *
	 * @return void
	 */
	public static function remove_filters() {
		remove_filter( 'woocommerce_bundle_prices_hash', array( __CLASS__, 'filter_bundle_prices_hash' ), 10, 2 );
		remove_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 16, 2 );
		remove_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_price' ), 16, 2 );
	}

	/**
	 * Aggregate bulk discount into bundled item discount.
	 *
	 * @since  1.2.0
	 *
	 * @param  float            $discount
	 * @param  WC_Bundled_Item  $bundled_item
	 * @param  string           $context
	 * @return float
	 */
	public static function filter_bundled_item_discount( $discount, $bundled_item, $context ) {

		if ( 'cart' !== $context ) {
			return $discount;
		}

		if ( empty( $bundled_item->bulk_discount ) ) {
			return $discount;
		}

		$discount      = (float) $discount;
		$bulk_discount = (float) $bundled_item->bulk_discount;
		$discount      = $bulk_discount + $discount - ( $discount * $bulk_discount ) / 100;

		return $discount;
	}

	/**
	 * Modify bundle prices hash to go around the runtime cache.
	 *
	 * @param   array              $hash
	 * @param   WC_Product_Bundle  $bundle
	 * @return array
	 */
	public static function filter_bundle_prices_hash( $hash, $bundle ) {
		$hash[ 'discount_data' ] = self::decode( $bundle->get_meta( '_wc_pb_quantity_discount_data', true ) );
		return $hash;
	}

	/**
	 * Computes and returns discounted price.
	 *
	 * @param  mixed   $price
	 * @param  object  $product
	 * @return mixed
	 */
	public static function filter_price( $price, $product ) {

		if ( '' === $price ) {
			return $price;
		}

		$calculate_discount = true;

		// Check if discount is applied to base price as well.
		if ( $product->is_type( 'bundle' ) && false === self::apply_discount_to_base_price( $product ) ) {
			$calculate_discount = false;
		}

		if ( $calculate_discount ) {

			$total_quantity = self::$total_min_quantity;

			if ( $discount = self::get_discount( $total_quantity, self::$discount_data_array ) ) {
				$price = self::get_discounted_price( $price, $discount );
			}
		}

		return $price;
	}

	/**
	 * Filter get_price() calls to include container discounts.
	 *
	 * @since  1.2.0
	 *
	 * @param  double      $price
	 * @param  WC_Product  $product
	 * @param  string      $context
	 * @return double
	 */
	public static function filter_get_price_cart( $price, $product, $context = '' ) {

		$discount = false;

		if ( ! is_null( WC_PB()->product_data->get( $product, 'bundle_bulk_discount' ) ) ) {
			$discount = WC_PB()->product_data->get( $product, 'bundle_bulk_discount' );
		}

		if ( ! $discount ) {
			return $price;
		}

		if ( ! $price ) {
			return $price;
		}

		return self::get_discounted_price( $price, $discount );
	}

	/**
	 * Calls filter to add a suffix to price_html if some discount applies.
	 *
	 * @param  array   $bundled_item
	 * @return void
	 */
	public static function add_bundle_discount_price_html_suffix( $bundled_item ) {

		$bundle              = $bundled_item->get_bundle();
		$discount_data_array = $bundle->get_meta( '_wc_pb_quantity_discount_data', true );

		if ( ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {
			self::$discount_data_array = $discount_data_array;
		}

		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'add_discount_price_html_suffix' ), 100, 2 );
	}

	/**
	 * Adds suffix to price_html.
	 *
	 * @param  string  $price_html
	 * @param  object  $product
	 * @return string  $price_html
	 */
	public static function add_discount_price_html_suffix( $price_html, $product ) {

		if ( ! empty( self::$discount_data_array ) && is_array( self::$discount_data_array ) ) {
			$price_html = sprintf( __( '%s <small>(before discount)</small>', 'woocommerce-product-bundles-bulk-discounts' ), $price_html );
		}

		return $price_html;
	}

	/**
	 * Removes filter.
	 *
	 * @param  array  $bundled_item
	 * @return void
	 */
	public static function remove_bundle_discount_price_html_suffix( $bundled_item ) {
		self::$discount_data_array = array();
		remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'add_discount_price_html_suffix' ), 100, 2 );
	}

	/**
	 * Front-end script.
	 *
	 * @param  array  $dependencies
	 */
	public static function script() {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'wc-pb-bd-add-to-cart', self::plugin_url() . '/assets/js/wc-pb-bd-add-to-cart' . $suffix . '.js', array( 'jquery', 'wc-add-to-cart-variation' ), self::$version, true );
		wp_register_style( 'wc-pb-bd-styles', self::plugin_url() . '/assets/css/wc-pb-bd-styles.css', false, self::$version, 'all' );
		wp_enqueue_style( 'wc-pb-bd-styles' );
	}

	/**
	 * Add the Bulk Discounts script as a dependency to the main Bundle/Composite script.
	 *
	 * @param array $dependencies
	 *
	 */
	public static function add_script_dependency( $dependencies ) {
		$dependencies[] = 'wc-pb-bd-add-to-cart';
		return $dependencies;
	}

	/**
	 * Update settings to add parameters.
	 *
	 * @param  array  $price_data
	 * @param  array  $bundle
	 * @return array
	 */
	public static function add_discount_data( $price_data, $bundle ) {

		$discount_data_array = $bundle->get_meta( '_wc_pb_quantity_discount_data', true );

		if ( ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

			// INF cannot be JSON-encoded :)
			foreach ( $discount_data_array as $line_key => $line ) {
				if ( isset( $line[ 'quantity_max' ] ) && is_infinite( $line[ 'quantity_max' ] ) ) {
					$discount_data_array[ $line_key ][ 'quantity_max' ] = '';
				}
			}

			$apply_discount_to_base_price = self::apply_discount_to_base_price( $bundle );

			$price_data[ 'bulk_discount_data' ] = array(
				'discount_array' => $discount_data_array,
				'discount_base'  => $apply_discount_to_base_price ? 'yes' : 'no'
			);

			// Keep total visible when discounting the base price.
			if ( $apply_discount_to_base_price ) {
				$price_data[ 'raw_bundle_price_max' ] = '';
			}
		}

		$bundled_items = $bundle->get_bundled_items();

		if ( empty( $bundled_items ) ) {
			return;
		}

		foreach ( $bundled_items as $bundled_item ) {
			$price_data[ 'bulk_discounts_on_regular_price' ][ $bundled_item->get_id() ] = $bundled_item->is_discount_allowed_on_sale_price() ? 'no' : 'yes';
		}

		return $price_data;
	}

	/**
	 * Update front-end parameters to add 'After discount'.
	 *
	 * @param  array  $parameter_array
	 * @return array
	 */
	public static function add_front_end_params( $parameter_array ) {

		$parameter_array[ 'i18n_bulk_discount_subtotal' ] = __( 'Subtotal: ', 'woocommerce-product-bundles-bulk-discounts' );
		$parameter_array[ 'i18n_bulk_discount' ]          = __( 'Discount: ', 'woocommerce-product-bundles-bulk-discounts' );
		$parameter_array[ 'i18n_bulk_discount_value' ]    = sprintf( __( '%s%%', 'woocommerce-product-bundles-bulk-discounts' ), '%v' );
		$parameter_array[ 'i18n_bulk_discount_format' ]   = sprintf( _x( '%1$s%2$s', '"Discount" string followed by value', 'woocommerce-product-bundles-bulk-discounts' ), '%s', '%v' );

		if ( ! isset( $parameter_array[ 'discounted_price_decimals' ] ) ) {
			$parameter_array[ 'discounted_price_decimals' ] = WC_PB_Product_Prices::get_discounted_price_precision();
		}

		return $parameter_array;
	}
}

WC_PB_Bulk_Discounts::init();
