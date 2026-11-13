<?php
/**
 * Server side of the cart features WooCommerce does not have itself: a cart notice,
 * a free shipping progress bar and a minimum order.
 * The on-screen parts live in src/cart.
 *
 * @package Zen_Block_Checkout
 */

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zen_Block_Checkout_Cart_Features implements IntegrationInterface {

	const NAMESPACE_ID = 'zenblch-cart';

	public static function init() {
		$instance = new self();

		add_action( 'woocommerce_blocks_cart_block_registration', array( $instance, 'register_integration' ) );
		add_action( 'woocommerce_blocks_enqueue_cart_block_scripts_after', array( $instance, 'enqueue_style' ) );
		add_action( 'woocommerce_store_api_cart_errors', array( $instance, 'enforce_minimum' ), 10, 2 );
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
			'notice'   => array(
				'enabled' => (bool) $settings['notice_enabled'] && '' !== trim( $settings['notice_text'] ),
				'text'    => $settings['notice_text'],
				'style'   => $settings['notice_style'],
			),
			'progress' => array(
				'enabled'   => (bool) $settings['progress_enabled'] && (float) $settings['progress_threshold'] > 0,
				'threshold' => (float) $settings['progress_threshold'],
				'below'     => $settings['progress_below'],
				'done'      => $settings['progress_done'],
			),
			'minimum'  => array(
				'enabled' => (bool) $settings['min_enabled'] && (float) $settings['min_amount'] > 0,
				'amount'  => (float) $settings['min_amount'],
				'message' => $settings['min_message'],
			),
		);
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
}
