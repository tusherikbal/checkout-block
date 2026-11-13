import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { ADDRESS_ANCHORS, FIELD_TYPES, OPTION_TYPES, SCOPES, WIDTHS } from './locations';

export default function FieldEditor( {
	field,
	isAddress,
	isFirst,
	isLast,
	onChange,
	onRemove,
	onMove,
} ) {
	const title = field.label || __( '(untitled field)', 'zen-block-checkout' );
	// A brand-new field starts open. This must not follow the label, or the panel
	// would close as soon as the first letter is typed.
	const [ isOpen, setIsOpen ] = useState( ! field.label );

	return (
		<PanelBody title={ title } opened={ isOpen } onToggle={ () => setIsOpen( ! isOpen ) }>
			<SelectControl
				label={ __( 'Type', 'zen-block-checkout' ) }
				value={ field.type }
				options={ FIELD_TYPES }
				onChange={ ( type ) => onChange( { type } ) }
			/>
			<TextControl
				label={
					field.type === 'info'
						? __( 'Heading', 'zen-block-checkout' )
						: __( 'Label', 'zen-block-checkout' )
				}
				value={ field.label }
				onChange={ ( label ) => onChange( { label } ) }
			/>
			{ field.type === 'info' && (
				<TextareaControl
					label={ __( 'Text', 'zen-block-checkout' ) }
					help={ __( 'Shown as plain text. A new line starts a new paragraph.', 'zen-block-checkout' ) }
					value={ field.text || '' }
					onChange={ ( text ) => onChange( { text } ) }
				/>
			) }
			{ field.type !== 'info' && (
				<ToggleControl
					label={
						field.type === 'checkbox'
							? __( 'Must be ticked', 'zen-block-checkout' )
							: __( 'Required', 'zen-block-checkout' )
					}
					checked={ !! field.required }
					onChange={ ( required ) => onChange( { required } ) }
				/>
			) }
			{ ( field.type === 'select' || field.type === 'country' ) && (
				<TextControl
					label={ __( 'Placeholder', 'zen-block-checkout' ) }
					value={ field.placeholder }
					onChange={ ( placeholder ) => onChange( { placeholder } ) }
				/>
			) }
			{ OPTION_TYPES.includes( field.type ) && (
				<TextareaControl
					label={ __( 'Options', 'zen-block-checkout' ) }
					help={
						field.type === 'checkboxes'
							? __( 'One option per line. Commas are removed.', 'zen-block-checkout' )
							: __( 'One option per line.', 'zen-block-checkout' )
					}
					value={ field.optionsText }
					onChange={ ( optionsText ) => onChange( { optionsText } ) }
				/>
			) }
			<SelectControl
				label={ __( 'Width', 'zen-block-checkout' ) }
				value={ field.width || '' }
				options={ WIDTHS }
				onChange={ ( width ) => onChange( { width } ) }
			/>
			{ isAddress && (
				<SelectControl
					label={ __( 'Show in', 'zen-block-checkout' ) }
					value={ field.scope || 'both' }
					options={ SCOPES }
					onChange={ ( scope ) => onChange( { scope } ) }
				/>
			) }
			{ isAddress && (
				<SelectControl
					label={ __( 'Position', 'zen-block-checkout' ) }
					help={ __(
						'Fields in the same spot keep the order shown in this list.',
						'zen-block-checkout'
					) }
					value={ field.after || '' }
					options={ ADDRESS_ANCHORS }
					onChange={ ( after ) => onChange( { after } ) }
				/>
			) }
			<div className="zenblch-actions">
				<Button
					icon="arrow-up-alt2"
					label={ __( 'Move up', 'zen-block-checkout' ) }
					disabled={ isFirst }
					onClick={ () => onMove( -1 ) }
				/>
				<Button
					icon="arrow-down-alt2"
					label={ __( 'Move down', 'zen-block-checkout' ) }
					disabled={ isLast }
					onClick={ () => onMove( 1 ) }
				/>
				<Button isDestructive variant="tertiary" onClick={ onRemove }>
					{ __( 'Delete', 'zen-block-checkout' ) }
				</Button>
			</div>
		</PanelBody>
	);
}
