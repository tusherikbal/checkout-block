<?php
/**
 * Reads the custom fields out of the checkout block markup and keeps the stored copy in step.
 *
 * @package Zen_Block_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zen_Block_Checkout_Config_Sync {

	const BLOCK = 'zen-block-checkout/custom-fields';

	/**
	 * The checkout step a block sits in decides the WooCommerce field location.
	 */
	const STEP_LOCATIONS = array(
		'woocommerce/checkout-contact-information-block' => 'contact',
		'woocommerce/checkout-shipping-address-block'    => 'address',
		'woocommerce/checkout-billing-address-block'     => 'address',
		'woocommerce/checkout-fields-block'              => 'order',
	);

	/**
	 * Types WooCommerce supports itself, plus plain-input types it does not.
	 * The latter are registered as "text" and given their real type in the browser (see INPUT_TYPES).
	 */
	const INPUT_TYPES = array( 'email', 'tel', 'number', 'url', 'date', 'time', 'datetime-local' );

	/**
	 * Types WooCommerce cannot draw at all. Each is stored by a native field (text, or a
	 * dropdown for radio) that the browser script hides and replaces with its own control,
	 * so position, scope, validation and order display all stay WooCommerce's.
	 */
	const SKIN_TYPES = array( 'textarea', 'radio', 'checkboxes', 'info' );

	/** Types that need a list of options. */
	const OPTION_TYPES = array( 'select', 'radio', 'checkboxes' );

	const TYPES = array( 'text', 'select', 'checkbox', 'email', 'tel', 'number', 'url', 'date', 'time', 'datetime-local', 'textarea', 'radio', 'checkboxes', 'country', 'info' );

	/**
	 * Default address fields a custom address field can be placed after (key => WooCommerce sort index).
	 */
	const ADDRESS_ANCHORS = array(
		'country'    => 1,
		'first_name' => 10,
		'last_name'  => 20,
		'company'    => 30,
		'address_1'  => 40,
		'address_2'  => 50,
		'city'       => 70,
		'state'      => 80,
		'postcode'   => 90,
		'phone'      => 100,
	);

	public static function init() {
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_save' ), 10, 2 );
		add_filter( 'pre_render_block', array( __CLASS__, 'on_render' ), 10, 2 );
	}

	/**
	 * Save-time sync: the checkout page, or any template that contains the checkout block.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'page' === $post->post_type ) {
			if ( ! function_exists( 'wc_get_page_id' ) || (int) wc_get_page_id( 'checkout' ) !== (int) $post_id ) {
				return;
			}

			// A checkout page that no longer has the block must not keep shadowing the templates.
			if ( ! has_block( 'woocommerce/checkout', $post->post_content ) ) {
				Zen_Block_Checkout_Config_Store::delete_source( $post_id );
				return;
			}
		} elseif ( 'wp_template' !== $post->post_type || ! has_block( 'woocommerce/checkout', $post->post_content ) ) {
			return;
		}

		$blocks = parse_blocks( $post->post_content );

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		Zen_Block_Checkout_Config_Store::set_source( $post_id, self::extract( $blocks ) );
		// A fresh save outranks whatever a page view recorded earlier.
		Zen_Block_Checkout_Config_Store::delete_source( Zen_Block_Checkout_Config_Store::RUNTIME );
	}

	/**
	 * Self-heal: if the checkout being rendered disagrees with the stored copy
	 * (theme template, import, migration...), the rendered markup wins.
	 *
	 * @param string|null $pre          Short-circuit value, passed through untouched.
	 * @param array       $parsed_block Block being rendered.
	 * @return string|null
	 */
	public static function on_render( $pre, $parsed_block ) {
		if ( 'woocommerce/checkout' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $pre;
		}

		// Only the real checkout page may speak for the store. Any author can put a checkout block in a
		// post and preview it, and that must not be able to rewrite the fields shoppers see.
		if ( is_admin() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return $pre;
		}

		$fields = self::extract( array( $parsed_block ) );
		if ( Zen_Block_Checkout_Config_Store::hash( $fields ) !== Zen_Block_Checkout_Config_Store::current_hash() ) {
			Zen_Block_Checkout_Config_Store::set_source( Zen_Block_Checkout_Config_Store::RUNTIME, $fields );
		}

		return $pre;
	}

	/**
	 * @param array       $blocks   Parsed blocks.
	 * @param string|null $location Location of the nearest checkout step above.
	 * @return array Sanitized fields, each tagged with its location.
	 */
	public static function extract( array $blocks, $location = null ) {
		$fields = array();

		foreach ( $blocks as $block ) {
			$name     = $block['blockName'] ?? '';
			$here     = self::STEP_LOCATIONS[ $name ] ?? $location;
			$children = $block['innerBlocks'] ?? array();

			if ( self::BLOCK === $name && $here ) {
				foreach ( (array) ( $block['attrs']['fields'] ?? array() ) as $raw ) {
					$field = self::sanitize_field( $raw, $here );
					if ( $field ) {
						$fields[] = $field;
					}
				}
			}

			if ( $children ) {
				$fields = array_merge( $fields, self::extract( $children, $here ) );
			}
		}

		// The key is the stored meta key, so the same one in two places would collide.
		$seen = array();
		return array_values(
			array_filter(
				$fields,
				function ( $field ) use ( &$seen ) {
					if ( isset( $seen[ $field['key'] ] ) ) {
						return false;
					}
					$seen[ $field['key'] ] = true;
					return true;
				}
			)
		);
	}

	/**
	 * @param mixed  $raw      Field as saved in the block attribute.
	 * @param string $location WooCommerce location.
	 * @return array|null
	 */
	private static function sanitize_field( $raw, $location ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$key   = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $raw['key'] ?? '' ) ) );
		$label = trim( sanitize_text_field( (string) ( $raw['label'] ?? '' ) ) );
		$type  = in_array( $raw['type'] ?? '', self::TYPES, true ) ? $raw['type'] : 'text';

		if ( '' === $key || '' === $label ) {
			return null;
		}

		$field = array(
			'key'         => $key,
			'location'    => $location,
			'type'        => $type,
			'label'       => $label,
			'required'    => ! empty( $raw['required'] ),
			'placeholder' => '',
			'text'        => '',
			'options'     => array(),
			'after'       => '',
			'scope'       => 'both',
			'width'       => '',
		);

		// Column width in the checkout form: one full row, or half a row (two fields side by side).
		if ( in_array( $raw['width'] ?? '', array( 'full', 'half' ), true ) ) {
			$field['width'] = $raw['width'];
		}

		// WooCommerce shows address fields in both forms; "shipping"/"billing" hide the other copy.
		$scope = (string) ( $raw['scope'] ?? 'both' );
		if ( 'address' === $location && in_array( $scope, array( 'shipping', 'billing' ), true ) ) {
			$field['scope'] = $scope;
		}

		// Only address fields sit among WooCommerce's own fields, so only they can be anchored to one.
		$after = (string) ( $raw['after'] ?? '' );
		if ( 'address' === $location && isset( self::ADDRESS_ANCHORS[ $after ] ) ) {
			$field['after'] = $after;
		}

		// Heading and text only: nothing is asked, so nothing can be required or stored.
		if ( 'info' === $type ) {
			$field['required'] = false;
			$field['text']     = sanitize_textarea_field( (string) ( $raw['text'] ?? '' ) );
		}

		// The list of countries comes from WooCommerce when the field is registered.
		if ( 'country' === $type ) {
			$field['placeholder'] = sanitize_text_field( (string) ( $raw['placeholder'] ?? '' ) );
		}

		if ( in_array( $type, self::OPTION_TYPES, true ) ) {
			if ( 'select' === $type ) {
				$field['placeholder'] = sanitize_text_field( (string) ( $raw['placeholder'] ?? '' ) );
			}

			$lines = preg_split( '/\r\n|\r|\n/', (string) ( $raw['optionsText'] ?? '' ) );
			// Several ticked options are stored as one comma-separated value, so no option may contain a comma.
			$lines   = array_map(
				function ( $line ) use ( $type ) {
					$line = sanitize_text_field( $line );
					return 'checkboxes' === $type ? trim( str_replace( ',', '', $line ) ) : $line;
				},
				$lines
			);
			$options = array_values( array_unique( array_filter( $lines, 'strlen' ) ) );

			// A list field with nothing to pick is not worth registering.
			if ( ! $options ) {
				return null;
			}
			$field['options'] = $options;
		}

		return $field;
	}
}
