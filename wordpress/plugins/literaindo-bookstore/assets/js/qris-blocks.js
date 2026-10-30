/* Mendaftarkan QRIS BTN ke checkout berbasis blok WooCommerce (tanpa build step). */
( function () {
	var registry = window.wc && window.wc.wcBlocksRegistry;
	var wcSettings = window.wc && window.wc.wcSettings;
	if ( ! registry || ! wcSettings ) {
		return;
	}

	var el = window.wp.element.createElement;
	var decode = window.wp.htmlEntities.decodeEntities;
	var settings = wcSettings.getSetting( 'literaindo_qris_data', {} );
	var label = decode( settings.title || 'QRIS' );

	var Content = function () {
		return el( 'div', null, decode( settings.description || '' ) );
	};

	registry.registerPaymentMethod( {
		name: 'literaindo_qris',
		label: el( 'span', null, label ),
		ariaLabel: label,
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () {
			return true;
		},
		supports: { features: settings.supports || [ 'products' ] },
	} );
} )();
