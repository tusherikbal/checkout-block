<?php
/**
 * WooCommerce > Checkout Block Editor: landing page with editor shortcuts, a setup status
 * and the cart settings.
 *
 * @package Zen_Block_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zen_Block_Checkout_Admin_Page {

	const SLUG   = 'zenblch-checkout-block-editor';
	const ACTION = 'zenblch_create_page';

	/** @var string Hook suffix returned by add_submenu_page(). */
	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_create_page' ) );
	}

	/**
	 * WooCommerce pages this plugin works with: key (as in woocommerce_{key}_page_id) => details.
	 *
	 * @return array
	 */
	private static function pages() {
		return array(
			'checkout' => array(
				'label'  => __( 'Checkout', 'zen-block-checkout' ),
				'block'  => 'woocommerce/checkout',
				'open'   => __( 'Open checkout editor', 'zen-block-checkout' ),
				'regen'  => __( 'Regenerate checkout page', 'zen-block-checkout' ),
				'none'   => __( 'No checkout page found', 'zen-block-checkout' ),
				'noblk'  => __( 'Checkout page does not use the Checkout Block', 'zen-block-checkout' ),
				'okblk'  => __( 'Checkout page uses the Checkout Block', 'zen-block-checkout' ),
			),
			'cart'     => array(
				'label'  => __( 'Cart', 'zen-block-checkout' ),
				'block'  => 'woocommerce/cart',
				'open'   => __( 'Open cart editor', 'zen-block-checkout' ),
				'regen'  => __( 'Regenerate cart page', 'zen-block-checkout' ),
				'none'   => __( 'No cart page found', 'zen-block-checkout' ),
				'noblk'  => __( 'Cart page does not use the Cart Block', 'zen-block-checkout' ),
				'okblk'  => __( 'Cart page uses the Cart Block', 'zen-block-checkout' ),
			),
		);
	}

	public static function add_menu() {
		self::$hook = (string) add_submenu_page(
			'woocommerce',
			__( 'Checkout Block Editor', 'zen-block-checkout' ),
			__( 'Checkout Block Editor', 'zen-block-checkout' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue( $hook ) {
		if ( '' === self::$hook || self::$hook !== $hook ) {
			return;
		}
		wp_enqueue_style( 'zenblch-admin', ZENBLCH_URL . 'assets/admin.css', array(), ZENBLCH_VERSION );
		wp_enqueue_script( 'zenblch-admin', ZENBLCH_URL . 'assets/admin.js', array(), ZENBLCH_VERSION, true );
	}

	/**
	 * @param string $key Page key, one of self::pages().
	 * @return array{exists:bool,uses_block:bool,edit_url:string}
	 */
	private static function page_status( $key ) {
		$pages   = self::pages();
		$page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $key ) : 0;
		$post    = $page_id > 0 ? get_post( $page_id ) : null;
		$exists  = $post && 'page' === $post->post_type && ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true );

		return array(
			'exists'     => (bool) $exists,
			'uses_block' => $exists ? has_block( $pages[ $key ]['block'], $post ) : false,
			'edit_url'   => $exists ? (string) get_edit_post_link( $page_id, 'raw' ) : '',
		);
	}

	/**
	 * Creates a missing WooCommerce page with WooCommerce's own block content, the same way
	 * WooCommerce > Status > Tools > "Create default WooCommerce pages" does, but only for the one page asked for.
	 */
	public static function handle_create_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'zen-block-checkout' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$key    = isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '';
		$result = 'error';

		if ( isset( self::pages()[ $key ] ) && class_exists( 'WC_Install' ) && ! self::page_status( $key )['exists'] ) {
			$only = function ( $pages ) use ( $key ) {
				return isset( $pages[ $key ] ) ? array( $key => $pages[ $key ] ) : array();
			};
			add_filter( 'woocommerce_create_pages', $only, PHP_INT_MAX );
			WC_Install::create_pages();
			remove_filter( 'woocommerce_create_pages', $only, PHP_INT_MAX );

			$result = self::page_status( $key )['exists'] ? 'created' : 'error';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'zenblch_result' => $result,
					'zenblch_page'   => $key,
					'tab'            => $key,
				),
				admin_url( 'admin.php?page=' . self::SLUG )
			)
		);
		exit;
	}

	/* ------------------------------------------------------------ Form helpers */

	private static function field_name( $key ) {
		return Zen_Block_Checkout_Cart_Settings::OPTION . '[' . $key . ']';
	}

	private static function row( $label, $control, $help = '' ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo $control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the helpers below, already escaped.
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function checkbox( $values, $key, $text ) {
		return sprintf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( self::field_name( $key ) ),
			checked( ! empty( $values[ $key ] ), true, false ),
			esc_html( $text )
		);
	}

	private static function input( $values, $key, $type = 'text', $extra = '' ) {
		return sprintf(
			'<input type="%1$s" class="regular-text" name="%2$s" value="%3$s" %4$s />',
			esc_attr( $type ),
			esc_attr( self::field_name( $key ) ),
			esc_attr( $values[ $key ] ),
			$extra // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute strings from this file.
		);
	}

	private static function textarea( $values, $key ) {
		return sprintf(
			'<textarea class="large-text" rows="3" name="%1$s">%2$s</textarea>',
			esc_attr( self::field_name( $key ) ),
			esc_textarea( $values[ $key ] )
		);
	}

	/* --------------------------------------------------------------- Rendering */

	/**
	 * @return string Active tab key, one of self::pages().
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- selects a tab, changes nothing.
		return isset( self::pages()[ $tab ] ) ? $tab : 'checkout';
	}

	/**
	 * The "no such page" / "page does not use the block" notice, plus the regenerate button.
	 *
	 * @param string $key    Page key.
	 * @param array  $status Result of page_status().
	 */
	private static function render_page_notice( $key, $status ) {
		$page = self::pages()[ $key ];

		if ( ! $status['exists'] ) :
			?>
			<div class="notice notice-warning inline zenblch-notice">
				<p>
					<strong><?php echo esc_html( $page['none'] . '.' ); ?></strong>
					<?php esc_html_e( 'WooCommerce has no such page set, so there is nothing to edit yet. Regenerate it with the block version and you can start right away.', 'zen-block-checkout' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<input type="hidden" name="page_key" value="<?php echo esc_attr( $key ); ?>" />
					<?php submit_button( $page['regen'], 'primary', 'submit', false ); ?>
				</form>
			</div>
			<?php
		elseif ( ! $status['uses_block'] ) :
			?>
			<div class="notice notice-warning inline zenblch-notice">
				<p>
					<strong><?php echo esc_html( $page['noblk'] . '.' ); ?></strong>
					<?php esc_html_e( 'It may use the classic shortcode or a page builder such as Elementor, which this plugin does not support. Use the block version of the page, or choose a different page under WooCommerce > Settings > Advanced.', 'zen-block-checkout' ); ?>
				</p>
			</div>
			<?php
		endif;
	}

	/**
	 * The editor button and the one-line page status that open every tab.
	 *
	 * @param string $key    Page key.
	 * @param array  $status Result of page_status().
	 * @param string $lead   Short description shown above the button.
	 * @param array  $extra  Extra status lines (plain text).
	 */
	private static function render_tab_header( $key, $status, $lead, $extra = array() ) {
		$page = self::pages()[ $key ];
		?>
		<p class="zenblch-lead"><?php echo esc_html( $lead ); ?></p>

		<div class="zenblch-card zenblch-hero">
			<?php if ( $status['uses_block'] ) : ?>
				<a class="button button-primary button-hero" href="<?php echo esc_url( $status['edit_url'] ); ?>"><?php echo esc_html( $page['open'] ); ?></a>
			<?php else : ?>
				<span class="button button-primary button-hero disabled" aria-disabled="true"><?php echo esc_html( $page['open'] ); ?></span>
			<?php endif; ?>
			<ul class="zenblch-status zenblch-status--inline">
				<li class="<?php echo $status['uses_block'] ? 'is-ok' : 'is-bad'; ?>">
					<?php
					if ( ! $status['exists'] ) {
						echo esc_html( $page['none'] );
					} elseif ( $status['uses_block'] ) {
						echo esc_html( $page['okblk'] );
					} else {
						echo esc_html( $page['noblk'] );
					}
					?>
				</li>
				<?php foreach ( $extra as $line ) : ?>
					<li class="is-info"><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private static function render_checkout_tab( $status ) {
		$field_count = count( Zen_Block_Checkout_Config_Store::get_fields() );
		$types       = array(
			__( 'Text', 'zen-block-checkout' ),
			__( 'Dropdown', 'zen-block-checkout' ),
			__( 'Email', 'zen-block-checkout' ),
			__( 'Phone', 'zen-block-checkout' ),
			__( 'Number', 'zen-block-checkout' ),
			__( 'URL', 'zen-block-checkout' ),
			__( 'Date', 'zen-block-checkout' ),
			__( 'Time', 'zen-block-checkout' ),
			__( 'Date and time', 'zen-block-checkout' ),
			__( 'Textarea', 'zen-block-checkout' ),
			__( 'Radio buttons', 'zen-block-checkout' ),
			__( 'Checkboxes', 'zen-block-checkout' ),
		);
		$places      = array(
			__( 'Contact information', 'zen-block-checkout' ),
			__( 'Shipping address', 'zen-block-checkout' ),
			__( 'Billing address', 'zen-block-checkout' ),
			__( 'Order information', 'zen-block-checkout' ),
		);

		self::render_tab_header(
			'checkout',
			$status,
			__( 'Add custom fields to the WooCommerce Checkout Block and place them where you need them, right inside the block editor.', 'zen-block-checkout' ),
			array(
				sprintf(
					/* translators: %d: number of custom checkout fields. */
					_n( '%d custom field is set up', '%d custom fields are set up', $field_count, 'zen-block-checkout' ),
					$field_count
				),
			)
		);
		?>
		<div class="zenblch-card">
			<h2><?php esc_html_e( 'How it works', 'zen-block-checkout' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Open your checkout page in the block editor.', 'zen-block-checkout' ); ?></li>
				<li><?php esc_html_e( 'Select a checkout step (Contact information, Shipping address, Billing address or Order information) and add the "Custom Fields" block inside it.', 'zen-block-checkout' ); ?></li>
				<li><?php esc_html_e( 'Add your fields in the block settings, then update the page.', 'zen-block-checkout' ); ?></li>
			</ol>
		</div>

		<div class="zenblch-card">
			<h2><?php esc_html_e( 'What you can add', 'zen-block-checkout' ); ?></h2>
			<h3><?php esc_html_e( 'Field types', 'zen-block-checkout' ); ?></h3>
			<p class="zenblch-tags">
				<?php foreach ( $types as $type ) : ?>
					<span><?php echo esc_html( $type ); ?></span>
				<?php endforeach; ?>
			</p>
			<h3><?php esc_html_e( 'Where fields can appear', 'zen-block-checkout' ); ?></h3>
			<p class="zenblch-tags">
				<?php foreach ( $places as $place ) : ?>
					<span><?php echo esc_html( $place ); ?></span>
				<?php endforeach; ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Address fields can be shown in shipping only, billing only, or both, and placed after any default address field. Required fields are validated on the server.', 'zen-block-checkout' ); ?>
			</p>
		</div>
		<?php
	}

	private static function render_cart_tab( $status ) {
		$values   = Zen_Block_Checkout_Cart_Settings::get();
		$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';

		self::render_tab_header(
			'cart',
			$status,
			__( 'Customize the WooCommerce Cart Block: a notice, a free shipping bar, a minimum order, a cart note and more. Everything is off until you turn it on.', 'zen-block-checkout' )
		);
		?>
		<form method="post" action="options.php" class="zenblch-card zenblch-settings">
			<?php settings_fields( Zen_Block_Checkout_Cart_Settings::GROUP ); ?>
			<?php // Later field of the same name wins, so saving comes back to this tab. ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( add_query_arg( array( 'page' => self::SLUG, 'tab' => 'cart' ), 'admin.php' ) ); ?>" />

			<h3><?php esc_html_e( 'Checkout button', 'zen-block-checkout' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php
				self::row(
					__( 'Button text', 'zen-block-checkout' ),
					self::input( $values, 'button_label', 'text', 'placeholder="' . esc_attr__( 'Proceed to Checkout', 'zen-block-checkout' ) . '"' ),
					__( 'Leave empty to keep the default text.', 'zen-block-checkout' )
				);
				?>
			</table>

			<h3><?php esc_html_e( 'Notice', 'zen-block-checkout' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php
				self::row( __( 'Show a notice', 'zen-block-checkout' ), self::checkbox( $values, 'notice_enabled', __( 'Show the notice in the cart', 'zen-block-checkout' ) ) );
				self::row( __( 'Text', 'zen-block-checkout' ), self::textarea( $values, 'notice_text' ), __( 'For example: Delivery takes 3 to 5 days. A new line starts a new paragraph.', 'zen-block-checkout' ) );
				self::row(
					__( 'Style', 'zen-block-checkout' ),
					'<select name="' . esc_attr( self::field_name( 'notice_style' ) ) . '">'
					. '<option value="info"' . selected( $values['notice_style'], 'info', false ) . '>' . esc_html__( 'Info', 'zen-block-checkout' ) . '</option>'
					. '<option value="success"' . selected( $values['notice_style'], 'success', false ) . '>' . esc_html__( 'Success', 'zen-block-checkout' ) . '</option>'
					. '<option value="warning"' . selected( $values['notice_style'], 'warning', false ) . '>' . esc_html__( 'Warning', 'zen-block-checkout' ) . '</option>'
					. '</select>'
				);
				?>
			</table>

			<h3><?php esc_html_e( 'Free shipping progress bar', 'zen-block-checkout' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php
				self::row( __( 'Show progress bar', 'zen-block-checkout' ), self::checkbox( $values, 'progress_enabled', __( 'Show how much is left for free shipping', 'zen-block-checkout' ) ) );
				self::row(
					/* translators: %s: currency symbol. */
					sprintf( __( 'Free shipping from (%s)', 'zen-block-checkout' ), $currency ),
					self::input( $values, 'progress_threshold', 'number', 'min="0" step="0.01"' ),
					__( 'Use the same amount as your free shipping rule. Counted on the cart total after discounts, before tax and shipping.', 'zen-block-checkout' )
				);
				self::row( __( 'Message below the amount', 'zen-block-checkout' ), self::input( $values, 'progress_below' ), __( '{amount} is replaced with what is still missing.', 'zen-block-checkout' ) );
				self::row( __( 'Message when reached', 'zen-block-checkout' ), self::input( $values, 'progress_done' ) );
				?>
			</table>

			<h3><?php esc_html_e( 'Minimum order', 'zen-block-checkout' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php
				self::row( __( 'Minimum order', 'zen-block-checkout' ), self::checkbox( $values, 'min_enabled', __( 'Set a minimum order amount', 'zen-block-checkout' ) ) );
				self::row(
					/* translators: %s: currency symbol. */
					sprintf( __( 'Minimum amount (%s)', 'zen-block-checkout' ), $currency ),
					self::input( $values, 'min_amount', 'number', 'min="0" step="0.01"' )
				);
				self::row( __( 'Message', 'zen-block-checkout' ), self::input( $values, 'min_message' ), __( '{amount} is replaced with the minimum amount.', 'zen-block-checkout' ) );
				self::row( __( 'Block checkout', 'zen-block-checkout' ), self::checkbox( $values, 'min_enforce', __( 'Do not let customers place the order below the minimum', 'zen-block-checkout' ) ), __( 'If off, the message is only a notice in the cart.', 'zen-block-checkout' ) );
				?>
			</table>

			<h3><?php esc_html_e( 'Cart note', 'zen-block-checkout' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php
				self::row( __( 'Cart note', 'zen-block-checkout' ), self::checkbox( $values, 'note_enabled', __( 'Let customers leave a note in the cart', 'zen-block-checkout' ) ), __( 'The note is saved with the order and shown in the admin order page, the order emails and the order details.', 'zen-block-checkout' ) );
				self::row( __( 'Label', 'zen-block-checkout' ), self::input( $values, 'note_label' ) );
				self::row( __( 'Placeholder', 'zen-block-checkout' ), self::input( $values, 'note_placeholder' ) );
				?>
			</table>

			<h3><?php esc_html_e( 'Hide cart elements', 'zen-block-checkout' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php
				self::row(
					__( 'Hide', 'zen-block-checkout' ),
					self::checkbox( $values, 'hide_cross_sells', __( 'Cross-sells ("You may be interested in")', 'zen-block-checkout' ) )
					. '<br>' . self::checkbox( $values, 'hide_coupon', __( 'Coupon box', 'zen-block-checkout' ) ),
					__( 'These are hidden with CSS, so a WooCommerce update that renames them may bring them back.', 'zen-block-checkout' )
				);
				?>
			</table>

			<?php submit_button( __( 'Save cart settings', 'zen-block-checkout' ) ); ?>
		</form>
		<?php
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$pages       = self::pages();
		$tab         = self::current_tab();
		$result      = isset( $_GET['zenblch_result'] ) ? sanitize_key( wp_unslash( $_GET['zenblch_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
		$result_page = isset( $_GET['zenblch_page'] ) ? sanitize_key( wp_unslash( $_GET['zenblch_page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
		?>
		<div class="wrap zenblch-admin">
			<h1>
				<?php esc_html_e( 'Checkout Block Editor', 'zen-block-checkout' ); ?>
				<span class="zenblch-version">
					<?php
					echo esc_html( 'v' . ZENBLCH_VERSION );
					if ( defined( 'WC_VERSION' ) ) {
						echo esc_html( ' · WooCommerce ' . WC_VERSION );
					}
					?>
				</span>
			</h1>

			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper zenblch-tabs" role="tablist">
				<?php foreach ( $pages as $key => $page ) : ?>
					<a
						id="zenblch-tab-<?php echo esc_attr( $key ); ?>"
						class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"
						role="tab"
						data-tab="<?php echo esc_attr( $key ); ?>"
						aria-controls="zenblch-panel-<?php echo esc_attr( $key ); ?>"
						aria-selected="<?php echo $key === $tab ? 'true' : 'false'; ?>"
					><?php echo esc_html( $page['label'] ); ?></a>
				<?php endforeach; ?>
			</h2>

			<?php foreach ( $pages as $key => $page ) : ?>
				<?php $status = self::page_status( $key ); ?>
				<div
					id="zenblch-panel-<?php echo esc_attr( $key ); ?>"
					class="zenblch-panel"
					role="tabpanel"
					aria-labelledby="zenblch-tab-<?php echo esc_attr( $key ); ?>"
					<?php echo $key === $tab ? '' : 'hidden'; ?>
				>
					<?php if ( $result_page === $key && 'created' === $result && $status['exists'] ) : ?>
						<div class="notice notice-success inline zenblch-notice"><p>
							<?php
							/* translators: %s: page name, e.g. Checkout or Cart. */
							echo esc_html( sprintf( __( '%s page created. You can open it in the editor now.', 'zen-block-checkout' ), $page['label'] ) );
							?>
						</p></div>
					<?php elseif ( $result_page === $key && 'error' === $result ) : ?>
						<div class="notice notice-error inline zenblch-notice"><p><?php esc_html_e( 'The page could not be created. Try WooCommerce > Status > Tools > Create default WooCommerce pages.', 'zen-block-checkout' ); ?></p></div>
					<?php endif; ?>

					<?php self::render_page_notice( $key, $status ); ?>

					<?php
					if ( 'cart' === $key ) {
						self::render_cart_tab( $status );
					} else {
						self::render_checkout_tab( $status );
					}
					?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
