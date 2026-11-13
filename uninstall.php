<?php
/**
 * Removes plugin data on uninstall.
 *
 * @package Zen_Block_Checkout
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'zenblch_config' );
delete_option( 'zenblch_cart' );
