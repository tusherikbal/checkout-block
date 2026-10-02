import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import FieldsEditor from './fields-editor';
import { BLOCK, STEP_LOCATIONS } from './locations';

/**
 * Selecting a checkout step shows our controls in its sidebar. The fields live
 * on a Custom Fields block inside that step; it is created on the first "Add field".
 */
function StepPanel( { name, clientId } ) {
	const child = useSelect(
		( select ) =>
			select( 'core/block-editor' )
				.getBlocks( clientId )
				.find( ( block ) => block.name === BLOCK ),
		[ clientId ]
	);
	const { insertBlock, updateBlockAttributes } = useDispatch( 'core/block-editor' );

	const setFields = ( fields ) => {
		if ( child ) {
			updateBlockAttributes( child.clientId, { fields } );
		} else {
			insertBlock( createBlock( BLOCK, { fields } ), undefined, clientId );
		}
	};

	return (
		<InspectorControls>
			<FieldsEditor
				fields={ child?.attributes.fields ?? [] }
				setFields={ setFields }
				step={ STEP_LOCATIONS[ name ] }
			/>
		</InspectorControls>
	);
}

addFilter(
	'editor.BlockEdit',
	'zen-block-checkout/step-panel',
	createHigherOrderComponent(
		( BlockEdit ) => ( props ) => (
			<>
				{ /* Rendered first so our panels sit above the step's own (Address Fields, Feedback...). */ }
				{ props.isSelected && STEP_LOCATIONS[ props.name ] && (
					<StepPanel name={ props.name } clientId={ props.clientId } />
				) }
				<BlockEdit { ...props } />
			</>
		),
		'withZenCheckoutFields'
	)
);
