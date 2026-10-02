import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import metadata from '../../block/block.json';
import { applyInputTypes } from '../input-types';
import { startSkins } from '../skins';
import { installPageStyles } from '../styles';

applyInputTypes();
installPageStyles();
startSkins();

/**
 * The fields themselves are registered server-side with WooCommerce's
 * Additional Checkout Fields API, so WooCommerce renders them. This block only
 * has to exist on the frontend so the checkout doesn't treat it as unknown.
 */
registerCheckoutBlock( {
	metadata,
	component: () => null,
} );
