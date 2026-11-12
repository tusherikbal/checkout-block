import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { __, sprintf } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import {
	ExperimentalOrderMeta,
	registerCheckoutFilters,
	extensionCartUpdate,
} from '@woocommerce/blocks-checkout';
import { formatPrice, getCurrencyFromPriceResponse } from '@woocommerce/price-format';
import './cart.scss';

const NAMESPACE = 'zenblch-cart';
const settings = getSetting( 'zenblch-cart_data', {} );

/**
 * "Proceed to checkout" label.
 */
if ( settings.buttonLabel ) {
	registerCheckoutFilters( NAMESPACE, {
		proceedToCheckoutButtonLabel: () => settings.buttonLabel,
	} );
}

/**
 * Cart figures in the store currency. Items minus discounts, before tax and shipping,
 * which is the same figure the server uses for the minimum order.
 */
function useCartAmount() {
	const totals = useSelect( ( select ) => select( 'wc/store/cart' ).getCartTotals(), [] );
	if ( ! totals || totals.total_items === undefined ) {
		return null;
	}
	const currency = getCurrencyFromPriceResponse( totals );
	const factor = Math.pow( 10, currency.minorUnit );
	const minor = parseInt( totals.total_items, 10 ) - parseInt( totals.total_discount || 0, 10 );
	return { currency, factor, minor, major: minor / factor };
}

function fillAmount( template, minor, currency ) {
	return template.replace( '{amount}', formatPrice( minor, currency ) );
}

function InfoNotice() {
	const { text, style } = settings.notice;
	return (
		<div className={ `zenblch-notice zenblch-notice--${ style }` }>
			{ text.split( /\n+/ ).map( ( line, index ) => (
				<p key={ index }>{ line }</p>
			) ) }
		</div>
	);
}

function FreeShippingBar( { cart } ) {
	const { threshold, below, done } = settings.progress;
	const goal = Math.round( threshold * cart.factor );
	const reached = cart.minor >= goal;
	const percent = Math.max( 0, Math.min( 100, ( cart.minor / goal ) * 100 ) );

	return (
		<div className="zenblch-progress">
			<p>{ reached ? done : fillAmount( below, goal - cart.minor, cart.currency ) }</p>
			<div
				className="zenblch-progress__track"
				role="progressbar"
				aria-valuemin={ 0 }
				aria-valuemax={ 100 }
				aria-valuenow={ Math.round( percent ) }
			>
				<div className="zenblch-progress__bar" style={ { width: `${ percent }%` } } />
			</div>
		</div>
	);
}

function MinimumNotice( { cart } ) {
	const { amount, message } = settings.minimum;
	const goal = Math.round( amount * cart.factor );
	if ( cart.minor >= goal ) {
		return null;
	}
	return (
		<div className="zenblch-notice zenblch-notice--warning" role="alert">
			<p>{ fillAmount( message, goal, cart.currency ) }</p>
		</div>
	);
}

function CartNote() {
	const { label, placeholder, limit } = settings.note;
	const saved = useSelect( ( select ) => {
		const extensions = select( 'wc/store/cart' ).getCartData().extensions || {};
		return ( extensions[ NAMESPACE ] && extensions[ NAMESPACE ].note ) || '';
	}, [] );
	const [ value, setValue ] = useState( saved );
	const edited = useRef( false );

	// Pick up the saved note once the cart has loaded, unless the customer already started typing.
	useEffect( () => {
		if ( ! edited.current ) {
			setValue( saved );
		}
	}, [ saved ] );

	useEffect( () => {
		if ( ! edited.current ) {
			return undefined;
		}
		const timer = setTimeout( () => {
			extensionCartUpdate( { namespace: NAMESPACE, data: { note: value } } );
		}, 600 );
		return () => clearTimeout( timer );
	}, [ value ] );

	return (
		<div className="zenblch-note">
			<label htmlFor="zenblch-cart-note">{ label }</label>
			<textarea
				id="zenblch-cart-note"
				rows={ 3 }
				maxLength={ limit }
				placeholder={ placeholder }
				value={ value }
				onChange={ ( event ) => {
					edited.current = true;
					setValue( event.target.value );
				} }
			/>
			<span className="zenblch-note__count">
				{ sprintf(
					/* translators: 1: characters used, 2: character limit. */
					__( '%1$d / %2$d', 'zen-block-checkout' ),
					value.length,
					limit
				) }
			</span>
		</div>
	);
}

function CartExtras() {
	const cart = useCartAmount();

	return (
		<div className="zenblch-cart-extras">
			{ settings.notice && settings.notice.enabled && <InfoNotice /> }
			{ cart && settings.progress && settings.progress.enabled && <FreeShippingBar cart={ cart } /> }
			{ cart && settings.minimum && settings.minimum.enabled && <MinimumNotice cart={ cart } /> }
			{ settings.note && settings.note.enabled && <CartNote /> }
		</div>
	);
}

const anything =
	( settings.notice && settings.notice.enabled ) ||
	( settings.progress && settings.progress.enabled ) ||
	( settings.minimum && settings.minimum.enabled ) ||
	( settings.note && settings.note.enabled );

if ( anything ) {
	registerPlugin( NAMESPACE, {
		scope: 'woocommerce-checkout',
		render: () => (
			<ExperimentalOrderMeta>
				<CartExtras />
			</ExperimentalOrderMeta>
		),
	} );
}
