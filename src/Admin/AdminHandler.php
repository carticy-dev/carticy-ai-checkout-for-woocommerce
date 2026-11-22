<?php
/**
 * Admin Handler
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Admin;

use Carticy\AiCheckout\Services\PrerequisitesChecker;

/**
 * Handles admin area functionality
 */
final class AdminHandler {

	/**
	 * Mount element id for the React admin app (one bundle, screen chosen by boot).
	 */
	private const MOUNT_ID = 'carticy-ai-checkout-admin-root';

	/**
	 * Prerequisites checker service
	 *
	 * @var PrerequisitesChecker
	 */
	private PrerequisitesChecker $prerequisites;

	/**
	 * The plugin's admin screens, keyed by the page hook WordPress returned when each was registered.
	 *
	 * The one source for "is this our screen": other Carticy plugins' screens share the
	 * `carticy` prefix, so a name match would put this plugin's assets and notice rules on them.
	 *
	 * @var array<string, string> Page hook => React screen key.
	 */
	private array $screens = array();

	/**
	 * Constructor
	 *
	 * @param PrerequisitesChecker $prerequisites Prerequisites checker instance.
	 */
	public function __construct( PrerequisitesChecker $prerequisites ) {
		$this->prerequisites = $prerequisites;
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Hide third-party admin notices on plugin pages.
		add_action( 'in_admin_header', array( $this, 'hide_unrelated_notices' ), 99 );
		add_filter( 'admin_body_class', array( $this, 'add_takeover_body_class' ) );

		// Cache-bust all of this plugin's assets by file mtime: the enqueue
		// calls pin ver=CARTICY_AI_CHECKOUT_VERSION, which serves stale CSS/JS
		// from browser caches whenever assets change within a release.
		add_filter( 'style_loader_src', array( $this, 'bust_asset_cache' ) );
		add_filter( 'script_loader_src', array( $this, 'bust_asset_cache' ) );

		// Add ChatGPT order indicator to WooCommerce orders list (support both HPOS and legacy CPT).
		// HPOS (High-Performance Order Storage) - WC 8.2+.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_chatgpt_order_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_chatgpt_order_column' ), 10, 2 );

		// Legacy CPT-based orders - TODO: Remove when WooCommerce drops backward compatibility for CPT orders.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_chatgpt_order_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_chatgpt_order_column_legacy' ), 10, 2 );
	}

	/**
	 * Hide third-party admin notices on plugin pages
	 *
	 * Removes all WordPress core and third-party plugin notices on Carticy admin pages
	 * to maintain clean, branded UI. Only affects plugin pages, not other WordPress areas.
	 *
	 * Uses the in_admin_header hook with priority 99 to ensure it runs after most plugins
	 * have registered their notices but before they are rendered.
	 *
	 * @return void
	 */
	public function hide_unrelated_notices(): void {
		if ( null === $this->current_screen() ) {
			return;
		}

		// Remove all third-party admin notice hooks.
		// This suppresses WordPress core, WooCommerce, Stripe, and all other plugin notices.
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );

		// Note: Plugin's own notices using add_settings_error() will still display
		// via the settings_errors() WordPress core function, which is separate from admin_notices.
	}

	/**
	 * Add the design-system takeover body class on plugin screens
	 *
	 * `cv-takeover` gates the admin-ui chrome.css layer: full-bleed content
	 * area, hidden WP footer, hidden stray notices. Plugin screens own the
	 * whole inner page; the rest of wp-admin is untouched.
	 *
	 * @param string $classes Space-separated admin body classes.
	 * @return string
	 */
	public function add_takeover_body_class( string $classes ): string {
		return null === $this->current_screen() ? $classes : $classes . ' cv-takeover';
	}

	/**
	 * The React screen key of the current admin page, or null when it is not one of this plugin's pages.
	 *
	 * @return string|null
	 */
	private function current_screen(): ?string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return null === $screen ? null : ( $this->screens[ $screen->id ] ?? null );
	}

	/**
	 * Replace the ver query arg with the asset file's mtime
	 *
	 * Applies only to URLs under this plugin's directory (vendor design
	 * system CSS included), so other plugins' assets are untouched.
	 *
	 * @param string $src Enqueued asset URL.
	 * @return string
	 */
	public function bust_asset_cache( string $src ): string {
		$base_url = plugin_dir_url( dirname( __DIR__ ) );
		if ( ! str_starts_with( $src, $base_url ) ) {
			return $src;
		}

		$relative = strtok( substr( $src, strlen( $base_url ) ), '?' );
		$file     = plugin_dir_path( dirname( __DIR__ ) ) . $relative;
		if ( false !== $relative && file_exists( $file ) ) {
			$src = add_query_arg( 'ver', (string) filemtime( $file ), $src );
		}

		return $src;
	}

	/**
	 * Add admin menu pages
	 *
	 * @return void
	 */
	public function add_menu_pages(): void {
		$settings = add_menu_page(
			__( 'Carticy AI Checkout', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'AI Checkout', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-for-woocommerce',
			array( $this, 'render_settings_page' ),
			'dashicons-cart',
			58
		);

		$this->screens[ $settings ] = 'settings';

		// Add Product Feed Manager submenu.
		$feed = add_submenu_page(
			'carticy-ai-checkout-for-woocommerce',
			__( 'Product Feed Manager', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'Product Feed Manager', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-product-feed',
			array( $this, 'render_product_feed_page' )
		);
		if ( false !== $feed ) {
			$this->screens[ $feed ] = 'product-feed';
		}

		// Add Application Wizard submenu.
		$wizard = add_submenu_page(
			'carticy-ai-checkout-for-woocommerce',
			__( 'Application Wizard', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'Application Wizard', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-application-wizard',
			array( $this, 'render_application_wizard_page' )
		);
		if ( false !== $wizard ) {
			$this->screens[ $wizard ] = 'wizard';
		}

		// Add Analytics submenu.
		$analytics = add_submenu_page(
			'carticy-ai-checkout-for-woocommerce',
			__( 'Analytics', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'Analytics', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-analytics',
			array( $this, 'render_analytics_page' )
		);
		if ( false !== $analytics ) {
			$this->screens[ $analytics ] = 'analytics';
		}

		// Add Logs & Monitoring submenu.
		$logs = add_submenu_page(
			'carticy-ai-checkout-for-woocommerce',
			__( 'Logs & Monitoring', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'Logs & Monitoring', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-logs',
			array( $this, 'render_logs_page' )
		);
		if ( false !== $logs ) {
			$this->screens[ $logs ] = 'logs';
		}
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$screen = $this->screens[ $hook ] ?? null;
		if ( null === $screen ) {
			return;
		}

		// Unified Carticy admin design system (carticy/admin-ui): tokens +
		// components, scoped to the .cv-root mount the React app marks.
		// Plugin-unique handles by design so other Carticy plugins can carry
		// their own version side by side.
		wp_enqueue_style(
			'carticy-ai-checkout-admin-ui-tokens',
			plugin_dir_url( dirname( __DIR__ ) ) . 'vendor/carticy/admin-ui/css/tokens.css',
			array(),
			CARTICY_AI_CHECKOUT_VERSION
		);

		wp_enqueue_style(
			'carticy-ai-checkout-admin-ui',
			plugin_dir_url( dirname( __DIR__ ) ) . 'vendor/carticy/admin-ui/css/components.css',
			array( 'carticy-ai-checkout-admin-ui-tokens' ),
			CARTICY_AI_CHECKOUT_VERSION
		);

		// wp-admin chrome takeover (gated by the cv-takeover body class):
		// full-bleed content area, no WP footer, no stray notices.
		wp_enqueue_style(
			'carticy-ai-checkout-admin-ui-chrome',
			plugin_dir_url( dirname( __DIR__ ) ) . 'vendor/carticy/admin-ui/css/chrome.css',
			array( 'carticy-ai-checkout-admin-ui' ),
			CARTICY_AI_CHECKOUT_VERSION
		);

		// Enqueue admin styles.
		wp_enqueue_style(
			'carticy-ai-checkout-admin',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/dist/admin.min.css',
			array( 'dashicons', 'carticy-ai-checkout-admin-ui' ),
			CARTICY_AI_CHECKOUT_VERSION
		);

		$this->enqueue_admin_app( $screen, 'analytics' === $screen ? array( 'defaultDays' => 30 ) : array() );
	}

	/**
	 * Enqueue the React admin app for a given screen and pass its boot data.
	 *
	 * One bundle serves every migrated screen; the boot object names the screen
	 * to render and carries any screen-specific config. React, api-fetch, i18n and
	 * date resolve to the WordPress script globals declared as dependencies; the
	 * bundle's strings are translated through wp-i18n from the plugin's
	 * languages folder or its translate.wordpress.org pack.
	 *
	 * @param string               $screen      Screen key the bundle should render.
	 * @param array<string, mixed> $screen_data Screen-specific boot config.
	 */
	private function enqueue_admin_app( string $screen, array $screen_data = array() ): void {
		wp_enqueue_script(
			'carticy-ai-checkout-admin-app',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/dist/admin-app.min.js',
			array( 'wp-element', 'wp-api-fetch', 'wp-i18n', 'wp-date' ),
			CARTICY_AI_CHECKOUT_VERSION,
			true
		);
		wp_set_script_translations( 'carticy-ai-checkout-admin-app', 'carticy-ai-checkout-for-woocommerce', plugin_dir_path( dirname( __DIR__ ) ) . 'languages' );

		wp_localize_script(
			'carticy-ai-checkout-admin-app',
			'carticyAiCheckoutAdmin',
			array(
				'restNamespace' => \Carticy\AiCheckout\Rest\AbstractAdminController::NAMESPACE,
				'mountId'       => self::MOUNT_ID,
				'screen'        => $screen,
				'version'       => defined( 'CARTICY_AI_CHECKOUT_VERSION' ) ? CARTICY_AI_CHECKOUT_VERSION : '',
				'adminUrl'      => admin_url(),
				$screen         => $screen_data,
			)
		);
	}

	/**
	 * Output the React mount element for a migrated screen.
	 */
	private function render_admin_app(): void {
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div id="' . esc_attr( self::MOUNT_ID ) . '"></div>';
		}
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// React screen (see assets/js/src/admin-app/screens/Settings.jsx and
		// Rest\SettingsController). The bundle + boot data are enqueued in
		// enqueue_assets; here we only output the mount element.
		$this->render_admin_app();
	}

	/**
	 * Render product feed manager page
	 *
	 * @return void
	 */
	public function render_product_feed_page(): void {
		// React screen (see assets/js/src/admin-app/screens/ProductFeed.jsx and
		// Rest\ProductFeedController).
		$this->render_admin_app();
	}

	/**
	 * Render application wizard page
	 *
	 * @return void
	 */
	public function render_application_wizard_page(): void {
		// React screen (see assets/js/src/admin-app/screens/Wizard.jsx and
		// Rest\WizardController).
		$this->render_admin_app();
	}

	/**
	 * Render analytics page
	 *
	 * @return void
	 */
	public function render_analytics_page(): void {
		// The analytics screen is a React app (see assets/js/src/admin-app and
		// Rest\AnalyticsController). The bundle + boot data are enqueued in
		// enqueue_assets; here we only output the mount element.
		$this->render_admin_app();
	}

	/**
	 * Render logs and monitoring page
	 *
	 * @return void
	 */
	public function render_logs_page(): void {
		// React screen (see assets/js/src/admin-app/screens/Logs.jsx and
		// Rest\LogsController).
		$this->render_admin_app();
	}

	/**
	 * Add ChatGPT order column to orders list
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_chatgpt_order_column( array $columns ): array {
		// Insert after 'order_status' column.
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'order_status' === $key ) {
				$new_columns['chatgpt_order'] = __( 'Source', 'carticy-ai-checkout-for-woocommerce' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render ChatGPT order column content (HPOS)
	 *
	 * @param string $column Column name.
	 * @param mixed  $order Order object or ID.
	 * @return void
	 */
	public function render_chatgpt_order_column( string $column, mixed $order ): void {
		if ( 'chatgpt_order' !== $column ) {
			return;
		}

		// Get order object if not already.
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return;
		}

		// Check if this is a ChatGPT order.
		$is_chatgpt_order = 'yes' === $order->get_meta( '_chatgpt_checkout' );

		if ( $is_chatgpt_order ) {
			echo '<span style="display: inline-flex; align-items: center; gap: 3px; color: #2271b1; font-size: 11px; font-weight: 500;" title="' . esc_attr__( 'ChatGPT Instant Checkout', 'carticy-ai-checkout-for-woocommerce' ) . '">';
			echo '<span class="dashicons dashicons-admin-comments" style="font-size: 16px; width: 16px; height: 16px;"></span>';
			echo '<span style="text-decoration: underline; text-decoration-style: dotted; text-underline-offset: 2px;">' . esc_html__( 'AI', 'carticy-ai-checkout-for-woocommerce' ) . '</span>';
			echo '</span>';
		} else {
			echo '<span style="color: #999;">—</span>';
		}
	}

	/**
	 * Render ChatGPT order column content (Legacy CPT)
	 *
	 * @param string $column Column name.
	 * @param int    $order_id Order ID.
	 * @return void
	 */
	public function render_chatgpt_order_column_legacy( string $column, int $order_id ): void {
		$this->render_chatgpt_order_column( $column, $order_id );
	}
}
