
;( function ( $, window, document, undefined ) {

	$( '.bundle_data' )

	.on( 'woocommerce-product-bundle-initializing', function( event, composite ) {

	// 		composite.filters.add_filter( 'composite_price_html', function( price_html, view, price_data_array ) {

	// 			// Avoid execution when in recursion.
	// 			if ( typeof( price_data_array ) === 'undefined' || ( typeof( price_data_array ) !== 'undefined' && ! price_data_array.applied_discount ) ) {

	// 				var model                 = view.model,
	// 					price_data            = model.price_data,
	// 					total_quantity_object = model.price_data.quantities,
	// 					global_discount_array = composite.settings.global_discount_array,
	// 					discount 			  = 0,
	// 					total_quantity        = 0;

	// 				// If the Quantity Discounts field is not empty.
	// 				if ( global_discount_array.length > 0 ) {

	// 					// Sum quantity of each Component.
	// 					for ( var component in total_quantity_object ) {
	// 						total_quantity += total_quantity_object[ component ];
	// 					}

	// 					// Check if there exists a discount for that quantity
	// 					for ( var i = 0; i < global_discount_array.length; i++ ) {
	// 						if ( total_quantity >= global_discount_array[ i ][ 'quantity_min' ] && total_quantity <= global_discount_array[ i ][ 'quantity_max' ] ) {
	// 							discount = global_discount_array[ i ][ 'discount' ];
	// 						}
	// 					}

	// 					if ( discount > 0 ) {

	// 						price_data = $.extend( true, {}, composite.data_model.price_data );

	// 						$.each( composite.get_components(), function( index, component ) {
	// 							price_data.prices[ component.component_id ] = price_data.prices[ component.component_id ] * ( 1 - discount / 100 );
	// 						} );

	// 						// Determine if discount should be applied to base price.
	// 						if ( composite.settings.apply_discount_to_base ) {
	// 							price_data.base_price = price_data.base_price * ( 1 - discount / 100 );
	// 						}

	// 						price_data_array = composite.data_model.calculate_subtotals( false, price_data );

	// 						var totals = composite.data_model.calculate_totals( price_data );
	// 						price_data.totals = totals;

	// 						// Avoid displaying regular price crossed out.
	// 						price_data.totals.regular_price = price_data.price;
	// 						price_data.applied_discount     = true;

	// 						var discount_string        = '<span class="discount">' + wc_composite_params.i18n_bulk_discount + '</span>',
	// 							discount_value         = '<span class="discount-amount">' + wc_composite_params.i18n_bulk_discount_value.replace( '%v', wc_cp_number_round( discount, 2 ) ) + '</span>',
	// 							discount_html          = wc_composite_params.i18n_bulk_discount_format.replace( '%s', discount_string ).replace( '%v' , discount_value ),
	// 							discount_html          = '<span class="price-discount">' + discount_html + '</span>',
	// 							$price_html            = $( price_html ).wrapInner( '<span class="price-subtotal"></span>' ),
	// 							$discounted_price_html = $( view.get_price_html( price_data ) ).wrapInner( '<span class="price-total"></span>' );

	// 						// Modify existing "Total:" to "Subtotal:".
	// 						$price_html.find( 'span.total' ).text( wc_composite_params.i18n_bulk_discount_subtotal );

	// 						// Append "Discount:" line.
	// 						$price_html.append( discount_html );

	// 						// Append new "Total:" line.
	// 						$price_html.append( $discounted_price_html.html() );

	// 						price_html = $price_html.prop( 'outerHTML' );
	// 					}
	// 				}
	// 			}

	// 			return price_html;

	// 		}, 10, this );

	 	} );

} ) ( jQuery, window, document );
