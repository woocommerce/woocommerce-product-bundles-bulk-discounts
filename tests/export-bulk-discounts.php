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
