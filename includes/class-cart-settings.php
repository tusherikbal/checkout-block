<?php
/**
 * Settings for the cart block features.
 *
 * @package Zen_Block_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zen_Block_Checkout_Cart_Settings {

	const OPTION = 'zenblch_cart';
	const GROUP  = 'zenblch_cart_group';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		// Shop managers have manage_woocommerce, not manage_options.
		add_filter( 'option_page_capability_' . self::GROUP, array( __CLASS__, 'capability' ) );
	}

	public static function capability() {
		return 'manage_woocommerce';
	}

	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * @return array
	 */
	public static function defaults() {
		return array(
			'notice_enabled'     => 0,
			'notice_text'        => '',
			'notice_style'       => 'info',
			'progress_enabled'   => 0,
			'progress_threshold' => '',
			'progress_below'     => __( 'Add {amount} more to get free shipping.', 'zen-block-checkout' ),
			'progress_done'      => __( 'You have free shipping!', 'zen-block-checkout' ),
			'min_enabled'        => 0,
			'min_amount'         => '',
			'min_message'        => __( 'The minimum order is {amount}.', 'zen-block-checkout' ),
			'min_enforce'        => 1,
		);
	}

	/**
	 * @return array Saved settings merged over the defaults.
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @param mixed $input Raw submitted settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$out   = array();

		foreach ( array( 'notice_enabled', 'progress_enabled', 'min_enabled', 'min_enforce' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		foreach ( array( 'progress_below', 'progress_done', 'min_message' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		}

		$out['notice_text']  = isset( $input['notice_text'] ) ? sanitize_textarea_field( $input['notice_text'] ) : '';
		$style               = isset( $input['notice_style'] ) ? sanitize_key( $input['notice_style'] ) : 'info';
		$out['notice_style'] = in_array( $style, array( 'info', 'success', 'warning' ), true ) ? $style : 'info';

		foreach ( array( 'progress_threshold', 'min_amount' ) as $key ) {
			$value       = isset( $input[ $key ] ) ? (float) str_replace( ',', '.', (string) $input[ $key ] ) : 0;
			$out[ $key ] = $value > 0 ? (string) $value : '';
		}

		// An empty message would show a blank notice, so fall back to the default.
		$defaults = self::defaults();
		foreach ( array( 'progress_below', 'progress_done', 'min_message' ) as $key ) {
			if ( '' === $out[ $key ] ) {
				$out[ $key ] = $defaults[ $key ];
			}
		}

		return $out;
	}
}
