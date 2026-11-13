=== Zen Block Checkout editor ===
Contributors: tusherikbal
Tags: woocommerce, checkout, checkout block, custom fields, checkout fields
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom fields for the WooCommerce Checkout Block, plus cart extras: notice, free shipping bar and minimum order.

== Description ==
Add your own fields to the WooCommerce block checkout and edit the default ones, right inside the block editor.

* Text, dropdown, email, phone, number, URL, date, time, textarea, radio and checkbox-group fields
* Place fields in Contact information, Shipping address, Billing address or Order information
* Show a field in shipping only, billing only, or both
* Required and optional fields with server-side validation

Cart Block extras (all off until you turn them on, under WooCommerce > Checkout Block Editor):

* A notice in the cart, for example delivery times
* A free shipping progress bar
* A minimum order amount, with an optional block at checkout
* One click to regenerate a missing Checkout or Cart page

This plugin does not connect to any external service and does not track users.

== Installation ==
1. Make sure WooCommerce is installed and active.
2. Upload the plugin to `/wp-content/plugins/` and activate it.
3. Edit your Checkout page in the block editor and add the "Custom Fields" block inside the checkout step where you want the fields.
4. For the cart, go to WooCommerce > Checkout Block Editor and turn on the cart features you want.

== Frequently Asked Questions ==
= Does it work with the classic shortcode checkout? =
No. It is built for the WooCommerce Checkout and Cart Blocks. Pages built with the classic shortcode or a page builder such as Elementor are not supported.

== Source code ==
The JavaScript and CSS in the `build` folder are compiled from the `src` folder. Build with `npm install` then `npm run build` (uses @wordpress/scripts).

== Changelog ==
= 1.0.0 =
* Initial release: custom checkout fields and cart block extras.
