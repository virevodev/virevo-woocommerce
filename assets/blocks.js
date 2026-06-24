/* global wc, wp */
( function () {
	const { registerPaymentMethod } = wc.wcBlocksRegistry;
	const { getSetting } = wc.wcSettings;
	const { createElement } = wp.element;
	const { decodeEntities } = wp.htmlEntities;

	const settings = getSetting( 'virevo_data', {} );
	const label = decodeEntities( settings.title || 'Virement instantané' );
	const description = decodeEntities( settings.description || '' );

	const Content = () => createElement( 'div', null, description );

	registerPaymentMethod( {
		name: 'virevo',
		label: label,
		ariaLabel: label,
		canMakePayment: () => true,
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		supports: {
			features: ( settings.supports && settings.supports.length ) ? settings.supports : [ 'products' ],
		},
	} );
} )();
