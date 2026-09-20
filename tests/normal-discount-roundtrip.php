<?php
/** Ordinary CSV and frontend price-data compatibility for discount tiers. */

define( 'ABSPATH', __DIR__ );
function add_action( $hook_name, $callback ) {}
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	public function __construct( $code, $message ) {}
}

require_once dirname( __DIR__ ) . '/product-bundles-bulk-discounts-for-woocommerce.php';

$meta = (object) array( 'key' => '_wc_pb_quantity_discount_data' );
$rules = array(
	array( 'quantity_min' => 4, 'quantity_max' => 5, 'discount' => 5 ),
	array( 'quantity_min' => 6, 'quantity_max' => 9, 'discount' => 10 ),
	array( 'quantity_min' => 10, 'quantity_max' => INF, 'discount' => 15 ),
);
$exported = WC_PB_Bulk_Discounts::export_bulk_discounts( $rules, $meta );
if ( ! is_string( $exported ) || '' === $exported ) {
	throw new RuntimeException( 'Open-ended tiers must export as JSON.' );
}
$imported = WC_PB_Bulk_Discounts::import_bulk_discounts(
	array( 'meta_data' => array( array( 'key' => $meta->key, 'value' => $exported ) ) )
);
if ( $rules !== $imported['meta_data'][0]['value'] ) {
	throw new RuntimeException( 'Finite and open-ended tiers must survive CSV round trips.' );
}
if ( 15 !== WC_PB_Bulk_Discounts::get_discount( 100, $imported['meta_data'][0]['value'] ) ) {
	throw new RuntimeException( 'The restored open-ended tier must retain its discount.' );
}
if ( INF !== $rules[2]['quantity_max'] ) {
	throw new RuntimeException( 'Export must preserve the stored PHP representation.' );
}
echo "Normal discount CSV round trip passed (4 assertions).\n";

function apply_filters( $hook, $value, $bundle ) { return false; }
class Normal_Discounts_Bundled_Item {
	public function get_id() { return 1; }
	public function is_discount_allowed_on_sale_price() { return true; }
}
class Normal_Discounts_Bundle {
	public $rules;
	public function get_meta( $key, $single ) { return $this->rules; }
	public function contains( $key ) { return false; }
	public function get_bundled_items() { return array( new Normal_Discounts_Bundled_Item() ); }
}
$bundle = new Normal_Discounts_Bundle();
$bundle->rules = $rules;
$price_data = WC_PB_Bulk_Discounts::add_discount_data( array( 'original' => 'keep' ), $bundle );
$frontend_rules = $rules;
$frontend_rules[2]['quantity_max'] = '';
if ( ! isset( $price_data['bulk_discount_data'] ) || array( 'discount_array' => $frontend_rules, 'discount_base' => 'no' ) !== $price_data['bulk_discount_data'] ) {
	throw new RuntimeException( 'Saved tiers must remain available in the frontend payload even when core prices do not use them.' );
}
if ( 'keep' !== $price_data['original'] || array( 1 => 'no' ) !== $price_data['bulk_discounts_on_regular_price'] ) {
	throw new RuntimeException( 'Frontend tier data must preserve other price-data fields.' );
}
if ( INF !== $bundle->rules[2]['quantity_max'] ) {
	throw new RuntimeException( 'Frontend normalization must preserve the stored tier representation.' );
}
echo "Normal frontend discount payload passed (3 assertions).\n";
