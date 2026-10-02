<?php
/**
 * Hands our block scripts to the WooCommerce checkout block.
 *
 * @package Zen_Block_Checkout
 */

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zen_Block_Checkout_Integration implements IntegrationInterface {

	public function get_name() {
		return 'zen-block-checkout';
	}

	public function initialize() {
		$this->register_script( 'zbc-frontend', 'frontend' );
		$this->register_script( 'zbc-editor', 'editor' );
		wp_register_style( 'zbc-editor', ZBC_URL . 'build/editor.css', array(), ZBC_VERSION );
	}

	public function get_script_handles() {
		return array( 'zbc-frontend' );
	}

	public function get_editor_script_handles() {
		return array( 'zbc-editor' );
	}

	public function get_script_data() {
		// WooCommerce's editor already draws saved address fields itself; the editor script
		// uses this to preview only the ones that have not been saved yet.
		// Real input type per field id, for the types WooCommerce registers as plain text.
		$input_types = array();
		// Fields the browser script replaces with its own control (textarea, radio, checkboxes).
		$skins = array();
		// Column width per field id (only the ones the store owner set).
		$widths = array();
		foreach ( Zen_Block_Checkout_Config_Store::get_fields() as $field ) {
			$id = Zen_Block_Checkout_Field_Registrar::NAMESPACE_PREFIX . $field['key'];
			if ( ! empty( $field['width'] ) ) {
				$widths[ $id ] = $field['width'];
			}
			if ( in_array( $field['type'], Zen_Block_Checkout_Config_Sync::INPUT_TYPES, true ) ) {
				$input_types[ $id ] = $field['type'];
			} elseif ( in_array( $field['type'], Zen_Block_Checkout_Config_Sync::SKIN_TYPES, true ) ) {
				$skins[ $id ] = array(
					'type'     => $field['type'],
					'label'    => $field['label'],
					'required' => $field['required'],
					'options'  => $field['options'],
				);
			}
		}

		return array(
			'inputTypes'       => (object) $input_types,
			'skins'            => (object) $skins,
			'widths'           => (object) $widths,
			'savedAddressKeys' => array_values(
				array_map(
					function ( $field ) {
						return $field['key'];
					},
					array_filter(
						Zen_Block_Checkout_Config_Store::get_fields(),
						function ( $field ) {
							return 'address' === $field['location'];
						}
					)
				)
			),
		);
	}

	/**
	 * @param string $handle Script handle.
	 * @param string $name   Entry name in build/.
	 */
	private function register_script( $handle, $name ) {
		$asset_file = ZBC_PATH . "build/{$name}.asset.php";
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => ZBC_VERSION,
		);

		wp_register_script( $handle, ZBC_URL . "build/{$name}.js", $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( $handle, 'zen-block-checkout' );
	}
}
