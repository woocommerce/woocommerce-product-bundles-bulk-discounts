<?php

define( 'ABSPATH', __DIR__ );

function add_action( $hook_name, $callback ) {
}

function maybe_unserialize( $value ) {
	if ( ! is_string( $value ) ) {
		return $value;
	}

	$unserialized = @unserialize( trim( $value ) );
	return false === $unserialized && 'b:0;' !== trim( $value ) ? $value : $unserialized;
}

function assert_same( $expected, $actual, $message ) {
	$GLOBALS['bulk_discount_assertions'] = 1 + ( $GLOBALS['bulk_discount_assertions'] ?? 0 );
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message );
	}
}

class WC_PB_Bulk_Discounts_Unserialization_Probe {

	public static $wakeup_count = 0;

	public function __wakeup() {
		++self::$wakeup_count;
	}
}

class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = '' ) { return $text; }
function wc_sanitize_textarea( $value ) { return trim( $value ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }

class WC_PB_Meta_Box_Product_Data {
	public static $notices = array();
	public static function add_admin_notice( $message, $type ) { self::$notices[] = $message; }
}
class Bulk_Discounts_Test_Product {
	public $data;
	public function add_meta_data( $key, $value, $unique ) { $this->data = $value; }
	public function delete_meta_data( $key ) { $this->data = null; }
}


require_once dirname( __DIR__ ) . '/product-bundles-bulk-discounts-for-woocommerce.php';

$bulk_discounts_meta = (object) array(
	'key' => '_wc_pb_quantity_discount_data',
);

$serialized_object = serialize( new WC_PB_Bulk_Discounts_Unserialization_Probe() );
$exported_value     = WC_PB_Bulk_Discounts::export_bulk_discounts( $serialized_object, $bulk_discounts_meta );

assert_same( 0, WC_PB_Bulk_Discounts_Unserialization_Probe::$wakeup_count, 'Export must not unserialize object payloads.' );
assert_same( $serialized_object, $exported_value, 'Export must preserve unexpected scalar values.' );

$discounts = array(
	array(
		'quantity_min' => 1,
		'quantity_max' => 5,
		'discount'     => 10,
	),
);

$exported_value = WC_PB_Bulk_Discounts::export_bulk_discounts( $discounts, $bulk_discounts_meta );
assert_same( json_encode( $discounts ), $exported_value, 'Export must encode discount arrays as JSON.' );

$other_meta = (object) array(
	'key' => '_unrelated_meta',
);

assert_same( $discounts, WC_PB_Bulk_Discounts::export_bulk_discounts( $discounts, $other_meta ), 'Export must preserve unrelated metadata.' );

$parsed_data = array(
	'meta_data' => array(
		array(
			'key'   => '_wc_pb_quantity_discount_data',
			'value' => $exported_value,
		),
	),
);

$imported_data = WC_PB_Bulk_Discounts::import_bulk_discounts( $parsed_data );
assert_same( $discounts, $imported_data['meta_data'][0]['value'], 'Exported discounts must import as the original array.' );

echo "Bulk discount export tests passed.\n";

$product = new Bulk_Discounts_Test_Product();
$product->data = $discounts;
$_POST['_wc_pb_quantity_discount_data'] = implode( "\n", array_map( static function ( $i ) { return $i . ' | 5'; }, range( 1, 101 ) ) );
WC_PB_Bulk_Discounts::save_meta( $product );
assert_same( $discounts, $product->data, 'Over-limit editor input must preserve saved discounts.' );
assert_same( true, ! empty( WC_PB_Meta_Box_Product_Data::$notices ), 'Rejected editor input needs a notice.' );

echo "Bulk discount admission tests passed.\n";

// Bound admission without discarding the last valid configuration.
foreach ( array( array(), str_repeat( ' ', 32769 ), '1 - 5 | 2' . "\n" . '5 - 8 | 3', '5 - 1 | 2', '1 | 101', '1 | text', '1.5 | 2', '1e309 | 2', '1 | 1e309' ) as $invalid ) {
	$product->data = $discounts;
	$_POST['_wc_pb_quantity_discount_data'] = $invalid;
	WC_PB_Bulk_Discounts::save_meta( $product );
	assert_same( $discounts, $product->data, 'Invalid editor input must preserve the previous tiers.' );
}
$_POST['_wc_pb_quantity_discount_data'] = "2 - 5 | 5.5\n1 | 1\n6+ | 7";
WC_PB_Bulk_Discounts::save_meta( $product );
assert_same( array(
	array( 'quantity_min' => 2, 'quantity_max' => 5, 'discount' => 5.5 ),
	array( 'quantity_min' => 1, 'quantity_max' => 1, 'discount' => 1.0 ),
	array( 'quantity_min' => 6, 'quantity_max' => INF, 'discount' => 7.0 ),
), $product->data, 'Valid ranges preserve entry order and native integer/float representation.' );
assert_same( 5.5, WC_PB_Bulk_Discounts::get_discount( 5, $product->data ), 'Inclusive range boundary still selects the same discount.' );
assert_same( 7.0, WC_PB_Bulk_Discounts::get_discount( 100, $product->data ), 'The existing open-ended tier still applies.' );

$_POST['_wc_pb_quantity_discount_data'] = "4 - 5 | 5\n6 - 9 | 10\n10 + | 15";
WC_PB_Bulk_Discounts::save_meta( $product );
$documented_rules = array(
	array( 'quantity_min' => 4, 'quantity_max' => 5, 'discount' => 5.0 ),
	array( 'quantity_min' => 6, 'quantity_max' => 9, 'discount' => 10.0 ),
	array( 'quantity_min' => 10, 'quantity_max' => INF, 'discount' => 15.0 ),
);
assert_same( $documented_rules, $product->data, 'The documented spaced open-ended syntax remains valid.' );
$decode = new ReflectionMethod( WC_PB_Bulk_Discounts::class, 'decode' );
$decode->setAccessible( true );
$_POST['_wc_pb_quantity_discount_data'] = $decode->invoke( null, $product->data );
WC_PB_Bulk_Discounts::save_meta( $product );
assert_same( $documented_rules, $product->data, 'Existing tiers must survive the editor display and re-save round trip.' );

$limit_rules = array();
foreach ( range( 1, 100 ) as $quantity ) {
	$limit_rules[] = array( 'quantity_min' => $quantity, 'quantity_max' => $quantity, 'discount' => 5 );
}
$_POST['_wc_pb_quantity_discount_data'] = implode( "\n", array_map( static function ( $i ) { return $i . ' | 5'; }, range( 1, 100 ) ) );
WC_PB_Bulk_Discounts::save_meta( $product );
assert_same( 100, count( $product->data ), 'The rule-count boundary remains valid.' );
unset( $_POST['_wc_pb_quantity_discount_data'] );
$before = $product->data;
WC_PB_Bulk_Discounts::save_meta( $product );
assert_same( $before, $product->data, 'An absent editor field must not change stored data.' );
$_POST['_wc_pb_quantity_discount_data'] = '';
WC_PB_Bulk_Discounts::save_meta( $product );
assert_same( null, $product->data, 'An explicitly empty editor field still removes tiers.' );

function import_discount_value( $value ) {
	return WC_PB_Bulk_Discounts::import_bulk_discounts( array( 'meta_data' => array(
		array( 'key' => '_unrelated', 'value' => 'Keep me' ),
		array( 'key' => '_wc_pb_quantity_discount_data', 'value' => $value ),
	) ) );
}
$imported = import_discount_value( json_encode( array_reverse( $limit_rules ) ) );
assert_same( array_reverse( $limit_rules ), $imported['meta_data'][1]['value'], 'CSV validates a sorted copy without reordering valid tiers.' );
assert_same( $imported, WC_PB_Bulk_Discounts::validate_import_bulk_discounts( $imported ), 'Valid parsed rows pass native row validation unchanged.' );
$too_many = $limit_rules;
$too_many[] = array( 'quantity_min' => 101, 'quantity_max' => 101, 'discount' => 5 );
foreach ( array( json_encode( $too_many ), str_repeat( ' ', 32769 ), '{', 'null', '[{"quantity_min":1,"quantity_max":2,"discount":101}]', '[{"quantity_min":1,"quantity_max":2,"discount":1},{"quantity_min":0,"quantity_max":3,"discount":2}]', '[{"quantity_min":1,"quantity_max":2,"discount":1,"extra":"ignored?"}]' ) as $invalid ) {
	$imported = import_discount_value( $invalid );
	assert_same( array( 'key' => '_unrelated', 'value' => 'Keep me' ), $imported['meta_data'][0], 'Invalid discounts leave unrelated parsed metadata intact.' );
	assert_same( false, isset( $imported['meta_data'][1] ), 'Rejected data must not reach product metadata.' );
	$rejected = false;
	try {
		WC_PB_Bulk_Discounts::validate_import_bulk_discounts( $imported );
	} catch ( Exception $error ) {
		$rejected = true;
	}
	assert_same( true, $rejected, 'Invalid CSV tiers must fail the row through the native importer catch.' );
}
assert_same( array(), import_discount_value( '' )['meta_data'][1]['value'], 'An explicitly empty CSV field clears tiers.' );
assert_same( 5, WC_PB_Bulk_Discounts::get_discount( 101, $too_many ), 'Legacy saved tiers remain readable even above the new write limit.' );

function apply_filters( $hook, $value, $bundle ) { return $GLOBALS['test_discount_base']; }
class Bulk_Discounts_Test_Bundled_Item {
	public function get_id() { return 1; }
	public function is_discount_allowed_on_sale_price() { return true; }
}
class Bulk_Discounts_Test_Bundle {
	public $individual = false;
	public $rules;
	public function get_meta( $key, $single ) { return $this->rules; }
	public function contains( $key ) { return $this->individual; }
	public function get_bundled_items() { return array( new Bulk_Discounts_Test_Bundled_Item() ); }
}
$bundle = new Bulk_Discounts_Test_Bundle();
$bundle->rules = $too_many;
$GLOBALS['test_discount_base'] = false;
$price_data = WC_PB_Bulk_Discounts::add_discount_data( array( 'original' => 'keep' ), $bundle );
assert_same( false, isset( $price_data['bulk_discount_data'] ), 'Do not send tiers when neither individual nor base prices use them.' );
assert_same( 'keep', $price_data['original'], 'Preserve caller-owned price data.' );
assert_same( array( 1 => 'no' ), $price_data['bulk_discounts_on_regular_price'], 'Keep the existing per-item price-data contract.' );
$bundle->individual = true;
$price_data = WC_PB_Bulk_Discounts::add_discount_data( array(), $bundle );
assert_same( $too_many, $price_data['bulk_discount_data']['discount_array'], 'Do not truncate active legacy tiers and change storefront prices.' );
$bundle->individual = false;
$GLOBALS['test_discount_base'] = true;
$price_data = WC_PB_Bulk_Discounts::add_discount_data( array(), $bundle );
assert_same( 'yes', $price_data['bulk_discount_data']['discount_base'], 'The existing base-price filter still enables frontend tiers.' );
assert_same( '', $price_data['raw_bundle_price_max'], 'Base-price discounts retain the visible-total contract.' );

foreach ( array( null, false, 'custom value', array( 'wc_pb_bulk_discounts_error' => array() ) ) as $filtered ) {
	assert_same( $filtered, WC_PB_Bulk_Discounts::validate_import_bulk_discounts( $filtered ), 'The new filter preserves unexpected third-party values.' );
}
echo 'Bulk discount checks passed: ' . $GLOBALS['bulk_discount_assertions'] . " assertions.\n";
