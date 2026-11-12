=== Zen Block Checkout editor ===
Contributors: tusherikbal
Tags: woocommerce, checkout, checkout block, custom fields, checkout fields
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Field editor for the WooCommerce Checkout Block: add custom fields with advanced options and manage default fields.

== Description ==
Add your own fields to the WooCommerce block checkout and edit the default ones, right inside the block editor.

* Text, dropdown, email, phone, number, URL, date, time, textarea, radio and checkbox-group fields
* Place fields in Contact information, Shipping address, Billing address or Order information
* Show a field in shipping only, billing only, or both
* Required and optional fields with server-side validation

This plugin does not connect to any external service and does not track users.

== Installation ==
1. Make sure WooCommerce is installed and active.
2. Upload the plugin to `/wp-content/plugins/` and activate it.
3. Edit your Checkout page in the block editor and add the "Custom Fields" block inside the checkout step where you want the fields.

== Frequently Asked Questions ==
= Does it work with the classic shortcode checkout? =
No. It is built for the WooCommerce Checkout Block.

== Source code ==
The JavaScript and CSS in the `build` folder are compiled from the `src` folder. Build with `npm install` then `npm run build` (uses @wordpress/scripts).

== Changelog ==
= 0.1.0 =
* Initial release.
