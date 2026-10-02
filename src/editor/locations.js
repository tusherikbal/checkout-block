import { __ } from '@wordpress/i18n';

export const BLOCK = 'zen-block-checkout/custom-fields';

// Which checkout step a block sits in decides where WooCommerce shows its fields.
// The order-information fields have no step block of their own: WooCommerce keeps
// them directly inside the checkout fields block.
export const STEP_LOCATIONS = {
	'woocommerce/checkout-contact-information-block': {
		location: 'contact',
		label: __( 'Contact information', 'zen-block-checkout' ),
		hint: __( 'These fields appear in Contact information, below the email.', 'zen-block-checkout' ),
	},
	'woocommerce/checkout-shipping-address-block': {
		location: 'address',
		label: __( 'Shipping address', 'zen-block-checkout' ),
	},
	'woocommerce/checkout-billing-address-block': {
		location: 'address',
		label: __( 'Billing address', 'zen-block-checkout' ),
	},
	'woocommerce/checkout-fields-block': {
		location: 'order',
		label: __( 'Order information', 'zen-block-checkout' ),
		hint: __( 'These fields appear in the order information area, next to the order note.', 'zen-block-checkout' ),
	},
};

// Default address fields a custom field can be placed after. Keys match WooCommerce's.
export const ADDRESS_ANCHORS = [
	{ value: '', label: __( 'At the end', 'zen-block-checkout' ) },
	{ value: 'country', label: __( 'After Country/Region', 'zen-block-checkout' ) },
	{ value: 'first_name', label: __( 'After First name', 'zen-block-checkout' ) },
	{ value: 'last_name', label: __( 'After Last name', 'zen-block-checkout' ) },
	{ value: 'company', label: __( 'After Company', 'zen-block-checkout' ) },
	{ value: 'address_1', label: __( 'After Address', 'zen-block-checkout' ) },
	{ value: 'address_2', label: __( 'After Apartment, suite, etc.', 'zen-block-checkout' ) },
	{ value: 'city', label: __( 'After City', 'zen-block-checkout' ) },
	{ value: 'state', label: __( 'After State/County', 'zen-block-checkout' ) },
	{ value: 'postcode', label: __( 'After Postal code', 'zen-block-checkout' ) },
	{ value: 'phone', label: __( 'After Phone', 'zen-block-checkout' ) },
];

export const SCOPES = [
	{ value: 'both', label: __( 'Shipping and billing', 'zen-block-checkout' ) },
	{ value: 'shipping', label: __( 'Shipping only', 'zen-block-checkout' ) },
	{ value: 'billing', label: __( 'Billing only', 'zen-block-checkout' ) },
];

export const FIELD_TYPES = [
	{ value: 'text', label: __( 'Text', 'zen-block-checkout' ) },
	{ value: 'select', label: __( 'Dropdown', 'zen-block-checkout' ) },
	{ value: 'email', label: __( 'Email', 'zen-block-checkout' ) },
	{ value: 'tel', label: __( 'Phone', 'zen-block-checkout' ) },
	{ value: 'number', label: __( 'Number', 'zen-block-checkout' ) },
	{ value: 'url', label: __( 'URL', 'zen-block-checkout' ) },
	{ value: 'date', label: __( 'Date', 'zen-block-checkout' ) },
	{ value: 'time', label: __( 'Time', 'zen-block-checkout' ) },
	{ value: 'datetime-local', label: __( 'Date and time', 'zen-block-checkout' ) },
	{ value: 'textarea', label: __( 'Textarea', 'zen-block-checkout' ) },
	{ value: 'radio', label: __( 'Radio buttons', 'zen-block-checkout' ) },
	{ value: 'checkboxes', label: __( 'Checkboxes', 'zen-block-checkout' ) },
];

// Types that need a list of options.
export const OPTION_TYPES = [ 'select', 'radio', 'checkboxes' ];

/**
 * One line about where this step's fields end up. WooCommerce shows an address field in
 * both the shipping and billing form, so what is true depends on each field's "Show in".
 */
export function stepHint( step, fields ) {
	if ( ! step ) {
		return '';
	}
	if ( step.location !== 'address' ) {
		return step.hint;
	}

	const scopes = new Set( fields.map( ( field ) => field.scope || 'both' ) );
	if ( scopes.size === 1 && scopes.has( 'shipping' ) ) {
		return __( 'These fields appear in the shipping address only.', 'zen-block-checkout' );
	}
	if ( scopes.size === 1 && scopes.has( 'billing' ) ) {
		return __( 'These fields appear in the billing address only.', 'zen-block-checkout' );
	}
	if ( scopes.size > 1 ) {
		return __( 'Some fields are limited to one address. Check "Show in" on each field.', 'zen-block-checkout' );
	}
	return __(
		'These fields appear in both the shipping and billing address. To limit one, change "Show in" on that field.',
		'zen-block-checkout'
	);
}

export const WIDTHS = [
	{ value: '', label: __( 'Default', 'zen-block-checkout' ) },
	{ value: 'full', label: __( 'Full width (1 column)', 'zen-block-checkout' ) },
	{ value: 'half', label: __( 'Half width (2 columns)', 'zen-block-checkout' ) },
];
