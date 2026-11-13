import { createRoot, useEffect, useState } from '@wordpress/element';
import { getSetting } from '@woocommerce/settings';

/*
 * WooCommerce can only draw text inputs, dropdowns and checkboxes. For a textarea, radio
 * buttons or a group of checkboxes, the field is still registered as text (or a dropdown),
 * so WooCommerce keeps its position, validation, saving and order display. Here the native
 * control is hidden and replaced by ours, which writes its value back into the native one.
 * Styles for these live in styles.js.
 */

// React listens to "input" on text fields and "change" on dropdowns, and ignores a plain `.value = x`.
function setNative( el, value ) {
	const proto = el.tagName === 'SELECT' ? window.HTMLSelectElement.prototype : window.HTMLInputElement.prototype;
	Object.getOwnPropertyDescriptor( proto, 'value' ).set.call( el, value );
	el.dispatchEvent( new window.Event( el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true } ) );
}

// WooCommerce sets the native value from its own state (saved address, autofill) without an event, so poll.
function useNativeValue( el ) {
	const [ value, setValue ] = useState( el.value );

	useEffect( () => {
		const timer = window.setInterval( () => {
			if ( ! el.isConnected ) {
				window.clearInterval( timer );
				return;
			}
			setValue( ( current ) => ( current === el.value ? current : el.value ) );
		}, 250 );
		return () => window.clearInterval( timer );
	}, [ el ] );

	return [
		value,
		( next ) => {
			setValue( next );
			setNative( el, next );
		},
	];
}

function Textarea( { el, skin } ) {
	const [ value, update ] = useNativeValue( el );
	const id = el.id + '-zenblch';
	return (
		<div className="zenblch-skin">
			<label htmlFor={ id }>{ skin.label }</label>
			<textarea id={ id } rows="4" value={ value } onChange={ ( e ) => update( e.target.value ) } />
		</div>
	);
}

function Radio( { el, skin } ) {
	const [ value, update ] = useNativeValue( el );
	return (
		<div className="zenblch-skin">
			<fieldset>
				<legend>{ skin.label }</legend>
				{ skin.options.map( ( option ) => (
					<label key={ option } className="zenblch-choice">
						<input
							type="radio"
							name={ el.id + '-zenblch' }
							checked={ value === option }
							onChange={ () => update( option ) }
						/>
						{ option }
					</label>
				) ) }
			</fieldset>
		</div>
	);
}

function Checkboxes( { el, skin } ) {
	const [ value, update ] = useNativeValue( el );
	const ticked = value ? value.split( ',' ).map( ( v ) => v.trim() ) : [];
	const toggle = ( option ) => {
		const next = ticked.includes( option )
			? ticked.filter( ( v ) => v !== option )
			: [ ...ticked, option ];
		// Keep the configured order so the stored value does not depend on click order.
		update( skin.options.filter( ( o ) => next.includes( o ) ).join( ', ' ) );
	};
	return (
		<div className="zenblch-skin">
			<fieldset>
				<legend>{ skin.label }</legend>
				{ skin.options.map( ( option ) => (
					<label key={ option } className="zenblch-choice">
						<input
							type="checkbox"
							checked={ ticked.includes( option ) }
							onChange={ () => toggle( option ) }
						/>
						{ option }
					</label>
				) ) }
			</fieldset>
		</div>
	);
}

// No control at all: a heading and some text. The native field behind it is never filled in.
function Info( { skin } ) {
	return (
		<div className="zenblch-skin zenblch-info">
			{ skin.label && <h4>{ skin.label }</h4> }
			{ ( skin.text || '' )
				.split( /\n+/ )
				.filter( Boolean )
				.map( ( line, index ) => (
					<p key={ index }>{ line }</p>
				) ) }
		</div>
	);
}

const SKINS = { textarea: Textarea, radio: Radio, checkboxes: Checkboxes, info: Info };

function enhance( skins ) {
	Object.entries( skins ).forEach( ( [ id, skin ] ) => {
		const suffix = id.replace( '/', '-' );
		document
			.querySelectorAll( `input[id$="${ suffix }"], select[id$="${ suffix }"]` )
			.forEach( ( el ) => {
				if ( el.dataset.zenblchSkin ) {
					return;
				}
				el.dataset.zenblchSkin = '1';

				// Hide only the control: the wrapper also holds WooCommerce's validation message.
				const control = el.tagName === 'SELECT' ? el.closest( '.wc-blocks-components-select' ) || el : el;
				const nativeLabel = control.parentNode.querySelector( `label[for="${ el.id }"]` );
				control.style.display = 'none';
				if ( nativeLabel ) {
					nativeLabel.style.display = 'none';
				}

				const mount = document.createElement( 'div' );
				control.parentNode.insertBefore( mount, control );
				const Skin = SKINS[ skin.type ];
				createRoot( mount ).render( <Skin el={ el } skin={ skin } /> );
			} );
	} );
}

export function startSkins() {
	const skins = getSetting( 'zen-block-checkout_data', {} ).skins || {};
	if ( ! Object.keys( skins ).length ) {
		return;
	}

	// WooCommerce renders the form after load and again when the country changes.
	let queued = false;
	const run = () => {
		queued = false;
		enhance( skins );
	};
	new window.MutationObserver( () => {
		if ( ! queued ) {
			queued = true;
			window.requestAnimationFrame( run );
		}
	} ).observe( document.body, { childList: true, subtree: true } );
	run();
}
