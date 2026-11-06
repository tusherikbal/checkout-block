import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { getSetting } from '@woocommerce/settings';
import FieldsEditor from './fields-editor';
import Preview from './preview';
import { STEP_LOCATIONS } from './locations';

// Same rule the frontend applies: a field limited to one address is hidden in the other form.
// WooCommerce draws these with the ids "shipping-zenblch-<key>" and "billing-zenblch-<key>".
const WRAPPERS = [
	'.wc-block-components-text-input',
	'.wc-blocks-components-select',
	'.wc-block-components-checkbox',
];

function scopeCss( fields ) {
	return fields
		.filter( ( field ) => field.scope === 'shipping' || field.scope === 'billing' )
		.flatMap( ( field ) => {
			const hidden = field.scope === 'shipping' ? 'billing' : 'shipping';
			return WRAPPERS.map(
				( wrapper ) => `${ wrapper }:has(#${ hidden }-zenblch-${ field.key })`
			);
		} )
		.join( ',' );
}

export default function Edit( { attributes, setAttributes, clientId } ) {
	const blockProps = useBlockProps();

	const step = useSelect(
		( select ) => {
			const { getBlockParents, getBlockName } = select( 'core/block-editor' );
			// Parents come root-first, so the nearest step is the last match.
			const parent = getBlockParents( clientId )
				.map( getBlockName )
				.reverse()
				.find( ( name ) => STEP_LOCATIONS[ name ] );
			return parent ? STEP_LOCATIONS[ parent ] : null;
		},
		[ clientId ]
	);

	// WooCommerce's editor already draws saved address fields, so only preview the unsaved ones.
	const saved = getSetting( 'zen-block-checkout_data', {} ).savedAddressKeys || [];
	const previewFields =
		step?.location === 'address'
			? attributes.fields.filter( ( field ) => ! saved.includes( field.key ) )
			: attributes.fields;

	return (
		<div { ...blockProps }>
			{ step?.location === 'address' && scopeCss( attributes.fields ) && (
				<style>{ scopeCss( attributes.fields ) + '{display:none!important}' }</style>
			) }
			<InspectorControls>
				<FieldsEditor
					fields={ attributes.fields }
					setFields={ ( fields ) => setAttributes( { fields } ) }
					step={ step }
				/>
			</InspectorControls>
			<Preview fields={ previewFields } />
		</div>
	);
}
