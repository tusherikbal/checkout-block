<?php
/**
 * Registers the stored fields with WooCommerce's Additional Checkout Fields API.
 *
 * @package Zen_Block_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zen_Block_Checkout_Field_Registrar {

	const NAMESPACE_PREFIX = 'zenblch/';

	public static function init() {
		add_action( 'woocommerce_init', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_blocks_validate_location_address_fields', array( __CLASS__, 'validate_scoped' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'print_scope_css' ) );
	}

	/**
	 * Fields limited to one address are registered optional (the hidden copy would
	 * otherwise block checkout), so "required" is enforced here for the visible copy only.
	 *
	 * @param WP_Error $errors Errors collector.
	 * @param array    $fields Submitted address fields, id => value.
	 * @param string   $group  "shipping" or "billing".
	 */
	public static function validate_scoped( $errors, $fields, $group ) {
		foreach ( Zen_Block_Checkout_Config_Store::get_fields() as $field ) {
			if ( 'address' !== $field['location'] || ( $field['scope'] ?? 'both' ) !== $group || ! $field['required'] ) {
				continue;
			}

			$value = $fields[ self::NAMESPACE_PREFIX . $field['key'] ] ?? '';
			// An unticked checkbox arrives as false.
			if ( '' === $value || false === $value || null === $value ) {
				$errors->add(
					'zenblch_required_' . $field['key'],
					/* translators: %s: field label. */
					sprintf( __( 'Please enter a value for %s.', 'zen-block-checkout' ), $field['label'] )
				);
			}
		}
	}

	/**
	 * Hides the copy of a scoped field that belongs to the other address form.
	 * WooCommerce renders them with the ids "shipping-zenblch-<key>" and "billing-zenblch-<key>".
	 */
	public static function print_scope_css() {
		$selectors = array();

		foreach ( Zen_Block_Checkout_Config_Store::get_fields() as $field ) {
			if ( 'address' !== $field['location'] || 'both' === ( $field['scope'] ?? 'both' ) ) {
				continue;
			}

			$hidden_group = 'shipping' === ( $field['scope'] ?? 'both' ) ? 'billing' : 'shipping';
			$id           = '#' . $hidden_group . '-zenblch-' . $field['key'];
			foreach ( array( '.wc-block-components-text-input', '.wc-blocks-components-select', '.wc-block-components-checkbox' ) as $wrapper ) {
				$selectors[] = $wrapper . ':has(' . $id . ')';
			}
		}

		if ( $selectors ) {
			wp_register_style( 'zenblch-scope', false, array(), ZENBLCH_VERSION );
			wp_enqueue_style( 'zenblch-scope' );
			wp_add_inline_style( 'zenblch-scope', esc_html( implode( ',', $selectors ) ) . '{display:none!important}' );
		}
	}

	/**
	 * Server-side check for a plain-input type. Replaces WooCommerce's default
	 * callback, so it repeats the "required" check that one does.
	 *
	 * @param string $type One of Zen_Block_Checkout_Config_Sync::INPUT_TYPES.
	 * @return callable
	 */
	private static function validator( $type ) {
		return function ( $value, $field ) use ( $type ) {
			if ( '' === (string) $value ) {
				if ( ! empty( $field['required'] ) ) {
					/* translators: %s: field label. */
					return new WP_Error( 'woocommerce_required_checkout_field', sprintf( __( 'Please enter a value for %s.', 'zen-block-checkout' ), $field['label'] ) );
				}
				return true;
			}

			switch ( $type ) {
				case 'email':
					$valid = (bool) is_email( $value );
					break;
				case 'tel':
					$valid = (bool) preg_match( '/^[0-9+\-\s().]{5,}$/', $value );
					break;
				case 'number':
					$valid = is_numeric( $value );
					break;
				case 'url':
					$valid = false !== filter_var( $value, FILTER_VALIDATE_URL );
					break;
				case 'date':
					$valid = (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
					break;
				case 'time':
					$valid = (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value );
					break;
				case 'datetime-local':
					$valid = (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):[0-5]\d$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
					break;
				default:
					$valid = true;
			}

			if ( $valid ) {
				return true;
			}

			/* translators: %s: field label. */
			return new WP_Error( 'zenblch_invalid_value', sprintf( __( 'Please provide a valid %s.', 'zen-block-checkout' ), $field['label'] ) );
		};
	}

	/**
	 * WooCommerce only knows text, select and checkbox. Everything else is stored by
	 * one of those and drawn differently in the browser (see src/input-types.js and src/skins.js).
	 *
	 * @param string $type Our field type.
	 * @return string
	 */
	public static function wc_type( $type ) {
		if ( 'radio' === $type ) {
			return 'select';
		}
		if ( in_array( $type, Zen_Block_Checkout_Config_Sync::INPUT_TYPES, true ) || in_array( $type, array( 'textarea', 'checkboxes' ), true ) ) {
			return 'text';
		}
		return $type;
	}

	/**
	 * Every ticked value must be one of the configured options.
	 *
	 * @param string[] $options Allowed values.
	 * @return callable
	 */
	private static function choices_validator( array $options ) {
		return function ( $value, $field ) use ( $options ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				if ( ! empty( $field['required'] ) ) {
					/* translators: %s: field label. */
					return new WP_Error( 'woocommerce_required_checkout_field', sprintf( __( 'Please choose at least one option for %s.', 'zen-block-checkout' ), $field['label'] ) );
				}
				return true;
			}

			foreach ( array_map( 'trim', explode( ',', $value ) ) as $picked ) {
				if ( ! in_array( $picked, $options, true ) ) {
					/* translators: %s: field label. */
					return new WP_Error( 'zenblch_invalid_value', sprintf( __( 'Please provide a valid %s.', 'zen-block-checkout' ), $field['label'] ) );
				}
			}
			return true;
		};
	}

	public static function register() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		// Fields sharing an anchor keep the order they have in the editor.
		$placed = array();

		foreach ( Zen_Block_Checkout_Config_Store::get_fields() as $field ) {
			$args = array(
				'id'       => self::NAMESPACE_PREFIX . $field['key'],
				'label'    => $field['label'],
				'location' => $field['location'],
				'type'     => self::wc_type( $field['type'] ),
				'required' => $field['required'] && 'both' === ( $field['scope'] ?? 'both' ),
			);

			if ( in_array( $field['type'], Zen_Block_Checkout_Config_Sync::INPUT_TYPES, true ) ) {
				$args['validate_callback'] = self::validator( $field['type'] );
			} elseif ( 'checkboxes' === $field['type'] ) {
				$args['validate_callback'] = self::choices_validator( $field['options'] );
			} elseif ( 'textarea' === $field['type'] ) {
				// The default sanitizer is a no-op, but be explicit that line breaks are wanted.
				$args['sanitize_callback'] = function ( $value ) {
					return sanitize_textarea_field( (string) $value );
				};
			}

			if ( 'address' === $field['location'] ) {
				$anchor            = $field['after'] ?? '';
				$base              = Zen_Block_Checkout_Config_Sync::ADDRESS_ANCHORS[ $anchor ] ?? 110;
				$placed[ $anchor ] = ( $placed[ $anchor ] ?? 0 ) + 1;
				// WooCommerce sorts address fields by index; the fraction slots us right after the anchor.
				$args['index'] = $base + $placed[ $anchor ] / 100;
			}

			if ( in_array( $field['type'], array( 'select', 'radio' ), true ) ) {
				$args['options'] = array_map(
					function ( $option ) {
						return array(
							'value' => $option,
							'label' => $option,
						);
					},
					$field['options']
				);

				if ( '' !== ( $field['placeholder'] ?? '' ) ) {
					$args['placeholder'] = $field['placeholder'];
				}
			}

			woocommerce_register_additional_checkout_field( $args );
		}
	}
}
