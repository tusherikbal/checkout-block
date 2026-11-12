const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

// Swap the stock dependency plugin for WooCommerce's, so `@woocommerce/blocks-checkout`
// resolves to the `wc-blocks-checkout` global instead of being bundled.
module.exports = {
	...defaultConfig,
	entry: {
		editor: './src/editor/index.js',
		frontend: './src/frontend/index.js',
		cart: './src/cart/index.js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};
