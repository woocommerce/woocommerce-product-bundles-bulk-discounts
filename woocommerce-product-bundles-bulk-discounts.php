<?php
/*
* Plugin Name: WooCommerce Product Bundles - Bulk Discounts
* Plugin URI: http://woocommerce.com/products/product-bundles
* Description: Bulk discounts for WooCommerce Product Bundles.
* Version: 1.0.0
* Author: SomewhereWarm
* Author URI: http://somewherewarm.gr/
*
* Text Domain: woocommerce-product-bundles-bulk-discounts
* Domain Path: /languages/
*
* Requires at least: 4.1
* Tested up to: 4.8
*
* WC requires at least: 3.0
* WC tested up to: 3.2
*
* Copyright: © 2017 SomewhereWarm SMPC.
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_PB_Quantity_Discount {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public static $version = '1.0.0';

	/**
	 * Min required PB version.
	 *
	 * @var string
	 */
	public static $req_pb_version = '5.6';

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
	public static $total_min_quantity  = 0;

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

		if ( ! function_exists( 'WC_PB' ) || version_compare( WC_PB()->version, self::$req_pb_version ) < 0 ) {
			add_action( 'admin_notices', array( __CLASS__, 'version_notice' ) );
			return false;
		}

		// Display bundle quantity discount option.
		add_action( 'woocommerce_bundled_products_admin_config', array( __CLASS__, 'product_bundles_bulk_discount' ), 16 );

		// Save discount data.
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_meta' ) );

		// Applies discount on bundled cart items based on overall cart quantity.
		add_filter( 'woocommerce_bundled_cart_item', array( __CLASS__, 'bundled_cart_item_discount' ), 10, 2 );

		// Applies discount on bundle container cart item based on overall cart quantity.
		add_filter( 'woocommerce_bundle_container_cart_item', array( __CLASS__, 'bundle_container_cart_item_discount' ), 10, 2 );

		// Returns discounted price based on default min quantities.
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'bundle_get_discounted_price_html' ), 10 , 2 );

		// Calls filter to add a suffix to price_html if some discount applies.
		add_action( 'woocommerce_bundled_product_price_filters_added', array( __CLASS__, 'add_bundle_discount_price_html_suffix' ), 10, 1 );

		// Removes filter.
		add_action( 'woocommerce_bundled_product_price_filters_removed', array( __CLASS__, 'remove_add_discount_price_html_suffix_filter' ), 10, 1 );

		// Bootstrapping.
		add_action( 'woocommerce_bundle_add_to_cart', array( __CLASS__, 'script' ) );
        add_action( 'woocommerce_composite_add_to_cart', array( __CLASS__, 'script' ) );

		// Add parameters to bundle price data.
		add_filter( 'woocommerce_bundle_price_data', array( __CLASS__, 'add_discount_data' ), 10, 2 );

		// // Update front-end parameters to add 'After discount'.
		add_filter( 'woocommerce_bundle_front_end_params', array( __CLASS__, 'add_front_end_params' ), 10, 1 );

	}

	/**
	 * PB version check notice.
	 */
	public static function version_notice() {
		echo '<div class="error"><p>' . sprintf( __( '<strong>WooCommerce Product Bundles &ndash; Bulk Discounts</strong> requires Product Bundles <strong>%s</strong> or higher.', 'woocommerce-product-bundles-bulk-discounts' ), self::$req_pb_version ) . '</p></div>';
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
			'label'         => __( 'Quantity Discounts', 'woocommerce-product-bundles-bulk-discounts' ),
			'desc_tip'      => true,
			'description'   => __( 'Define bulk discounts here. Add one discount per line using either a quantity range format, e.g. <strong>1 - 5 | 5%</strong> or a single quantity format, e.g. <strong>6 | 10%</strong>. ', 'woocommerce-product-bundles-bulk-discounts' )
		) );
	}

	/**
	 * Save meta.
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function save_meta( $product ) {

		$input_data           = $_POST[ '_wc_pb_quantity_discount_data' ];
		$parsed_discount_data = self::encode( $input_data );

		if ( ! empty( $_POST[ '_wc_pb_quantity_discount_data' ] ) ) {
			$product->add_meta_data( '_wc_pb_quantity_discount_data', $parsed_discount_data, true );
		} else {
			$product->delete_meta_data( '_wc_pb_quantity_discount_data' );
		}
	}

	/**
	 * Decodes $discount_data_array to string by separating quantity_min, quantity_max and discount.
	 *
	 * @param  array 	   $discount_data_array
	 * @return string  	   $discount_data_string
	 */
	private static function decode( $discount_data_array ) {

		$discount_data_string = '';

		if ( ! empty( $discount_data_array ) ) {
			foreach ( $discount_data_array as $i => $discount_line) {

				if ( $discount_line[ 'quantity_min' ] === $discount_line[ 'quantity_max'] ) {
					$discount_data_string  = $discount_data_string . $discount_line[ 'quantity_min' ] . ' ' . '|' . ' ' . $discount_line[ 'discount' ]. "\n";
				} elseif ( is_infinite( $discount_line[ 'quantity_max' ] ) ) {
					$discount_data_string  = $discount_data_string . $discount_line[ 'quantity_min' ] . '+' . ' ' . '|' . ' ' . $discount_line[ 'discount' ]. "\n";
				} else {
					$discount_data_string  = $discount_data_string . $discount_line[ 'quantity_min' ] . ' ' . '-' . ' ' . $discount_line[ 'quantity_max' ] . ' ' . '|' . ' ' . $discount_line[ 'discount' ]. "\n";
				}
			}
		}

		return $discount_data_string;
	}

	/**
	 * Encodes $input_data string to array by separating quantity_min, quantity_max and discount.
	 *
	 * @param  string  $input_data
	 * @return array   $parsed_discount_data
	 */
	private static function encode( $input_data ) {

		$parsed_discount_data = array();
		$input_data           = wc_sanitize_textarea( $input_data );

		if ( ! empty( $input_data ) ) {

			$input_data = array_filter( array_map( 'trim', explode( "\n", $input_data ) ) );

			// Explode based on "|".
			foreach ( $input_data as $discount_line ) {

				$line_error_notice_added        = false;
				$discount_line_seperator_pieces = array_map( 'trim', explode( "|", $discount_line ) );

				// Validate that only one "|" exist in each line.
				if ( 2 !== sizeof( $discount_line_seperator_pieces ) ) {

					if ( ! $line_error_notice_added ) {
						WC_PB_Meta_Box_Product_Data::add_admin_notice( sprintf( __( 'Line <strong> %s </strong> not saved. Invalid format.', 'woocommerce-product-bundles-bulk-discounts' ), $discount_line ), 'error' );
						$line_error_notice_added = true;
						continue;
					}
				}

				$discount_line_dash_pieces = array_map( 'trim', explode( "-", $discount_line_seperator_pieces[ 0 ] ) );

				// Validate that only at most one "-" exist in each line.
				if ( sizeof( $discount_line_dash_pieces ) > 2 ) {

					if ( ! $line_error_notice_added ) {

						WC_PB_Meta_Box_Product_Data::add_admin_notice( sprintf( __( 'Line <strong> %s </strong> not saved. Invalid format.', 'woocommerce-product-bundles-bulk-discounts' ), $discount_line ), 'error' );
						$line_error_notice_added = true;
						continue;
					}

				} else {

					if ( 2 === sizeof( $discount_line_dash_pieces ) ) {

						$quantity_min = $discount_line_dash_pieces[0];
						$quantity_max = $discount_line_dash_pieces[1];

					} else {

						$quantity_min = $discount_line_dash_pieces[0];
						$quantity_max = $quantity_min;

						if ( '+' === substr( $quantity_min, -1 ) ) {
							$quantity_min = rtrim( $quantity_min, '+ ' );
							$quantity_max = INF;
						}
					}

					if ( is_numeric( $quantity_min ) && is_numeric( $quantity_max )  ) {

						if ( ! empty( $parsed_discount_data ) ) {

							// Check for overlap.
							foreach ( $parsed_discount_data as $lines ) {

								if ( $lines[ 'quantity_min' ] <= $quantity_min && $lines[ 'quantity_max' ] >= $quantity_min ) {

									if ( ! $line_error_notice_added ) {

										WC_PB_Meta_Box_Product_Data::add_admin_notice( sprintf( __( 'Line <strong> %s </strong> not saved. Overlapping data.', 'woocommerce-product-bundles-bulk-discounts' ), $discount_line ), 'error' );
										$line_error_notice_added = true;
										continue 2;
									}

								} elseif ( $lines[ 'quantity_min' ] <= $quantity_max && $lines[ 'quantity_max' ] >= $quantity_max ) {

									if ( ! $line_error_notice_added ) {

										WC_PB_Meta_Box_Product_Data::add_admin_notice( sprintf( __( 'Line <strong> %s </strong> not saved. Overlapping data.', 'woocommerce-product-bundles-bulk-discounts' ), $discount_line ), 'error' );
										$line_error_notice_added = true;
										continue 2;
									}

								} elseif ( $lines[ 'quantity_min' ] >= $quantity_min && $lines[ 'quantity_max' ] <= $quantity_max ) {

									if ( ! $line_error_notice_added ) {
										WC_PB_Meta_Box_Product_Data::add_admin_notice( sprintf( __( 'Line <strong> %s </strong> not saved. Overlapping data.', 'woocommerce-product-bundles-bulk-discounts' ), $discount_line ), 'error' );
										$line_error_notice_added = true;
										continue 2;
									}
								}
							}
						}

						if ( 0 > $discount_line_seperator_pieces[1] || 100 < $discount_line_seperator_pieces[1] ) {

							if ( ! $line_error_notice_added ) {

								WC_PB_Meta_Box_Product_Data::add_admin_notice( sprintf( __( 'Line <strong> %s </strong> not saved. Invalid discount.', 'woocommerce-product-bundles-bulk-discounts' ), $discount_line ), 'error' );
								$line_error_notice_added = true;
								continue;
							}
						}

						if ( is_infinite( $quantity_max ) ) {

							$parsed_discount_data[] = array(
							'quantity_min' => intval( $quantity_min ),
							'quantity_max' => INF,
							'discount'     => floatval( $discount_line_seperator_pieces[1])
							);

						} else {

							$parsed_discount_data[] = array(
							'quantity_min' => intval( $quantity_min ),
							'quantity_max' => intval( $quantity_max ),
							'discount'     => floatval( $discount_line_seperator_pieces[1])
							);
						}


					// Non numeric data entered.
					} else {

						if ( ! $line_error_notice_added ) {

							WC_PB_Meta_Box_Product_Data::add_admin_notice( sprintf( __( 'Line <strong> %s </strong> not saved. Invalid format.', 'woocommerce-product-bundles-bulk-discounts' ), $discount_line ), 'error' );
							$line_error_notice_added = true;
							continue;
						}
					}
				}
			}
		}

		return $parsed_discount_data;
	}

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
					if ( isset( $bundled_item_data[ 'quantity' ] ) ) {
						$total_quantity += $bundled_item_data[ 'quantity' ];
					}
				}

				$discounted_price = self::calculate_discount( $price, $total_quantity, $discount_data_array );
				$cart_item[ 'data' ]->set_price( $discounted_price );

			}
		}

		return $cart_item;
	}

	/**
	 * Calculates the discount based on the overall cart item quantity and returns discounted price.
	 *
	 * @param  mixed    $price
	 * @param  integer  $total_quantity
	 * @param  array    $discount_data_array
	 * @return mixed    $price
	 */
	public static function calculate_discount( $price, $total_quantity, $discount_data_array ) {

		foreach ( $discount_data_array as $lines ) {

			if ( isset( $lines[ 'quantity_min' ], $lines[ 'quantity_max' ], $lines[ 'discount' ] ) ) {

				$quantity_min = $lines[ 'quantity_min' ];
				$quantity_max = $lines[ 'quantity_max' ];
				$discount     = $lines[ 'discount' ];

				if ( $total_quantity >= $quantity_min && $total_quantity <= $quantity_max ) {
					$price = (double) ( ( 100 - $discount ) * $price ) / 100 ;
					break;
				}
			}
		}

		return $price;
	}

	/**
	 * Applies discount on bundle container cart item based on overall cart quantity.
	 *
	 * @param  array  $cart_item
	 * @param  array  $bundle
	 * @return array  $cart_item
	 */
	public static function bundle_container_cart_item_discount( $cart_item, $bundle ) {

		if ( wc_pb_is_bundle_container_cart_item( $cart_item ) && self::apply_discount_to_base_price( $bundle) ) {

			$discount_data_array = $cart_item[ 'data' ]->get_meta( '_wc_pb_quantity_discount_data', true );
			$price               = $cart_item[ 'data' ]->get_price();

			if ( $price && ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

				$bundled_items_data = $cart_item[ 'stamp' ];
				$total_quantity     = 0;

				foreach ( $bundled_items_data as $bundled_item_data ) {
					if ( isset( $bundled_item_data[ 'quantity' ] ) ) {
						$total_quantity += $bundled_item_data[ 'quantity' ];
					}
				}

				$discounted_price = self::calculate_discount( $price, $total_quantity, $discount_data_array );
				$cart_item[ 'data' ]->set_price( $discounted_price );
			}
		}

		return $cart_item;
	}

	/**
	 * Returns discounted price based on default min quantities.
	 *
	 * @param  string  $price
	 * @param  object  $product
	 * @return string  $price
	 */
	public static function bundle_get_discounted_price_html( $price, $product ) {

		$product_id = $product->get_id();

		// If product is bundle then get discount_data_array.
		if ( $product->is_type( 'bundle' ) ) {

			$bundle              = wc_get_product( $product_id );
			$discount_data_array = $bundle->get_meta( '_wc_pb_quantity_discount_data', true );

			// If there exists a discount then get all min_quantities of the bundled items.
			if ( ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

				$total_min_quantity = 0;
				$discount_applies   = false;
				$bundled_items      = $bundle->get_bundled_items();

				foreach ( $bundled_items as $value ) {
					$total_min_quantity += $value->get_quantity( 'min' );
				}

				// Check if the sum of min_quantity exists in a disount line.
				foreach ( $discount_data_array as $line ) {
					if ( isset( $line[ 'quantity_min' ] ) && $total_min_quantity >= $line[ 'quantity_min' ] ) {
						$discount_applies = true;
					}
				}

				if ( $discount_applies ) {

					self::$discount_data_array = $discount_data_array;
					self::$total_min_quantity  = $total_min_quantity;

					self::add_filters();

					// Remove to prevent infinite loop.
					remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'bundle_get_discounted_price_html' ), 10, 2 );

					$price = $bundle->get_price_html();

					// Add again.
					add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'bundle_get_discounted_price_html' ), 10, 2 );

					self::remove_filters();

					self::$discount_data_array = array();
					self::$total_min_quantity  = 0;
				}
			}
		}

		return $price;
	}

	/**
	 * Compoutes and returns discounted price.
	 *
	 * @param  mixed   $price
	 * @param  object  $product
	 * @return mixed   $price
	 */
	public static function get_discounted_price( $price, $product ) {

		$calculate_discount = true;

		// Check if discount is applied to base price as well.
		if ( $product->is_type( 'bundle' ) && false === self::apply_discount_to_base_price( $product ) ) {
			$calculate_discount = false;
		}

		if ( $calculate_discount ) {
			$total_quantity = self::$total_min_quantity;
			$price          = self::calculate_discount( $price, $total_quantity, self::$discount_data_array );

		}

		return $price;
	}

	/**
	 * Determines if discount is applied to base price.
	 *
	 * @param  array    $bundle
	 * @return boolean
	 */
	public static function apply_discount_to_base_price( $bundle ) {
		return apply_filters( 'wc_pb_bulk_discount_apply_to_base_price', false, $bundle );
	}

	/**
	 * Add filters to modify products when contained in Bundles.
	 *
	 * @return void
	 */
	public static function add_filters() {
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'get_discounted_price' ), 16, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'get_discounted_price' ), 16, 2 );
	}

	/**
	 * Remove filters - @see 'add_filters'.
	 *
	 * @return void
	 */
	public static function remove_filters() {
		remove_filter( 'woocommerce_product_get_price', array( __CLASS__, 'get_discounted_price' ), 16, 2 );
		remove_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'get_discounted_price' ), 16, 2 );
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
	 * @param  array   $bundled_item
	 * @return void
	 */
	public static function remove_add_discount_price_html_suffix_filter( $bundled_item ) {
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

		wp_register_script( 'wc-pb-bd-add-to-cart', self::plugin_url() . '/assets/js/wc-pb-bd-add-to-cart' . $suffix . '.js', array(), self::$version );
		wp_enqueue_script( 'wc-pb-bd-add-to-cart' );

		wp_register_style( 'wc-pb-bd-styles', self::plugin_url() . '/assets/css/wc-pb-bd-styles.css', false, self::$version, 'all' );
		wp_enqueue_style( 'wc-pb-bd-styles' );
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

			$price_data[ 'bulk_discount_data' ] = array(
				'discount'       => '',
				'discount_array' => $discount_data_array,
				'discount_base'  => self::apply_discount_to_base_price( $bundle ) ? 'yes' : 'no'
			);
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

		return $parameter_array;
	}
}

WC_PB_Quantity_Discount::init();
