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
		// Sous le minimum, l'API refuserait le paiement : on masque le moyen de
		// paiement. total_price est en unités mineures (centimes pour l'euro).
		canMakePayment: ( { cartTotals } ) => {
			const min = settings.minAmountCents || 0;
			if ( ! cartTotals || ! min ) {
				return true;
			}
			const unit = cartTotals.currency_minor_unit;
			const cents = parseInt( cartTotals.total_price, 10 ) * Math.pow( 10, 2 - ( unit === undefined ? 2 : unit ) );
			return cents >= min;
		},
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		supports: {
			features: ( settings.supports && settings.supports.length ) ? settings.supports : [ 'products' ],
		},
	} );
} )();
