import { __ } from '@wordpress/i18n';
import { Button, Notice, PanelBody } from '@wordpress/components';
import FieldEditor from './field-editor';
import { stepHint } from './locations';

// The key becomes part of the saved order meta key, so it is generated once and never edited.
const newField = () => ( {
	key: 'f' + Math.random().toString( 36 ).slice( 2, 8 ),
	type: 'text',
	label: '',
	required: false,
	placeholder: '',
	optionsText: '',
} );

/**
 * The sidebar controls, shared by the Custom Fields block itself and by the
 * panel shown when its parent checkout step is selected.
 */
export default function FieldsEditor( { fields, setFields, step } ) {
	const update = ( index, patch ) =>
		setFields(
			fields.map( ( field, i ) => ( i === index ? { ...field, ...patch } : field ) )
		);

	const remove = ( index ) => setFields( fields.filter( ( _, i ) => i !== index ) );

	const move = ( index, direction ) => {
		const target = index + direction;
		if ( target < 0 || target >= fields.length ) {
			return;
		}
		const next = [ ...fields ];
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		setFields( next );
	};

	// One wrapper, not a fragment: the sidebar slot appends each top-level child separately, so a field
	// added later would land after WooCommerce's own panels (Feedback...) instead of with the others.
	return (
		<div className="zenblch-panels">
			<PanelBody title={ __( 'Zen custom fields', 'zen-block-checkout' ) }>
				{ step && (
					<p>
						{ __( 'Shown in:', 'zen-block-checkout' ) } <strong>{ step.label }</strong>
					</p>
				) }
				{ step && (
					<Notice status="info" isDismissible={ false }>
						{ stepHint( step, fields ) }
					</Notice>
				) }
			</PanelBody>
			{ fields.map( ( field, index ) => (
				<FieldEditor
					key={ field.key }
					field={ field }
					isAddress={ step?.location === 'address' }
					isFirst={ index === 0 }
					isLast={ index === fields.length - 1 }
					onChange={ ( patch ) => update( index, patch ) }
					onRemove={ () => remove( index ) }
					onMove={ ( direction ) => move( index, direction ) }
				/>
			) ) }
			{ /* Below the list, so it stays next to where the new field appears. */ }
			<div className="zenblch-add">
				<Button
					variant="primary"
					icon="plus"
					onClick={ () => setFields( [ ...fields, newField() ] ) }
				>
					{ __( 'Add field', 'zen-block-checkout' ) }
				</Button>
			</div>
		</div>
	);
}
