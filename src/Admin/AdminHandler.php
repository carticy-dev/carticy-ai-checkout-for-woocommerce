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
	 * Prerequisites checker service
	 *
	 * @var PrerequisitesChecker
	 */
	private PrerequisitesChecker $prerequisites;

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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_carticy_ai_checkout_refresh_ip_ranges', array( $this, 'handle_ip_refresh' ) );

		// Hide third-party admin notices on plugin pages.
		add_action( 'in_admin_header', array( $this, 'hide_unrelated_notices' ), 99 );

		// AJAX handlers for settings page.
		add_action( 'wp_ajax_carticy_ai_checkout_regenerate_api_key', array( $this, 'ajax_regenerate_api_key' ) );
		add_action( 'wp_ajax_carticy_ai_checkout_regenerate_webhook_secret', array( $this, 'ajax_regenerate_webhook_secret' ) );
		add_action( 'wp_ajax_carticy_ai_checkout_test_webhook', array( $this, 'ajax_test_webhook' ) );

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
		// Check if we're on a plugin page using the page parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

		// Only hide notices on Carticy plugin pages.
		if ( ! str_starts_with( $page, 'carticy-ai-checkout-for-woocommerce' ) ) {
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
	 * Add admin menu pages
	 *
	 * @return void
	 */
	public function add_menu_pages(): void {
		add_menu_page(
			__( 'Carticy AI Checkout', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'AI Checkout', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-for-woocommerce',
			array( $this, 'render_settings_page' ),
			'dashicons-cart',
			58
		);

		// Add Product Feed Manager submenu.
		add_submenu_page(
			'carticy-ai-checkout-for-woocommerce',
			__( 'Product Feed Manager', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'Product Feed Manager', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-product-feed',
			array( $this, 'render_product_feed_page' )
		);

		// Add Application Wizard submenu.
		add_submenu_page(
			'carticy-ai-checkout-for-woocommerce',
			__( 'Application Wizard', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'Application Wizard', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-application-wizard',
			array( $this, 'render_application_wizard_page' )
		);

		// Add Analytics submenu.
		add_submenu_page(
			'carticy-ai-checkout-for-woocommerce',
			__( 'Analytics', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'Analytics', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-analytics',
			array( $this, 'render_analytics_page' )
		);

		// Add Logs & Monitoring submenu.
		add_submenu_page(
			'carticy-ai-checkout-for-woocommerce',
			__( 'Logs & Monitoring', 'carticy-ai-checkout-for-woocommerce' ),
			__( 'Logs & Monitoring', 'carticy-ai-checkout-for-woocommerce' ),
			'manage_options',
			'carticy-ai-checkout-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on plugin pages.
		if ( ! str_contains( $hook, 'carticy' ) ) {
			return;
		}

		// Enqueue unified layout styles (Carticy branding + design system) from Composer package.
		wp_enqueue_style(
			'carticy-design-system',
			plugin_dir_url( dirname( __DIR__ ) ) . 'vendor/carticy/design-system/dist/design-system.min.css',
			array(),
			CARTICY_AI_CHECKOUT_VERSION
		);

		wp_enqueue_style(
			'carticy-ai-checkout-layout',
			plugin_dir_url( dirname( __DIR__ ) ) . 'vendor/carticy/admin-layout/dist/admin-layout.min.css',
			array( 'carticy-design-system' ),
			CARTICY_AI_CHECKOUT_VERSION
		);

		// Enqueue admin styles.
		wp_enqueue_style(
			'carticy-ai-checkout-admin',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/dist/admin.min.css',
			array( 'dashicons', 'carticy-ai-checkout-layout' ),
			CARTICY_AI_CHECKOUT_VERSION
		);

		// Enqueue admin JavaScript (for tabs, etc.) from Composer package.
		wp_enqueue_script(
			'carticy-ai-checkout-admin',
			plugin_dir_url( dirname( __DIR__ ) ) . 'vendor/carticy/admin-components/dist/admin-components.min.js',
			array( 'jquery' ),
			CARTICY_AI_CHECKOUT_VERSION,
			true
		);

		// Enqueue settings page JavaScript on main settings page.
		if ( str_contains( $hook, 'carticy-ai-checkout-for-woocommerce' ) && ! str_contains( $hook, 'logs' ) && ! str_contains( $hook, 'wizard' ) && ! str_contains( $hook, 'product-feed' ) && ! str_contains( $hook, 'analytics' ) ) {
			wp_enqueue_script(
				'carticy-ai-checkout-settings',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/dist/admin-settings.min.js',
				array( 'jquery' ),
				CARTICY_AI_CHECKOUT_VERSION,
				true
			);

			wp_localize_script(
				'carticy-ai-checkout-settings',
				'carticySettings',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'carticy_settings_nonce' ),
					'i18n'    => array(
						'copied'            => __( 'Copied!', 'carticy-ai-checkout-for-woocommerce' ),
						'copyFailed'        => __( 'Copy failed', 'carticy-ai-checkout-for-woocommerce' ),
						'confirmRegenerate' => __( 'Are you sure you want to regenerate? This will invalidate the current key.', 'carticy-ai-checkout-for-woocommerce' ),
						'regenerating'      => __( 'Regenerating...', 'carticy-ai-checkout-for-woocommerce' ),
						'testingWebhook'    => __( 'Testing webhook...', 'carticy-ai-checkout-for-woocommerce' ),
						'webhookSuccess'    => __( 'Webhook test successful!', 'carticy-ai-checkout-for-woocommerce' ),
						'webhookFailed'     => __( 'Webhook test failed. Check the logs for details.', 'carticy-ai-checkout-for-woocommerce' ),
					),
				)
			);
		}

		// Enqueue product feed manager styles on product feed page.
		if ( str_contains( $hook, 'carticy-ai-checkout-product-feed' ) ) {
			wp_enqueue_style(
				'carticy-ai-checkout-product-manager',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/dist/admin-product-manager.min.css',
				array( 'carticy-ai-checkout-admin' ),
				CARTICY_AI_CHECKOUT_VERSION
			);

			wp_enqueue_script(
				'carticy-ai-checkout-product-manager',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/dist/admin-product-manager.min.js',
				array( 'jquery' ),
				CARTICY_AI_CHECKOUT_VERSION,
				true
			);
		}

		// Enqueue wizard styles and scripts on application wizard page.
		if ( str_contains( $hook, 'carticy-ai-checkout-application-wizard' ) ) {
			wp_enqueue_style(
				'carticy-ai-checkout-wizard',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/dist/admin-wizard.min.css',
				array( 'carticy-ai-checkout-admin' ),
				CARTICY_AI_CHECKOUT_VERSION
			);

			// Enqueue inline wizard styles (prerequisites, test setup, conformance).
			wp_enqueue_style(
				'carticy-ai-checkout-wizard-inline',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin-wizard-inline.css',
				array( 'carticy-ai-checkout-wizard' ),
				CARTICY_AI_CHECKOUT_VERSION
			);

			// Enqueue wizard navigation script.
			wp_enqueue_script(
				'carticy-ai-checkout-wizard-navigation',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/admin-wizard-navigation.js',
				array(),
				CARTICY_AI_CHECKOUT_VERSION,
				true
			);

			wp_localize_script(
				'carticy-ai-checkout-wizard-navigation',
				'carticyWizardNav',
				array(
					'adminPostUrl' => admin_url( 'admin-post.php' ),
					'nonce'        => wp_create_nonce( 'carticy_wizard_navigate' ),
				)
			);

			// Enqueue conformance test runner script.
			wp_enqueue_script(
				'carticy-ai-checkout-wizard-conformance',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/admin-wizard-conformance.js',
				array( 'jquery', 'carticy-ai-checkout-admin' ),
				CARTICY_AI_CHECKOUT_VERSION,
				true
			);

			wp_localize_script(
				'carticy-ai-checkout-wizard-conformance',
				'carticyConformance',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'carticy_wizard_tests' ),
					'i18n'    => array(
						'runningTests'             => __( 'Running Tests...', 'carticy-ai-checkout-for-woocommerce' ),
						'runningTestsIndividually' => __( 'Running tests individually... (Test 1 of 17)', 'carticy-ai-checkout-for-woocommerce' ),
						'runningTest'              => __( 'Running test', 'carticy-ai-checkout-for-woocommerce' ),
						'of'                       => __( 'of', 'carticy-ai-checkout-for-woocommerce' ),
						'testTimeout'              => __( 'Test timed out after 60 seconds', 'carticy-ai-checkout-for-woocommerce' ),
						'viewDetails'              => __( 'View Details', 'carticy-ai-checkout-for-woocommerce' ),
						'enableFilter'             => __( 'Enable Filter', 'carticy-ai-checkout-for-woocommerce' ),
						'allTestsCompleted'        => __( 'All tests completed!', 'carticy-ai-checkout-for-woocommerce' ),
						'runAllTestsAgain'         => __( 'Run All Tests Again', 'carticy-ai-checkout-for-woocommerce' ),
						'testName'                 => __( 'Test Name:', 'carticy-ai-checkout-for-woocommerce' ),
						'errorMessage'             => __( 'Error Message:', 'carticy-ai-checkout-for-woocommerce' ),
						'requestData'              => __( 'Request Data:', 'carticy-ai-checkout-for-woocommerce' ),
						'responseData'             => __( 'Response Data:', 'carticy-ai-checkout-for-woocommerce' ),
						'testDetails'              => __( 'Test Details', 'carticy-ai-checkout-for-woocommerce' ),
						'fixing'                   => __( 'Fixing...', 'carticy-ai-checkout-for-woocommerce' ),
						'fixed'                    => __( '✓ Fixed', 'carticy-ai-checkout-for-woocommerce' ),
						'robotsFilterEnabled'      => __( 'Robots.txt filter enabled. Re-run tests to verify.', 'carticy-ai-checkout-for-woocommerce' ),
						'failedToEnableFilter'     => __( 'Failed to enable filter. Please try manually.', 'carticy-ai-checkout-for-woocommerce' ),
						'errorOccurred'            => __( 'An error occurred. Please try again.', 'carticy-ai-checkout-for-woocommerce' ),
					),
				)
			);

			// Enqueue integration scripts (API key regeneration, JSON copy/download).
			wp_enqueue_script(
				'carticy-ai-checkout-wizard-integration',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/admin-wizard-integration.js',
				array( 'jquery' ),
				CARTICY_AI_CHECKOUT_VERSION,
				true
			);

			wp_localize_script(
				'carticy-ai-checkout-wizard-integration',
				'carticyIntegration',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'carticy_settings_nonce' ),
					'downloadDate' => gmdate( 'Y-m-d' ),
					'i18n'         => array(
						'copied'                         => __( 'Copied!', 'carticy-ai-checkout-for-woocommerce' ),
						'confirmRegenerateApiKey'        => __( 'Are you sure you want to regenerate the API key? This will invalidate the current key.', 'carticy-ai-checkout-for-woocommerce' ),
						'confirmRegenerateWebhookSecret' => __( 'Are you sure you want to regenerate the webhook secret?', 'carticy-ai-checkout-for-woocommerce' ),
						'failedToRegenerate'             => __( 'Failed to regenerate. Please try again.', 'carticy-ai-checkout-for-woocommerce' ),
						'failedToCopy'                   => __( 'Failed to copy to clipboard. Please copy manually.', 'carticy-ai-checkout-for-woocommerce' ),
						'errorOccurred'                  => __( 'An error occurred. Please try again.', 'carticy-ai-checkout-for-woocommerce' ),
					),
				)
			);
		}

		// Enqueue analytics dashboard styles on analytics page.
		if ( str_contains( $hook, 'carticy-ai-checkout-analytics' ) ) {
			wp_enqueue_style(
				'carticy-ai-checkout-analytics',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/dist/analytics-dashboard.min.css',
				array( 'carticy-ai-checkout-admin' ),
				CARTICY_AI_CHECKOUT_VERSION
			);
		}

		// Enqueue logs viewer styles and scripts on logs page.
		if ( str_contains( $hook, 'carticy-ai-checkout-logs' ) ) {
			wp_enqueue_style(
				'carticy-ai-checkout-logs',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/dist/logs-viewer.min.css',
				array( 'carticy-ai-checkout-admin' ),
				CARTICY_AI_CHECKOUT_VERSION
			);

			// Enqueue logs viewer script.
			wp_enqueue_script(
				'carticy-ai-checkout-logs-viewer',
				plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/admin-logs-viewer.js',
				array( 'jquery', 'carticy-ai-checkout-admin' ),
				CARTICY_AI_CHECKOUT_VERSION,
				true
			);

			wp_localize_script(
				'carticy-ai-checkout-logs-viewer',
				'carticyLogsViewer',
				array(
					'i18n' => array(
						'request'        => __( 'Request:', 'carticy-ai-checkout-for-woocommerce' ),
						'response'       => __( 'Response:', 'carticy-ai-checkout-for-woocommerce' ),
						'apiContext'     => __( 'API Context', 'carticy-ai-checkout-for-woocommerce' ),
						'errorMessage'   => __( 'Error Message:', 'carticy-ai-checkout-for-woocommerce' ),
						'contextDetails' => __( 'Context Details:', 'carticy-ai-checkout-for-woocommerce' ),
						'errorDetails'   => __( 'Error Details', 'carticy-ai-checkout-for-woocommerce' ),
					),
				)
			);
		}
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'carticy_ai_checkout_settings',
			'carticy_ai_checkout_test_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 'yes',
			)
		);

		register_setting(
			'carticy_ai_checkout_settings',
			'carticy_ai_checkout_delete_data_on_uninstall',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 'no',
			)
		);

		register_setting(
			'carticy_ai_checkout_settings',
			'carticy_ai_checkout_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		register_setting(
			'carticy_ai_checkout_settings',
			'carticy_ai_checkout_enable_ip_allowlist',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 'no',
			)
		);
	}

	/**
	 * Sanitize checkbox value
	 *
	 * @param mixed $value Input value.
	 * @return string 'yes' or 'no'.
	 */
	public function sanitize_checkbox( mixed $value ): string {
		return ( 'yes' === $value || '1' === $value || 1 === $value ) ? 'yes' : 'no';
	}

	/**
	 * Render admin page with unified Carticy layout
	 *
	 * Wraps page content with branded header and footer for consistency across all admin pages.
	 *
	 * @param string   $page_title   Page title for header (used by template).
	 * @param callable $content      Content callback function (used by template).
	 * @param array    $content_data Data to pass to content callback (used by template).
	 * @param array    $header_data  Additional header data (used by template).
	 * @return void
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Parameters used by included template.
	 */
	private function render_with_layout( string $page_title, callable $content, array $content_data = array(), array $header_data = array() ): void {
		// Check if test mode is enabled and set badge variables for header template.
		$show_test_badge = ( 'yes' === get_option( 'carticy_ai_checkout_test_mode', 'yes' ) );
		$test_badge_text = __( 'Test Mode', 'carticy-ai-checkout-for-woocommerce' );

		require plugin_dir_path( dirname( __DIR__ ) ) . 'vendor/carticy/admin-layout/templates/layout-wrapper.php';
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle IP refresh messages (must be before render_with_layout for settings_errors to work).
		// Verify nonce for admin message display (set in handle_ip_refresh redirect).
		if ( isset( $_GET['message'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_admin_message' ) ) {
				// Invalid nonce - skip message display for security.
				$message = '';
			} else {
				$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
			}

			if ( 'ip_refresh_success' === $message ) {
				$ip_ranges = get_option( 'carticy_ai_checkout_openai_ip_ranges_backup', array() );
				$count     = count( $ip_ranges );
				add_settings_error(
					'carticy_ai_checkout_settings',
					'ip_refresh_success',
					'<strong>' . esc_html__( 'IP ranges refreshed successfully!', 'carticy-ai-checkout-for-woocommerce' ) . '</strong>' .
					sprintf(
						/* translators: %d: number of IP ranges loaded */
						esc_html__( '%d IP ranges loaded from OpenAI.', 'carticy-ai-checkout-for-woocommerce' ),
						$count
					),
					'success'
				);
			} elseif ( 'ip_refresh_failed' === $message ) {
				add_settings_error(
					'carticy_ai_checkout_settings',
					'ip_refresh_failed',
					'<strong>' . esc_html__( 'Failed to refresh IP ranges.', 'carticy-ai-checkout-for-woocommerce' ) . '</strong>' .
					esc_html__( 'Unable to fetch fresh ranges from OpenAI. Using cached ranges.', 'carticy-ai-checkout-for-woocommerce' ),
					'error'
				);
			}
		}

		// Get raw prerequisites data from checker.
		$prerequisites_raw = $this->prerequisites->check_all();

		// Transform prerequisites data to match template format.
		$prerequisites = array();
		$labels        = array(
			'wordpress_version' => __( 'WordPress Version', 'carticy-ai-checkout-for-woocommerce' ),
			'php_version'       => __( 'PHP Version', 'carticy-ai-checkout-for-woocommerce' ),
			'woocommerce'       => __( 'WooCommerce', 'carticy-ai-checkout-for-woocommerce' ),
			'stripe_gateway'    => __( 'WooCommerce Stripe Gateway', 'carticy-ai-checkout-for-woocommerce' ),
			'stripe_keys'       => __( 'Stripe API Keys', 'carticy-ai-checkout-for-woocommerce' ),
			'ssl'               => __( 'SSL Certificate', 'carticy-ai-checkout-for-woocommerce' ),
			'permalinks'        => __( 'Permalink Structure', 'carticy-ai-checkout-for-woocommerce' ),
			'stripe_api'        => __( 'Stripe API Connection', 'carticy-ai-checkout-for-woocommerce' ),
		);

		foreach ( $prerequisites_raw as $key => $check ) {
			$prerequisites[ $key ] = array(
				'label'        => $labels[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) ),
				'status'       => $check['passed'],
				'message'      => $check['message'],
				'action_url'   => $check['action'] ?? '',
				'action_label' => __( 'Fix This', 'carticy-ai-checkout-for-woocommerce' ),
			);
		}

		$api_key          = get_option( 'carticy_ai_checkout_api_key', '' );
		$webhook_secret   = get_option( 'carticy_ai_checkout_webhook_secret', '' );
		$product_feed_url = rest_url( 'carticy-ai-checkout/v1/products' );

		// Render with unified layout.
		$this->render_with_layout(
			__( 'AI Checkout Settings', 'carticy-ai-checkout-for-woocommerce' ),
			function ( $data ) {
				// Extract variables for template.
				extract( $data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

				// Include the settings page template.
				require plugin_dir_path( dirname( __DIR__ ) ) . 'templates/admin/settings-page.php';
			},
			compact( 'prerequisites', 'api_key', 'webhook_secret', 'product_feed_url' )
		);
	}

	/**
	 * Render product feed manager page
	 *
	 * @return void
	 */
	public function render_product_feed_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get ProductFeedManager from container.
		$product_feed_manager = \Carticy\AiCheckout\Init::get_instance()->get_service( 'product_feed_manager' );

		// Use reflection to handle bulk actions and product toggle.
		$reflection = new \ReflectionClass( $product_feed_manager );

		$handle_bulk_actions_method = $reflection->getMethod( 'handle_bulk_actions' );
		$handle_bulk_actions_method->setAccessible( true );
		$handle_bulk_actions_method->invoke( $product_feed_manager );

		$handle_product_toggle_method = $reflection->getMethod( 'handle_product_toggle' );
		$handle_product_toggle_method->setAccessible( true );
		$handle_product_toggle_method->invoke( $product_feed_manager );

		// Get ProductQualityChecker from ProductFeedManager.
		$quality_checker_property = $reflection->getProperty( 'quality_checker' );
		$quality_checker_property->setAccessible( true );
		$quality_checker = $quality_checker_property->getValue( $product_feed_manager );

		// Create list table instance.
		$list_table = new \Carticy\AiCheckout\Admin\ProductsListTable( $quality_checker );
		$list_table->prepare_items();

		// Get statistics.
		$get_stats_method = $reflection->getMethod( 'get_feed_statistics' );
		$get_stats_method->setAccessible( true );
		$stats = $get_stats_method->invoke( $product_feed_manager );

		// Render with unified layout.
		$this->render_with_layout(
			__( 'Product Feed Manager', 'carticy-ai-checkout-for-woocommerce' ),
			function ( $template_data ) {
				// Extract variables for template.
				extract( $template_data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

				// Include the product feed manager template.
				require plugin_dir_path( dirname( __DIR__ ) ) . 'templates/admin/product-feed-manager.php';
			},
			compact( 'stats', 'list_table' )
		);
	}

	/**
	 * Render application wizard page
	 *
	 * @return void
	 */
	public function render_application_wizard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get ApplicationWizard from container.
		$application_wizard = \Carticy\AiCheckout\Init::get_instance()->get_service( 'application_wizard' );

		// Use reflection to access private/protected methods.
		$reflection = new \ReflectionClass( $application_wizard );

		// Get wizard service.
		$wizard_service_property = $reflection->getProperty( 'wizard_service' );
		$wizard_service_property->setAccessible( true );
		$wizard_service = $wizard_service_property->getValue( $application_wizard );

		// Get wizard data.
		$current_step = $wizard_service->get_current_step();
		$wizard_data  = $wizard_service->get_wizard_data();
		$total_steps  = $wizard_service->get_total_steps();
		$completion   = $wizard_service->get_completion_percentage();

		// Create a wrapper object with public methods for template.
		$wizard_instance = new class( $application_wizard, $reflection, $wizard_service ) {
			/**
			 * Application wizard instance.
			 *
			 * @var object
			 */
			private $wizard;

			/**
			 * Reflection class instance.
			 *
			 * @var object
			 */
			private $reflection;

			/**
			 * Wizard service instance.
			 *
			 * @var object
			 */
			private $wizard_service;

			/**
			 * Constructor.
			 *
			 * @param object $wizard         Application wizard instance.
			 * @param object $reflection     Reflection class instance.
			 * @param object $wizard_service Wizard service instance.
			 */
			public function __construct( $wizard, $reflection, $wizard_service ) {
				$this->wizard         = $wizard;
				$this->reflection     = $reflection;
				$this->wizard_service = $wizard_service;
			}

			/**
			 * Check if a step is completed.
			 *
			 * @param int $step Step number.
			 * @return bool True if step is completed.
			 */
			public function is_step_completed( int $step ): bool {
				return $this->wizard_service->is_step_completed( $step );
			}

			/**
			 * Get step title via reflection.
			 *
			 * @param int $step Step number.
			 * @return string Step title.
			 */
			public function get_step_title_public( int $step ): string {
				$method = $this->reflection->getMethod( 'get_step_title' );
				$method->setAccessible( true );
				return $method->invoke( $this->wizard, $step );
			}

			/**
			 * Render step content via reflection.
			 *
			 * @param int $step Step number.
			 */
			public function render_step_public( int $step ): void {
				$method = $this->reflection->getMethod( 'render_step' );
				$method->setAccessible( true );
				$method->invoke( $this->wizard, $step );
			}
		};

		// Render with unified layout.
		$this->render_with_layout(
			__( 'OpenAI Application Wizard', 'carticy-ai-checkout-for-woocommerce' ),
			function ( $template_data ) {
				// Extract variables for template.
				extract( $template_data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

				// Include the application wizard template.
				require plugin_dir_path( dirname( __DIR__ ) ) . 'templates/admin/application-wizard.php';
			},
			compact( 'current_step', 'total_steps', 'completion', 'wizard_data', 'wizard_instance' )
		);
	}

	/**
	 * Render analytics page
	 *
	 * @return void
	 */
	public function render_analytics_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get AnalyticsDashboard from container.
		$analytics_dashboard = \Carticy\AiCheckout\Init::get_instance()->get_service( 'analytics_dashboard' );

		// Get analytics data using reflection to access private methods.
		$reflection = new \ReflectionClass( $analytics_dashboard );

		// Get days filter.
		$days_property = $reflection->getProperty( 'days' );
		$days_property->setAccessible( true );
		$days = $days_property->getValue( $analytics_dashboard );

		// Handle filter request with nonce verification.
		if ( isset( $_GET['days'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_analytics_filter' ) ) {
				$days_filter = absint( wp_unslash( $_GET['days'] ) );
				if ( in_array( $days_filter, array( 7, 30, 90 ), true ) ) {
					$days = $days_filter;
				}
			}
		}

		// Get analytics service.
		$analytics_property = $reflection->getProperty( 'analytics' );
		$analytics_property->setAccessible( true );
		$analytics = $analytics_property->getValue( $analytics_dashboard );

		// Get analytics data.
		$chatgpt_orders  = $analytics->get_chatgpt_order_count( $days );
		$chatgpt_revenue = $analytics->get_chatgpt_revenue( $days );
		$regular_orders  = $analytics->get_regular_order_count( $days );
		$regular_revenue = $analytics->get_regular_revenue( $days );
		$conversion_rate = $analytics->get_conversion_rate( $days );
		$chatgpt_aov     = $analytics->get_average_order_value( 'chatgpt', $days );
		$regular_aov     = $analytics->get_average_order_value( 'regular', $days );
		$recent_orders   = $analytics->get_recent_chatgpt_orders( 10 );
		$status_stats    = $analytics->get_order_stats_by_status( $days );

		// Prepare template data.
		$data = array(
			'days'            => $days,
			'chatgpt_orders'  => $chatgpt_orders,
			'chatgpt_revenue' => $chatgpt_revenue,
			'regular_orders'  => $regular_orders,
			'regular_revenue' => $regular_revenue,
			'conversion_rate' => $conversion_rate,
			'chatgpt_aov'     => $chatgpt_aov,
			'regular_aov'     => $regular_aov,
			'recent_orders'   => $recent_orders,
			'status_stats'    => $status_stats,
			'total_orders'    => $chatgpt_orders + $regular_orders,
			'total_revenue'   => $chatgpt_revenue + $regular_revenue,
		);

		// Render with unified layout.
		$this->render_with_layout(
			__( 'Analytics Dashboard', 'carticy-ai-checkout-for-woocommerce' ),
			function ( $template_data ) {
				// Extract variables for template.
				extract( $template_data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

				// Include the analytics dashboard template.
				require plugin_dir_path( dirname( __DIR__ ) ) . 'templates/admin/analytics-dashboard.php';
			},
			$data
		);
	}

	/**
	 * Render logs and monitoring page
	 *
	 * @return void
	 */
	public function render_logs_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get LogsViewer from container.
		$logs_viewer = \Carticy\AiCheckout\Init::get_instance()->get_service( 'logs_viewer' );

		// Get active tab with nonce verification.
		$active_tab = 'api'; // Default tab.
		if ( isset( $_GET['tab'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_logs_tab' ) ) {
				$active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
			}
		}

		// Get tabs configuration.
		$tabs = $logs_viewer->get_tabs();

		// Get tab-specific data.
		$data = $this->get_logs_tab_data( $logs_viewer, $active_tab );

		// Render with unified layout.
		$this->render_with_layout(
			__( 'Logs & Monitoring', 'carticy-ai-checkout-for-woocommerce' ),
			function ( $template_data ) {
				// Extract variables for template.
				extract( $template_data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

				// Include the logs viewer template.
				require plugin_dir_path( dirname( __DIR__ ) ) . 'templates/admin/logs-viewer.php';
			},
			compact( 'active_tab', 'tabs', 'data' )
		);
	}

	/**
	 * Get logs tab data
	 *
	 * @param \Carticy\AiCheckout\Admin\LogsViewer $logs_viewer LogsViewer instance.
	 * @param string                               $tab         Active tab.
	 * @return array Tab data.
	 */
	private function get_logs_tab_data( $logs_viewer, string $tab ): array {
		// Use reflection to access private method (temporary solution).
		$reflection = new \ReflectionClass( $logs_viewer );
		$method     = $reflection->getMethod( 'get_tab_data' );
		$method->setAccessible( true );

		return $method->invoke( $logs_viewer, $tab );
	}

	/**
	 * Handle manual IP refresh request
	 *
	 * @return void
	 */
	public function handle_ip_refresh(): void {
		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_refresh_ips' ) ) {
			wp_die( esc_html__( 'Security check failed', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		// Get IP allowlist service from container.
		$ip_allowlist   = \Carticy\AiCheckout\Init::get_instance()->get_service( 'ip_allowlist' );
		$refresh_result = $ip_allowlist->refresh_ip_ranges();

		// Redirect back with message and preserve Security tab state.
		// Include nonce for message verification on redirect destination.
		$message_nonce = wp_create_nonce( 'carticy_admin_message' );

		if ( is_wp_error( $refresh_result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'     => 'carticy-ai-checkout-for-woocommerce',
						'message'  => 'ip_refresh_failed',
						'tab'      => 'security',
						'_wpnonce' => $message_nonce,
					),
					admin_url( 'admin.php' )
				)
			);
		} else {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'     => 'carticy-ai-checkout-for-woocommerce',
						'message'  => 'ip_refresh_success',
						'tab'      => 'security',
						'_wpnonce' => $message_nonce,
					),
					admin_url( 'admin.php' )
				)
			);
		}
		exit;
	}

	/**
	 * AJAX handler for regenerating API key
	 *
	 * @return void
	 */
	public function ajax_regenerate_api_key(): void {
		// Verify nonce.
		check_ajax_referer( 'carticy_settings_nonce', 'nonce' );

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) ),
				403
			);
		}

		// Get API key service from container.
		$api_key_service = \Carticy\AiCheckout\Init::get_instance()->get_service( 'api_key' );

		try {
			// Regenerate the API key.
			$new_api_key = $api_key_service->regenerate_api_key();

			wp_send_json_success(
				array(
					'api_key' => $new_api_key,
					'message' => __( 'API key regenerated successfully.', 'carticy-ai-checkout-for-woocommerce' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
				500
			);
		}
	}

	/**
	 * AJAX handler for regenerating webhook secret
	 *
	 * @return void
	 */
	public function ajax_regenerate_webhook_secret(): void {
		// Verify nonce.
		check_ajax_referer( 'carticy_settings_nonce', 'nonce' );

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) ),
				403
			);
		}

		// Get API key service from container.
		$api_key_service = \Carticy\AiCheckout\Init::get_instance()->get_service( 'api_key' );

		try {
			// Regenerate the webhook secret.
			$new_webhook_secret = $api_key_service->regenerate_webhook_secret();

			wp_send_json_success(
				array(
					'webhook_secret' => $new_webhook_secret,
					'message'        => __( 'Webhook secret regenerated successfully.', 'carticy-ai-checkout-for-woocommerce' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
				500
			);
		}
	}

	/**
	 * AJAX handler for testing webhook
	 *
	 * @return void
	 */
	public function ajax_test_webhook(): void {
		// Verify nonce.
		check_ajax_referer( 'carticy_settings_nonce', 'nonce' );

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) ),
				403
			);
		}

		// Get webhook service from container.
		$webhook_service = \Carticy\AiCheckout\Init::get_instance()->get_service( 'webhook' );

		// Create test webhook payload.
		$test_data = array(
			'session_id'    => 'test_session_' . time(),
			'permalink_url' => home_url( '/test-order' ),
			'status'        => 'test',
			'refunds'       => array(),
		);

		try {
			// Send test webhook.
			$result = $webhook_service->send( 'order_created', $test_data );

			if ( $result ) {
				wp_send_json_success(
					array( 'message' => __( 'Test webhook sent successfully! Check your webhook endpoint logs.', 'carticy-ai-checkout-for-woocommerce' ) )
				);
			} else {
				wp_send_json_error(
					array( 'message' => __( 'Failed to send test webhook. Verify your webhook URL and secret are configured.', 'carticy-ai-checkout-for-woocommerce' ) ),
					400
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
				500
			);
		}
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
