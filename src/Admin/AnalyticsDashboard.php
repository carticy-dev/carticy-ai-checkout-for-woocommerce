<?php
/**
 * Analytics Dashboard
 *
 * Admin page handler for analytics dashboard.
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Admin;

use Carticy\AiCheckout\Services\AnalyticsService;

/**
 * Handles analytics dashboard admin page
 */
final class AnalyticsDashboard {
	/**
	 * Analytics service
	 *
	 * @var AnalyticsService
	 */
	private AnalyticsService $analytics;

	/**
	 * Current date range filter
	 *
	 * @var int
	 */
	private int $days = 30;

	/**
	 * Constructor
	 *
	 * @param AnalyticsService $analytics Analytics service instance.
	 */
	public function __construct( AnalyticsService $analytics ) {
		$this->analytics = $analytics;
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue dashboard assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on analytics page.
		if ( ! str_contains( $hook, 'carticy-ai-checkout-analytics' ) ) {
			return;
		}

		wp_enqueue_style(
			'carticy-analytics-dashboard',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/dist/analytics-dashboard.min.css',
			array(),
			CARTICY_AI_CHECKOUT_VERSION
		);
	}

	/**
	 * Handle filter request
	 *
	 * @return void
	 */
	private function handle_filter_request(): void {
		// Days filter with nonce verification.
		if ( ! isset( $_GET['days'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_analytics_filter' ) ) {
			return;
		}

		$days_filter = absint( wp_unslash( $_GET['days'] ) );
		if ( in_array( $days_filter, array( 7, 30, 90 ), true ) ) {
			$this->days = $days_filter;
		}
	}

	/**
	 * Render analytics page
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle filter request.
		$this->handle_filter_request();

		// Get analytics data.
		$chatgpt_orders  = $this->analytics->get_chatgpt_order_count( $this->days );
		$chatgpt_revenue = $this->analytics->get_chatgpt_revenue( $this->days );
		$regular_orders  = $this->analytics->get_regular_order_count( $this->days );
		$regular_revenue = $this->analytics->get_regular_revenue( $this->days );
		$conversion_rate = $this->analytics->get_conversion_rate( $this->days );
		$chatgpt_aov     = $this->analytics->get_average_order_value( 'chatgpt', $this->days );
		$regular_aov     = $this->analytics->get_average_order_value( 'regular', $this->days );
		$recent_orders   = $this->analytics->get_recent_chatgpt_orders( 10 );
		$status_stats    = $this->analytics->get_order_stats_by_status( $this->days );

		// Prepare template data.
		$data = array(
			'days'            => $this->days,
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

		// Load template.
		$this->load_template( 'analytics-dashboard', $data );
	}

	/**
	 * Load template file
	 *
	 * @param string $template Template name without .php extension.
	 * @param array  $data Template data to extract.
	 * @return void
	 */
	private function load_template( string $template, array $data = array() ): void {
		$template_path = dirname( dirname( __DIR__ ) ) . "/templates/admin/{$template}.php";

		if ( ! file_exists( $template_path ) ) {
			wp_die(
				sprintf(
				/* translators: %s: template file path */
					esc_html__( 'Template file not found: %s', 'carticy-ai-checkout-for-woocommerce' ),
					esc_html( $template_path )
				)
			);
		}

		// Extract data for template.
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		// Include template.
		include $template_path;
	}
}
