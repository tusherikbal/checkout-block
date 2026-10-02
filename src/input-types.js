import { getSetting } from '@woocommerce/settings';

/**
 * WooCommerce registers number/email/date... fields as plain text, but its form
 * renders any non-dropdown field as <input type={ field.type }>. Giving the field
 * its real type here is all it takes for the browser to show the right input.
 */
export function applyInputTypes() {
	const types = getSetting( 'zen-block-checkout_data', {} ).inputTypes || {};
	const fields = getSetting( 'defaultFields', {} );

	Object.entries( types ).forEach( ( [ id, type ] ) => {
		if ( fields[ id ] ) {
			fields[ id ].type = type;
		}
	} );
}
