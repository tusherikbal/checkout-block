<?php
/**
 * Where the field config lives once it has been read out of the checkout block.
 *
 * @package Zen_Block_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The block attribute is the source of truth; this option is a copy PHP can
 * read on every request (including Store API requests that never render a page).
 *
 * Shape: [ '<source>' => [ 'hash' => string, 'fields' => array ] ] where source is
 * a post ID (checkout page or template) or "runtime" (read while rendering).
 */
class Zen_Block_Checkout_Config_Store {

	const OPTION  = 'zbc_config';
	const RUNTIME = 'runtime';

	/**
	 * @return array
	 */
	private static function all() {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * @param string|int $source Post ID or self::RUNTIME.
	 * @param array      $fields Sanitized fields.
	 */
	public static function set_source( $source, array $fields ) {
		$data            = self::all();
		$data[ $source ] = array(
			'hash'   => self::hash( $fields ),
			'fields' => $fields,
		);
		update_option( self::OPTION, $data, true );
	}

	/**
	 * @param string|int $source Post ID or self::RUNTIME.
	 */
	public static function delete_source( $source ) {
		$data = self::all();
		if ( isset( $data[ $source ] ) ) {
			unset( $data[ $source ] );
			update_option( self::OPTION, $data, true );
		}
	}

	/**
	 * Fields that should be registered: the checkout page as last saved,
	 * else what a page view found, else any template.
	 *
	 * @return array
	 */
	public static function get_fields() {
		$data = self::all();

		$page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;
		if ( $page_id > 0 && isset( $data[ $page_id ] ) ) {
			return $data[ $page_id ]['fields'];
		}

		// A page view only fills in when the checkout page has nothing saved.
		if ( isset( $data[ self::RUNTIME ] ) ) {
			return $data[ self::RUNTIME ]['fields'];
		}

		$first = reset( $data );
		return $first ? $first['fields'] : array();
	}

	/**
	 * @return string Hash of the fields currently in effect.
	 */
	public static function current_hash() {
		return self::hash( self::get_fields() );
	}

	/**
	 * @param array $fields Fields.
	 * @return string
	 */
	public static function hash( array $fields ) {
		return md5( wp_json_encode( $fields ) );
	}
}
