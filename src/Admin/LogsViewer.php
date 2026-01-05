<?php
/**
 * Logs Viewer Admin Page
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Admin;

use Carticy\AiCheckout\Services\LoggingService;
use Carticy\AiCheckout\Services\ErrorLogService;
use Carticy\AiCheckout\Services\WebhookLogger;
use Carticy\AiCheckout\Services\PerformanceMetrics;
use Carticy\AiCheckout\Services\SessionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LogsViewer
 *
 * Handles the logs viewer admin page
 */
final class LogsViewer {

	/**
	 * Logging service instance.
	 *
	 * @var LoggingService
	 */
	private LoggingService $logging_service;

	/**
	 * Error log service instance.
	 *
	 * @var ErrorLogService
	 */
	private ErrorLogService $error_log_service;

	/**
	 * Webhook logger instance.
	 *
	 * @var WebhookLogger
	 */
	private WebhookLogger $webhook_logger;

	/**
	 * Performance metrics instance.
	 *
	 * @var PerformanceMetrics
	 */
	private PerformanceMetrics $performance_metrics;

	/**
	 * Session service instance.
	 *
	 * @var SessionService
	 */
	private SessionService $session_service;

	/**
	 * Constructor.
	 *
	 * @param LoggingService     $logging_service     Logging service instance.
	 * @param ErrorLogService    $error_log_service   Error log service instance.
	 * @param WebhookLogger      $webhook_logger      Webhook logger instance.
	 * @param PerformanceMetrics $performance_metrics Performance metrics instance.
	 * @param SessionService     $session_service     Session service instance.
	 */
	public function __construct(
		LoggingService $logging_service,
		ErrorLogService $error_log_service,
		WebhookLogger $webhook_logger,
		PerformanceMetrics $performance_metrics,
		SessionService $session_service
	) {
		$this->logging_service     = $logging_service;
		$this->error_log_service   = $error_log_service;
		$this->webhook_logger      = $webhook_logger;
		$this->performance_metrics = $performance_metrics;
		$this->session_service     = $session_service;

		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Render the logs viewer page
	 */
	public function render(): void {
		// Tab selection with nonce verification.
		$active_tab = 'api'; // Default tab.
		if ( isset( $_GET['tab'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_logs_tab' ) ) {
				$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
			}
		}

		// Note: Modal assets (admin-layout CSS + admin-components JS) are enqueued
		// centrally in AdminHandler::enqueue_assets() for all admin pages.

		// Enqueue page-specific styles only
		wp_enqueue_style(
			'carticy-logs-viewer',
			CARTICY_AI_CHECKOUT_URL . 'assets/css/dist/logs-viewer.min.css',
			array(),
			CARTICY_AI_CHECKOUT_VERSION
		);

		// Get tab-specific data
		$data = $this->get_tab_data( $active_tab );

		include CARTICY_AI_CHECKOUT_DIR . 'templates/admin/logs-viewer.php';
	}

	/**
	 * Get data for specific tab.
	 *
	 * @param string $tab Tab identifier.
	 * @return array Tab data.
	 */
	private function get_tab_data( string $tab ): array {
		switch ( $tab ) {
			case 'api':
				return $this->get_api_logs_data();
			case 'errors':
				return $this->get_error_logs_data();
			case 'webhooks':
				return $this->get_webhook_logs_data();
			case 'sessions':
				return $this->get_session_status_data();
			default:
				return array();
		}
	}

	/**
	 * Get API logs data
	 *
	 * @return array API logs data
	 */
	private function get_api_logs_data(): array {
		$log_files = $this->logging_service->get_log_files( 'carticy-api' );
		$entries   = array();

		// Pagination support for large log volumes (prevents memory issues with thousands of entries).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination is a read-only display operation.
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 25;
		$per_page = max( 10, min( 100, $per_page ) ); // Clamp between 10-100

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination is a read-only display operation.
		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$current_page = max( 1, $current_page );

		if ( ! empty( $log_files ) ) {
			// Parse more entries than needed to enable pagination
			$max_entries = $per_page * 10; // Parse up to 10 pages worth
			$all_entries = $this->logging_service->parse_log_file( $log_files[0], $max_entries );

			// Paginate results
			$offset  = ( $current_page - 1 ) * $per_page;
			$entries = array_slice( $all_entries, $offset, $per_page );

			$total_entries = count( $all_entries );
		} else {
			$total_entries = 0;
		}

		return array(
			'entries'    => $entries,
			'file_count' => count( $log_files ),
			'pagination' => array(
				'current_page'  => $current_page,
				'per_page'      => $per_page,
				'total_entries' => $total_entries,
				'total_pages'   => $total_entries > 0 ? ceil( $total_entries / $per_page ) : 1,
			),
		);
	}

	/**
	 * Get error logs data
	 *
	 * @return array Error logs data
	 */
	private function get_error_logs_data(): array {
		// Category filter with nonce verification.
		$category_filter = null;
		if ( isset( $_GET['category'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_error_filter' ) ) {
				$category_filter = sanitize_key( wp_unslash( $_GET['category'] ) );
			}
		}

		return array(
			'entries'           => $this->error_log_service->get_recent_errors( 50, $category_filter ),
			'statistics'        => $this->error_log_service->get_error_statistics( 24 ),
			'selected_category' => $category_filter,
		);
	}

	/**
	 * Get webhook logs data
	 *
	 * @return array Webhook logs data
	 */
	private function get_webhook_logs_data(): array {
		// Webhook filters with nonce verification.
		$event_filter   = null;
		$success_filter = null;

		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_webhook_filter' ) ) {
			if ( isset( $_GET['event_type'] ) ) {
				$event_filter = sanitize_key( wp_unslash( $_GET['event_type'] ) );
			}
			if ( isset( $_GET['success'] ) ) {
				$success_filter = ( sanitize_key( wp_unslash( $_GET['success'] ) ) === '1' );
			}
		}

		return array(
			'entries'          => $this->webhook_logger->get_recent_webhooks( 50, $event_filter, $success_filter ),
			'statistics'       => $this->webhook_logger->get_statistics( 24 ),
			'retry_queue'      => $this->webhook_logger->get_retry_queue(),
			'selected_event'   => $event_filter,
			'selected_success' => $success_filter,
		);
	}

	/**
	 * Get session status data
	 *
	 * @return array Session status data
	 */
	private function get_session_status_data(): array {
		global $wpdb;

		// Get active session transients
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session_transients = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_carticy_ai_checkout_session_%'
             ORDER BY option_id DESC
             LIMIT 50",
			ARRAY_A
		);

		$recent_sessions = array();
		$active_count    = 0;
		$status_counts   = array(
			'active'    => 0,
			'completed' => 0,
			'cancelled' => 0,
			'failed'    => 0,
			'refunded'  => 0,
			'expired'   => 0,
			'unknown'   => 0,
		);
		$total_count     = 0;

		foreach ( $session_transients as $transient ) {
			// Extract session ID from transient name
			$session_id = str_replace( '_transient_carticy_ai_checkout_session_', '', $transient['option_name'] );

			// Get session data
			$session_data = maybe_unserialize( $transient['option_value'] );

			if ( ! is_array( $session_data ) ) {
				continue;
			}

			++$total_count;

			// Get expiration time
			$timeout_key = '_transient_timeout_carticy_ai_checkout_session_' . $session_id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$expires_at = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
					$timeout_key
				)
			);

			$created_at = $session_data['created_at'] ?? null;
			$updated_at = $session_data['updated_at'] ?? null;
			$status     = $session_data['status'] ?? 'unknown';

			// Check order status if session shows 'active' with an order_id,
			// as the order status may have changed after session was last updated (e.g., via webhook).
			$order_id = $session_data['order_id'] ?? null;

			if ( $order_id && 'active' === $status ) {
				// Session has order but shows active - check if order status changed.
				// This handles webhook updates that change order status without updating session.
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order_status = $order->get_status();

					// If order is no longer pending, derive session status from order.
					if ( in_array( $order_status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
						$status = 'completed';
					} elseif ( in_array( $order_status, array( 'failed', 'cancelled', 'refunded' ), true ) ) {
						$status = $order_status;
					}
					// If order is still pending, keep session status as 'active'.
				}
			}

			// For all other cases, trust the session status from database.
			// Cleanup happens in background via Init::cleanup_expired_sessions() cron.

			// Count sessions by status - only 'active' sessions are truly active
			if ( 'active' === $status ) {
				++$active_count;
			}

			// Track status breakdown for statistics
			if ( isset( $status_counts[ $status ] ) ) {
				++$status_counts[ $status ];
			} else {
				++$status_counts['unknown'];
			}

			$recent_sessions[] = array(
				'session_id'   => $session_id,
				'status'       => $status,
				'created_at'   => $created_at,
				'updated_at'   => $updated_at,
				'expires_at'   => $expires_at ? (int) $expires_at : null,
				'items_count'  => isset( $session_data['items'] ) ? count( $session_data['items'] ) : 0,
				'has_shipping' => ! empty( $session_data['shipping_address'] ),
			);
		}

		return array(
			'active_count'    => $active_count,
			'total_count'     => $total_count,
			'status_counts'   => $status_counts,
			'recent_sessions' => $recent_sessions,
			'performance'     => $this->performance_metrics->get_statistics( 24 ),
			'system_health'   => $this->performance_metrics->get_system_health(),
		);
	}

	/**
	 * Handle admin actions
	 */
	public function handle_actions(): void {
		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['page'] ) || 'carticy-ai-checkout-logs' !== $_GET['page'] ) {
			return;
		}

		check_admin_referer( 'carticy_logs_action' );

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		switch ( $action ) {
			case 'carticy_ai_checkout_clear_error_cache':
				$this->error_log_service->clear_statistics_cache();
				wp_safe_redirect(
					add_query_arg(
						array(
							'page' => 'carticy-ai-checkout-logs',
							'tab'  => 'errors',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;

			case 'carticy_ai_checkout_clear_webhook_cache':
				$this->webhook_logger->clear_statistics_cache();
				wp_safe_redirect(
					add_query_arg(
						array(
							'page' => 'carticy-ai-checkout-logs',
							'tab'  => 'webhooks',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;

			case 'carticy_ai_checkout_clear_performance_cache':
				$this->performance_metrics->clear_metrics_cache();
				wp_safe_redirect(
					add_query_arg(
						array(
							'page' => 'carticy-ai-checkout-logs',
							'tab'  => 'sessions',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;

			case 'carticy_ai_checkout_clear_retry_queue':
				$this->webhook_logger->clear_retry_queue();
				wp_safe_redirect(
					add_query_arg(
						array(
							'page' => 'carticy-ai-checkout-logs',
							'tab'  => 'webhooks',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
		}
	}

	/**
	 * Get available tabs
	 *
	 * @return array Tabs configuration
	 */
	public function get_tabs(): array {
		return array(
			'api'      => array(
				'label' => __( 'API Logs', 'carticy-ai-checkout-for-woocommerce' ),
				'icon'  => 'dashicons-rest-api',
			),
			'errors'   => array(
				'label' => __( 'Error Logs', 'carticy-ai-checkout-for-woocommerce' ),
				'icon'  => 'dashicons-warning',
			),
			'webhooks' => array(
				'label' => __( 'Webhook Activity', 'carticy-ai-checkout-for-woocommerce' ),
				'icon'  => 'dashicons-cloud-upload',
			),
			'sessions' => array(
				'label' => __( 'Session Status', 'carticy-ai-checkout-for-woocommerce' ),
				'icon'  => 'dashicons-admin-generic',
			),
		);
	}
}
