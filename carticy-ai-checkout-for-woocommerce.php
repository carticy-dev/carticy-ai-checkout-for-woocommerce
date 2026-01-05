<?php
/**
 * Plugin Name: AI Checkout for WooCommerce
 * Plugin URI: https://carticy.com/plugins/ai-checkout-for-woocommerce/
 * Description: ChatGPT Instant Checkout integration for WooCommerce using OpenAI Agentic Commerce Protocol
 * Version: 1.0.0
 * Author: alikhallad
 * Author URI: https://alikhallad.com
 * Text Domain: carticy-ai-checkout-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce, woocommerce-gateway-stripe
 * WC requires at least: 7.6
 * WC tested up to: 10.3.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'CARTICY_AI_CHECKOUT_VERSION', '1.0.0' );
define( 'CARTICY_AI_CHECKOUT_FILE', __FILE__ );
define( 'CARTICY_AI_CHECKOUT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARTICY_AI_CHECKOUT_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader.
if ( file_exists( CARTICY_AI_CHECKOUT_DIR . 'vendor/autoload.php' ) ) {
	require_once CARTICY_AI_CHECKOUT_DIR . 'vendor/autoload.php';
} else {
	// Fallback PSR-4 autoloader.
	spl_autoload_register(
		function ( $class_name ) {
			$prefix   = 'Carticy\\AiCheckout\\';
			$base_dir = CARTICY_AI_CHECKOUT_DIR . 'src/';

			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
				return;
			}

			$relative_class = substr( $class_name, $len );
			$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require $file;
			}
		}
	);
}

/**
 * Declare WooCommerce feature compatibility
 *
 * Must run BEFORE dependency checks to prevent false incompatibility warnings.
 *
 * @return void
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			// Declare HPOS (High-Performance Order Storage) compatibility.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);

			// Declare Cart/Checkout Blocks compatibility.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Plugin initialization
 *
 * Initialize plugin after WordPress and WooCommerce are loaded.
 *
 * @return void
 */
function carticy_ai_checkout_init(): void {
	// Check WooCommerce dependency.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'AI Checkout for WooCommerce', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						<?php esc_html_e( 'requires WooCommerce to be installed and active.', 'carticy-ai-checkout-for-woocommerce' ); ?>
						<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-small" style="margin-left: 10px;">
							<?php esc_html_e( 'Go to Plugins', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</a>
					</p>
				</div>
				<?php
			}
		);
		return;
	}

	// Check WooCommerce version.
	$required_wc_version = '7.6';
	if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, $required_wc_version, '<' ) ) {
		add_action(
			'admin_notices',
			function () use ( $required_wc_version ) {
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'AI Checkout for WooCommerce', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						<?php
						printf(
							/* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
							esc_html__( 'requires WooCommerce version %1$s or higher. You are running version %2$s.', 'carticy-ai-checkout-for-woocommerce' ),
							esc_html( $required_wc_version ),
							esc_html( WC_VERSION )
						);
						?>
						<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" class="button button-small" style="margin-left: 10px;">
							<?php esc_html_e( 'Update WooCommerce', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</a>
					</p>
				</div>
				<?php
			}
		);
		return;
	}

	// Check WooCommerce Stripe Gateway dependency.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! is_plugin_active( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' ) ) {
		add_action(
			'admin_notices',
			function () {
				// Use get_plugins() to check if plugin is installed (WordPress native function).
				$all_plugins  = get_plugins();
				$is_installed = isset( $all_plugins['woocommerce-gateway-stripe/woocommerce-gateway-stripe.php'] );
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'AI Checkout for WooCommerce', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						<?php
						if ( $is_installed ) {
							esc_html_e( 'requires WooCommerce Stripe Gateway to be active. The plugin is installed but not activated.', 'carticy-ai-checkout-for-woocommerce' );
						} else {
							esc_html_e( 'requires WooCommerce Stripe Gateway to be installed and active.', 'carticy-ai-checkout-for-woocommerce' );
						}
						?>
						<?php if ( $is_installed ) : ?>
							<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-small" style="margin-left: 10px;">
								<?php esc_html_e( 'Activate Plugin', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woocommerce-gateway-stripe' ) ); ?>" class="button button-small" style="margin-left: 10px;">
								<?php esc_html_e( 'Install Plugin', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</a>
						<?php endif; ?>
					</p>
				</div>
				<?php
			}
		);
		return;
	}

	// Initialize plugin.
	Init::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\carticy_ai_checkout_init' );

/**
 * Plugin activation
 *
 * @return void
 */
function carticy_ai_checkout_activate(): void {
	// Run activation tasks.
	// Dependency checks are handled in plugins_loaded with helpful admin notices.
	if ( class_exists( 'Carticy\\AiCheckout\\Core\\Activator' ) ) {
		Core\Activator::activate();
	}
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\carticy_ai_checkout_activate' );

/**
 * Plugin deactivation
 *
 * @return void
 */
function carticy_ai_checkout_deactivate(): void {
	Core\Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\carticy_ai_checkout_deactivate' );
