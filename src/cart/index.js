import { useSelect } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { getSetting } from '@woocommerce/settings';
import { ExperimentalOrderMeta } from '@woocommerce/blocks-checkout';
import { formatPrice, getCurrencyFromPriceResponse } from '@woocommerce/price-format';
import './cart.scss';

const NAMESPACE = 'zenblch-cart';
const settings = getSetting( 'zenblch-cart_data', {} );

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

function CartExtras() {
	const cart = useCartAmount();

	return (
		<div className="zenblch-cart-extras">
			{ settings.notice && settings.notice.enabled && <InfoNotice /> }
			{ cart && settings.progress && settings.progress.enabled && <FreeShippingBar cart={ cart } /> }
			{ cart && settings.minimum && settings.minimum.enabled && <MinimumNotice cart={ cart } /> }
		</div>
	);
}

const anything =
	( settings.notice && settings.notice.enabled ) ||
	( settings.progress && settings.progress.enabled ) ||
	( settings.minimum && settings.minimum.enabled );

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
