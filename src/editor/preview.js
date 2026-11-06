import { __ } from '@wordpress/i18n';

/*
 * These reuse WooCommerce's own checkout markup and class names so the fields look
 * exactly like the default ones (floating label, select arrow, checkbox).
 * WooCommerce renders its default fields itself, so ours are drawn right after them.
 */

function labelOf( field ) {
	const label = field.label || __( '(untitled field)', 'zen-block-checkout' );
	return field.required ? label : `${ label } ${ __( '(optional)', 'zen-block-checkout' ) }`;
}

function TextPreview( { field } ) {
	const id = `zenblch-preview-${ field.key }`;
	return (
		<div className="wc-block-components-text-input">
			<input type={ field.type } id={ id } readOnly tabIndex={ -1 } />
			<label htmlFor={ id }>{ labelOf( field ) }</label>
		</div>
	);
}

function SelectPreview( { field } ) {
	const id = `zenblch-preview-${ field.key }`;
	const options = ( field.optionsText || '' ).split( /\r?\n/ ).filter( Boolean );
	return (
		<div className="wc-blocks-components-select">
			<div className="wc-blocks-components-select__container">
				<label htmlFor={ id } className="wc-blocks-components-select__label">
					{ field.placeholder || labelOf( field ) }
				</label>
				<select id={ id } className="wc-blocks-components-select__select" tabIndex={ -1 } size={ 1 }>
					{ options.map( ( option ) => (
						<option key={ option }>{ option }</option>
					) ) }
				</select>
				<svg
					className="wc-blocks-components-select__expand"
					viewBox="0 0 24 24"
					width="24"
					height="24"
					aria-hidden="true"
				>
					<path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z" />
				</svg>
			</div>
		</div>
	);
}

function TextareaPreview( { field } ) {
	const id = `zenblch-preview-${ field.key }`;
	return (
		<div className="wc-block-components-text-input">
			<textarea id={ id } rows="3" readOnly tabIndex={ -1 } style={ { width: '100%', paddingTop: 24 } } />
			<label htmlFor={ id }>{ labelOf( field ) }</label>
		</div>
	);
}

function ChoicesPreview( { field } ) {
	const options = ( field.optionsText || '' ).split( /\r?\n/ ).filter( Boolean );
	const type = field.type === 'radio' ? 'radio' : 'checkbox';
	return (
		<fieldset>
			<legend>{ labelOf( field ) }</legend>
			{ options.map( ( option ) => (
				<label key={ option } style={ { display: 'block' } }>
					<input type={ type } disabled /> { option }
				</label>
			) ) }
		</fieldset>
	);
}

function CheckboxPreview( { field } ) {
	return (
		<div className="wc-block-components-checkbox">
			<label>
				<input
					className="wc-block-components-checkbox__input"
					type="checkbox"
					tabIndex={ -1 }
					readOnly
				/>
				<svg
					className="wc-block-components-checkbox__mark"
					aria-hidden="true"
					viewBox="0 0 24 20"
				>
					<path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" />
				</svg>
				<span className="wc-block-components-checkbox__label">
					{ field.label || __( '(untitled field)', 'zen-block-checkout' ) }
				</span>
			</label>
		</div>
	);
}

const PREVIEWS = {
	text: TextPreview,
	email: TextPreview,
	tel: TextPreview,
	number: TextPreview,
	url: TextPreview,
	date: TextPreview,
	time: TextPreview,
	'datetime-local': TextPreview,
	textarea: TextareaPreview,
	radio: ChoicesPreview,
	checkboxes: ChoicesPreview,
	select: SelectPreview,
	checkbox: CheckboxPreview,
};

export default function Preview( { fields } ) {
	if ( ! fields.length ) {
		return (
			<p className="zenblch-hint">
				{ __(
					'Custom fields: select the checkout step and use "Add field" in the sidebar.',
					'zen-block-checkout'
				) }
			</p>
		);
	}

	return (
		<div className="zenblch-preview">
			{ fields.map( ( field ) => {
				const Component = PREVIEWS[ field.type ] || TextPreview;
				return <Component key={ field.key } field={ field } />;
			} ) }
		</div>
	);
}
