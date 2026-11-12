<?php
/**
 * Server side of the cart features: minimum order, cart note, hidden cart elements.
 * The on-screen parts (notice, progress bar, note box, button label) live in src/cart.
 *
 * @package Zen_Block_Checkout
 */

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zen_Block_Checkout_Cart_Features implements IntegrationInterface {

	const NAMESPACE_ID = 'zenblch-cart';
	const SESSION_KEY  = 'zenblch_cart_note';
	const NOTE_META    = '_zenblch_cart_note';
	const NOTE_LIMIT   = 500;

	public static function init() {
		$instance = new self();

		add_action( 'woocommerce_blocks_cart_block_registration', array( $instance, 'register_integration' ) );
		add_action( 'woocommerce_blocks_enqueue_cart_block_scripts_after', array( $instance, 'enqueue_style' ) );
		add_action( 'woocommerce_init', array( $instance, 'register_store_api' ) );
		add_action( 'woocommerce_store_api_cart_errors', array( $instance, 'enforce_minimum' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $instance, 'save_note_on_order' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $instance, 'clear_note' ) );
		add_action( 'wp_enqueue_scripts', array( $instance, 'hide_elements' ) );

		// Show the note to the people who need it.
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $instance, 'print_in_admin' ) );
		add_filter( 'woocommerce_email_order_meta_fields', array( $instance, 'add_to_email' ), 10, 3 );
		add_action( 'woocommerce_order_details_after_order_table', array( $instance, 'print_in_order_details' ) );
	}

	/** @param object $registry Integration registry of the cart block. */
	public function register_integration( $registry ) {
		$registry->register( $this );
	}

	/* ---------------------------------------------------------------- Integration */

	public function get_name() {
		return self::NAMESPACE_ID;
	}

	public function initialize() {
		$asset_file = ZENBLCH_PATH . 'build/cart.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => ZENBLCH_VERSION,
		);

		wp_register_script( 'zenblch-cart', ZENBLCH_URL . 'build/cart.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'zenblch-cart', 'zen-block-checkout' );
		wp_register_style( 'zenblch-cart', ZENBLCH_URL . 'build/cart.css', array(), $asset['version'] );
	}

	/** Loads the stylesheet only when the cart block is actually shown. */
	public function enqueue_style() {
		wp_enqueue_style( 'zenblch-cart' );
	}

	public function get_script_handles() {
		return array( 'zenblch-cart' );
	}

	public function get_editor_script_handles() {
		return array();
	}

	public function get_script_data() {
		$settings = Zen_Block_Checkout_Cart_Settings::get();

		return array(
			'buttonLabel'       => $settings['button_label'],
			'notice'            => array(
				'enabled' => (bool) $settings['notice_enabled'] && '' !== trim( $settings['notice_text'] ),
				'text'    => $settings['notice_text'],
				'style'   => $settings['notice_style'],
			),
			'progress'          => array(
				'enabled'   => (bool) $settings['progress_enabled'] && (float) $settings['progress_threshold'] > 0,
				'threshold' => (float) $settings['progress_threshold'],
				'below'     => $settings['progress_below'],
				'done'      => $settings['progress_done'],
			),
			'minimum'           => array(
				'enabled' => (bool) $settings['min_enabled'] && (float) $settings['min_amount'] > 0,
				'amount'  => (float) $settings['min_amount'],
				'message' => $settings['min_message'],
			),
			'note'              => array(
				'enabled'     => (bool) $settings['note_enabled'],
				'label'       => $settings['note_label'],
				'placeholder' => $settings['note_placeholder'],
				'limit'       => self::NOTE_LIMIT,
			),
		);
	}

	/* ------------------------------------------------------------------ Store API */

	public function register_store_api() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
				'namespace'       => self::NAMESPACE_ID,
				'data_callback'   => function () {
					return array( 'note' => $this->get_note() );
				},
				'schema_callback' => function () {
					return array(
						'note' => array(
							'description' => __( 'Note the customer left in the cart.', 'zen-block-checkout' ),
							'type'        => 'string',
							'readonly'    => true,
						),
					);
				},
				'schema_type'     => ARRAY_A,
			)
		);

		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => self::NAMESPACE_ID,
				'callback'  => function ( $data ) {
					if ( ! isset( $data['note'] ) || empty( Zen_Block_Checkout_Cart_Settings::get()['note_enabled'] ) ) {
						return;
					}
					$this->set_note( (string) $data['note'] );
				},
			)
		);
	}

	/* ---------------------------------------------------------------- Cart note */

	/** @return string */
	private function get_note() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}
		return (string) WC()->session->get( self::SESSION_KEY, '' );
	}

	/** @param string $note Raw note from the browser. */
	private function set_note( $note ) {
		if ( ! WC()->session ) {
			return;
		}
		$note = sanitize_textarea_field( $note );
		$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, self::NOTE_LIMIT ) : substr( $note, 0, self::NOTE_LIMIT );

		// Guests have no session cookie yet.
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->session->set( self::SESSION_KEY, $note );
	}

	/** @param WC_Order $order Order being built from the checkout request. */
	public function save_note_on_order( $order ) {
		$note = $this->get_note();
		if ( '' !== $note && ! empty( Zen_Block_Checkout_Cart_Settings::get()['note_enabled'] ) ) {
			$order->update_meta_data( self::NOTE_META, $note );
		}
	}

	public function clear_note() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( self::SESSION_KEY );
		}
	}

	/** @param WC_Order $order Order. */
	public function print_in_admin( $order ) {
		$note = $order instanceof WC_Order ? (string) $order->get_meta( self::NOTE_META ) : '';
		if ( '' === $note ) {
			return;
		}
		echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Cart note:', 'zen-block-checkout' ) . '</strong><br>' . nl2br( esc_html( $note ) ) . '</p>';
	}

	/**
	 * @param array    $fields        Extra email fields.
	 * @param bool     $sent_to_admin Whether the email goes to the store.
	 * @param WC_Order $order         Order.
	 * @return array
	 */
	public function add_to_email( $fields, $sent_to_admin, $order ) {
		$note = $order instanceof WC_Order ? (string) $order->get_meta( self::NOTE_META ) : '';
		if ( '' !== $note ) {
			$fields['zenblch_cart_note'] = array(
				'label' => __( 'Cart note', 'zen-block-checkout' ),
				'value' => $note,
			);
		}
		return $fields;
	}

	/** @param WC_Order $order Order. */
	public function print_in_order_details( $order ) {
		$note = $order instanceof WC_Order ? (string) $order->get_meta( self::NOTE_META ) : '';
		if ( '' === $note ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Cart note:', 'zen-block-checkout' ) . '</strong><br>' . nl2br( esc_html( $note ) ) . '</p>';
	}

	/* ------------------------------------------------------------- Minimum order */

	/**
	 * @param WP_Error $errors Cart errors collected so far.
	 * @param WC_Cart  $cart   Cart.
	 */
	public function enforce_minimum( $errors, $cart ) {
		$settings = Zen_Block_Checkout_Cart_Settings::get();
		$minimum  = (float) $settings['min_amount'];

		if ( empty( $settings['min_enabled'] ) || empty( $settings['min_enforce'] ) || $minimum <= 0 ) {
			return;
		}

		// Same figure the progress bar and notice use: items minus discounts, before tax and shipping.
		$total = (float) $cart->get_subtotal() - (float) $cart->get_discount_total();
		if ( $total >= $minimum ) {
			return;
		}

		$errors->add(
			'zenblch_minimum_order',
			str_replace( '{amount}', html_entity_decode( wp_strip_all_tags( wc_price( $minimum ) ), ENT_QUOTES, 'UTF-8' ), $settings['min_message'] )
		);
	}

	/* ------------------------------------------------------------ Hidden elements */

	public function hide_elements() {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}

		$settings  = Zen_Block_Checkout_Cart_Settings::get();
		$selectors = array();
		if ( ! empty( $settings['hide_cross_sells'] ) ) {
			$selectors[] = '.wp-block-woocommerce-cart-cross-sells-block';
		}
		if ( ! empty( $settings['hide_coupon'] ) ) {
			$selectors[] = '.wp-block-woocommerce-cart-order-summary-coupon-form-block';
		}
		if ( ! $selectors ) {
			return;
		}

		wp_register_style( 'zenblch-cart-hide', false, array(), ZENBLCH_VERSION );
		wp_enqueue_style( 'zenblch-cart-hide' );
		wp_add_inline_style( 'zenblch-cart-hide', implode( ',', $selectors ) . '{display:none!important}' );
	}
}
