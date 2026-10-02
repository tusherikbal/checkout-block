import { getSetting } from '@woocommerce/settings';

/*
 * Styles injected into a document: the checkout page itself, and in the editor the
 * canvas iframe (a stylesheet enqueued in the parent page never reaches the iframe).
 */

const STYLE_ID = 'zbc-style';

// Types WooCommerce styles for text inputs but knows nothing about.
const EXTRA_TYPES = [ 'date', 'time', 'datetime-local' ];

const BASE = `
/* The text-input wrapper sets line-height:0, nowrap and absolutely positions every <label>. */
.zbc-skin{line-height:1.5;white-space:normal;margin:0 0 8px}
.wc-block-components-text-input .zbc-skin label,
.wc-block-components-text-input .zbc-skin legend,
.zbc-skin label,.zbc-skin legend{position:static;transform:none;top:auto;right:auto;max-width:none;overflow:visible;text-overflow:clip;white-space:normal;cursor:auto;display:block;margin:0 0 6px;padding:0;color:inherit;font-size:.875em;line-height:1.5;opacity:.8}
.wc-block-components-text-input .zbc-skin label.zbc-choice,
.zbc-skin label.zbc-choice{display:flex;align-items:center;gap:8px;margin:4px 0;font-size:1em;opacity:1;cursor:pointer}
.zbc-skin textarea{width:100%;box-sizing:border-box;padding:12px;border:1px solid color-mix(in srgb,currentColor 80%,transparent);border-radius:4px;font:inherit;line-height:1.5;background:#fff;color:#2b2d2f}
.zbc-skin fieldset{border:0;margin:0;padding:0}
.zbc-skin input[type=checkbox],.zbc-skin input[type=radio]{width:auto;height:auto;margin:0;padding:0;position:static}
/* The label of an empty date/time input would sit on top of the browser's own mm/dd/yyyy text. */
.wc-block-components-text-input:has(input[type=date],input[type=time],input[type=datetime-local]) label{top:6px;transform:scale(.82)}
.wc-block-components-text-input input[type=date],.wc-block-components-text-input input[type=time],.wc-block-components-text-input input[type=datetime-local]{padding:24px 9px 8px}
`;

const WRAPPERS = [
	'.wc-block-components-text-input',
	'.wc-block-components-select-input',
	'.wc-block-components-checkbox',
];

const WIDTHS = {
	full: 'flex:1 0 100%;max-width:100%',
	half: 'flex:0 0 calc(50% - 12px);max-width:calc(50% - 12px)',
};

/**
 * Copy every WooCommerce rule written for input[type=text] so it also covers date/time
 * inputs. That keeps them identical to the other fields (border, radius, focus ring...)
 * without us having to guess WooCommerce's CSS.
 */
function cloneTextRules( doc ) {
	const out = [];
	const isText = /\[type="?text"?\]/;

	const walk = ( rules, open, close ) => {
		Array.from( rules ).forEach( ( rule ) => {
			if ( rule.selectorText ) {
				const selectors = rule.selectorText.split( ',' ).filter( ( s ) => isText.test( s ) );
				if ( ! selectors.length ) {
					return;
				}
				const copies = selectors.flatMap( ( s ) =>
					EXTRA_TYPES.map( ( type ) => s.replace( /\[type="?text"?\]/g, `[type="${ type }"]` ) )
				);
				out.push( `${ open }${ copies.join( ',' ) }{${ rule.style.cssText }}${ close }` );
			} else if ( rule.cssRules && rule.media ) {
				walk( rule.cssRules, `${ open }@media ${ rule.media.mediaText }{`, `}${ close }` );
			} else if ( rule.cssRules && rule.conditionText !== undefined ) {
				walk( rule.cssRules, `${ open }@supports ${ rule.conditionText }{`, `}${ close }` );
			}
		} );
	};

	Array.from( doc.styleSheets ).forEach( ( sheet ) => {
		try {
			walk( sheet.cssRules, '', '' );
		} catch ( e ) {
			// Cross-origin stylesheet: unreadable, and not WooCommerce's anyway.
		}
	} );

	return out.join( '\n' );
}

function widthCss() {
	const widths = getSetting( 'zen-block-checkout_data', {} ).widths || {};
	return Object.entries( widths )
		.filter( ( [ , width ] ) => WIDTHS[ width ] )
		.map( ( [ id, width ] ) => {
			const suffix = id.replace( '/', '-' );
			return WRAPPERS.map( ( w ) => `${ w }:has([id$="${ suffix }"])` ).join( ',' ) + `{${ WIDTHS[ width ] }}`;
		} )
		.join( '\n' );
}

/** Safe to call repeatedly: it replaces its own <style>. */
export function installStyles( doc ) {
	let style = doc.getElementById( STYLE_ID );
	if ( ! style ) {
		style = doc.createElement( 'style' );
		style.id = STYLE_ID;
		doc.head.appendChild( style );
	}
	style.textContent = [ cloneTextRules( doc ), BASE, widthCss() ].join( '\n' );
}

/** Frontend: WooCommerce's stylesheets may still be loading, so redo it once everything has. */
export function installPageStyles() {
	installStyles( document );
	window.addEventListener( 'load', () => installStyles( document ) );
}

/** Editor: the canvas is an iframe that appears (and is rebuilt) after this script runs. */
export function watchEditorCanvas() {
	window.setInterval( () => {
		const frame = document.querySelector( 'iframe[name="editor-canvas"]' );
		const doc = frame?.contentDocument;
		if ( doc?.head && ! doc.getElementById( STYLE_ID ) && doc.styleSheets.length ) {
			installStyles( doc );
		}
	}, 1000 );
}
