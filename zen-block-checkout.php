<?php
/**
 * Plugin Name: Zen Block Checkout editor
 * Description: Field editor for the WooCommerce Checkout Block. Add custom fields (with advanced options) and manage the default fields.
 * Version: 1.0.0
 * Author: Tusher Ikbal
 * Author URI: https://tusherikbal.online
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zen-block-checkout
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 10.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZENBLCH_VERSION', '1.0.0' );
define( 'ZENBLCH_FILE', __FILE__ );
define( 'ZENBLCH_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZENBLCH_URL', plugin_dir_url( __FILE__ ) );

/**
 * WooCommerce HPOS + Cart/Checkout Blocks compatibility
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

/**
 * Main Plugin Class
 */
final class Zen_Block_Checkout {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		require_once ZENBLCH_PATH . 'includes/class-config-store.php';
		require_once ZENBLCH_PATH . 'includes/class-config-sync.php';
		require_once ZENBLCH_PATH . 'includes/class-field-registrar.php';

		require_once ZENBLCH_PATH . 'includes/class-cart-settings.php';
		Zen_Block_Checkout_Cart_Settings::init();

		// The cart features implement a WooCommerce Blocks interface, so only load them when it exists.
		if ( interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			require_once ZENBLCH_PATH . 'includes/class-cart-features.php';
			Zen_Block_Checkout_Cart_Features::init();
		}

		Zen_Block_Checkout_Config_Sync::init();
		Zen_Block_Checkout_Field_Registrar::init();

		if ( is_admin() ) {
			require_once ZENBLCH_PATH . 'includes/class-admin-page.php';
			Zen_Block_Checkout_Admin_Page::init();
		}

		add_action( 'init', array( $this, 'register_block' ) );
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $registry ) {
				require_once ZENBLCH_PATH . 'includes/class-integration.php';
				$registry->register( new Zen_Block_Checkout_Integration() );
			}
		);
	}

	public function register_block() {
		register_block_type( ZENBLCH_PATH . 'block' );
	}
}

Zen_Block_Checkout::instance();
