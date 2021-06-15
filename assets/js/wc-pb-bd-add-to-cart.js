
;( function( $ ) {

	var PB_Integration = function( bundle ) {

		/**
		 * Round discounted price.
		 */
		this.round_price = function( number ) {
			return wc_pb_number_round( number, wc_bundle_params.discounted_price_decimals );
		};

		/**
		 * 'bundle_subtotals_data' filter callback.
		 */
		this.filter_bundle_totals = function( totals, bundle_price_data, bundle, qty ) {

			if ( typeof bundle_price_data.bulk_discount_data === 'undefined' || false === bundle_price_data.bulk_discount_data ) {
				return totals;
			}

			var self           = this,
			    quantities     = bundle_price_data.quantities,
			    discount_data  = bundle_price_data.bulk_discount_data.discount_array,
			    discount       = 0,
			    total_quantity = 0;

			if ( discount_data.length > 0 ) {

				// Sum quantities.
				for ( var bundled_item_id in quantities ) {
					total_quantity += quantities[ bundled_item_id ];
				}

				// Check if there's a discount for that quantity.
				for ( var i = 0; i < discount_data.length; i++ ) {
					if ( typeof( discount_data[ i ].quantity_min ) !== 'undefined' && typeof( discount_data[ i ].quantity_max ) !== 'undefined' &&  typeof( discount_data[ i ].discount ) !== 'undefined' ) {
						if ( total_quantity >= discount_data[ i ].quantity_min && ( total_quantity <= discount_data[ i ].quantity_max || '' === discount_data[ i ].quantity_max ) ) {
							discount = discount_data[ i ].discount;
						}
					}
				}

				bundle.price_data.bulk_discount_data.discount = discount;

				if ( discount > 0 ) {

					var price_data = $.extend( true, {}, bundle_price_data );

					$.each( bundle.bundled_items, function( index, bundled_item ) {
						if ( 'yes' === price_data.bulk_discounts_on_regular_price[ bundled_item.bundled_item_id ] ) {
							price_data.prices[ bundled_item.bundled_item_id ] = self.round_price( price_data.regular_prices[ bundled_item.bundled_item_id ] * ( 1 - discount / 100 ) );
						} else {
							price_data.prices[ bundled_item.bundled_item_id ] = self.round_price( price_data.prices[ bundled_item.bundled_item_id ] * ( 1 - discount / 100 ) );
						}
					} );

					// Determine if discount should be applied to the base price.
					if ( 'yes' === bundle.price_data.bulk_discount_data.discount_base && price_data.base_price ) {
						price_data.base_price = self.round_price( Number( price_data.base_price ) * ( 1 - discount / 100 ) );
					}

					// Prevent infinite loop.
					price_data.bulk_discount_data = false;

					price_data = bundle.calculate_subtotals( false, price_data, qty );
					price_data = bundle.calculate_totals( price_data );

					totals = price_data.totals;
				}
			}

			return totals;
		};

		/**
		 * 'bundle_total_price_html' filter callback.
		 */
		this.filter_bundle_total_price_html = function( price_html, bundle ) {

			if ( typeof bundle.price_data.bulk_discount_data === 'undefined' ) {
				return price_html;
			}

			if ( bundle.price_data.bulk_discount_data.discount && bundle.price_data.subtotals.price !== bundle.price_data.totals.price ) {

				var price_html_subtotals = '',
				    price_data_subtotals = $.extend( true, {}, bundle.price_data );

				/*
				 * Recalculate price html to strikeout the subtotals' price.
				 */

				price_data_subtotals.totals.regular_price = price_data_subtotals.subtotals.price;

				price_html = bundle.get_price_html( price_data_subtotals );

				/*
				 * Now generate the initial price html again.
				 */

				price_data_subtotals.totals            = price_data_subtotals.subtotals;
				price_data_subtotals.show_total_string = 'yes';

				price_html_subtotals = bundle.get_price_html( price_data_subtotals );

				var discount_string        = '<span class="discount">' + wc_bundle_params.i18n_bulk_discount + '</span>',
				    discount_value         = '<span class="discount-amount">' + wc_bundle_params.i18n_bulk_discount_value.replace( '%v', wc_pb_number_round( bundle.price_data.bulk_discount_data.discount, 2 ) ) + '</span>',
				    discount_html_inner    = wc_bundle_params.i18n_bulk_discount_format.replace( '%s', discount_string ).replace( '%v' , discount_value ),
				    discount_html          = '<span class="price-discount">' + discount_html_inner + '</span>',
				    $discounted_price_html = $( price_html ).wrapInner( '<span class="price-total"></span>' ),
				    $price_html            = $( price_html_subtotals ).wrapInner( '<span class="price-subtotal"></span>' );

				// Modify existing "Total:" to "Subtotal:".
				$price_html.find( 'span.total' ).text( wc_bundle_params.i18n_bulk_discount_subtotal );

				// Append "Discount:" line.
				$price_html.append( discount_html );

				// Append new "Total:" line.
				$price_html.append( $discounted_price_html.html() );

				price_html = $price_html.prop( 'outerHTML' );
			}

			return price_html;
		};

		// Init.
		this.initialize = function() {

			if ( typeof bundle.price_data.bulk_discount_data === 'undefined' ) {
				return;
			}

			/**
			 * Filter totals using 'totals' JS filter.
			 */
			bundle.filters.add_filter( 'bundle_totals', this.filter_bundle_totals, 10, this );

			/**
			 * Filter price total html using 'bundle_total_price_html' JS filter.
			 */
			bundle.filters.add_filter( 'bundle_total_price_html', this.filter_bundle_total_price_html, 10, this );

			/**
			 * When discounting base prices, always force totals recalculation when changes happen, otherwise the totals will never be updated because subtotals don't change.
			 */
			if ( 'yes' === bundle.price_data.bulk_discount_data.discount_base && bundle.price_data.base_price ) {
				bundle.$bundle_data.on( 'woocommerce-product-bundle-update', function( event ) {
					bundle.dirty_subtotals = true;
				} );
			}
		};
	};

	// Hook into Bundles.
	$( '.bundle_form .bundle_data' ).on( 'woocommerce-product-bundle-initializing', function( event, bundle ) {
		var pb_integration = new PB_Integration( bundle );
		pb_integration.initialize();

	} );

	// Hook into Bundles loaded by Composites.
	$( '.composite_component' ).on( 'wc-composite-component-loaded', function( event, step ) {

		if ( 'bundle' === step.get_selected_product_type() ) {

			var bundle = step.get_bundle_script(),
			    pb_integration = new PB_Integration( bundle );

			pb_integration.initialize();
		}
	} );

} ) ( jQuery );
