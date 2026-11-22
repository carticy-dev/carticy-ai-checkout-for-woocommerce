<?php
/**
 * Logs & Monitoring screen REST controller.
 *
 * Wraps the logging/observability services so the React Logs screen can load
 * each sub-tab (API logs, error logs, webhook activity, session status) and
 * trigger the same cache-clear actions the server page exposed. Replaces the
 * server-rendered LogsViewer page; it reads its params from WP_REST_Request
 * rather than the global $_GET reads LogsViewer relied on.
 *
 * @package Carticy\AiCheckout\Rest
 */

declare(strict_types=1);

namespace Carticy\AiCheckout\Rest;

use Carticy\AiCheckout\Services\LoggingService;
use Carticy\AiCheckout\Services\ErrorLogService;
use Carticy\AiCheckout\Services\WebhookLogger;
use Carticy\AiCheckout\Services\PerformanceMetrics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backs the React Logs & Monitoring screen.
 */
final class LogsController extends AbstractAdminController {

	/**
	 * Logging service (log files + parsing).
	 *
	 * @var LoggingService
	 */
	private LoggingService $logging;

	/**
	 * Error log service (recent errors + statistics).
	 *
	 * @var ErrorLogService
	 */
	private ErrorLogService $error_log;

	/**
	 * Webhook logger (recent deliveries, statistics, retry queue).
	 *
	 * @var WebhookLogger
	 */
	private WebhookLogger $webhook_logger;

	/**
	 * Performance metrics (API timing + system health).
	 *
	 * @var PerformanceMetrics
	 */
	private PerformanceMetrics $performance_metrics;

	/**
	 * Error categories exposed by the error-log filter.
	 */
	private const ERROR_CATEGORIES = array( 'payment', 'api', 'webhook', 'validation', 'system' );

	/**
	 * Session statuses we count, in display order.
	 */
	private const SESSION_STATUSES = array( 'active', 'completed', 'cancelled', 'failed', 'refunded', 'expired', 'unknown' );

	/**
	 * Constructor.
	 *
	 * @param LoggingService     $logging             Logging service.
	 * @param ErrorLogService    $error_log           Error log service.
	 * @param WebhookLogger      $webhook_logger      Webhook logger.
	 * @param PerformanceMetrics $performance_metrics Performance metrics service.
	 */
	public function __construct(
		LoggingService $logging,
		ErrorLogService $error_log,
		WebhookLogger $webhook_logger,
		PerformanceMetrics $performance_metrics
	) {
		$this->logging             = $logging;
		$this->error_log           = $error_log;
		$this->webhook_logger      = $webhook_logger;
		$this->performance_metrics = $performance_metrics;
	}

	/**
	 * Route definitions for this controller.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function routes(): array {
		return array(
			array(
				'methods'  => 'GET',
				'route'    => '/logs',
				'callback' => array( $this, 'get_logs' ),
				'args'     => array(
					'tab'      => array(
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'api',
					),
					'paged'    => array(
						'sanitize_callback' => 'absint',
						'default'           => 1,
					),
					'per_page' => array(
						'sanitize_callback' => 'absint',
						'default'           => 25,
					),
					'category' => array(
						'sanitize_callback' => 'sanitize_key',
						'default'           => '',
					),
					'event'    => array(
						'sanitize_callback' => 'sanitize_key',
						'default'           => '',
					),
					'success'  => array(
						'sanitize_callback' => 'sanitize_key',
						'default'           => '',
					),
				),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/logs/clear-cache',
				'callback' => array( $this, 'clear_cache' ),
			),
		);
	}

	/**
	 * Return the payload for the requested sub-tab.
	 *
	 * @param \WP_REST_Request $request The REST request (reads the `tab` param).
	 */
	public function get_logs( \WP_REST_Request $request ): \WP_REST_Response {
		$tab = (string) $request->get_param( 'tab' );

		switch ( $tab ) {
			case 'errors':
				return $this->ok( $this->errors_payload( $request ) );
			case 'webhooks':
				return $this->ok( $this->webhooks_payload( $request ) );
			case 'sessions':
				return $this->ok( $this->sessions_payload() );
			case 'api':
			default:
				return $this->ok( $this->api_payload( $request ) );
		}
	}

	/**
	 * Build the API-logs payload (paginated entries from the newest log file).
	 *
	 * @param \WP_REST_Request $request The REST request (reads `paged`, `per_page`).
	 * @return array<string, mixed>
	 */
	private function api_payload( \WP_REST_Request $request ): array {
		$log_files = $this->logging->get_log_files( 'carticy-api' );

		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = max( 10, min( 100, $per_page ) );

		$current_page = max( 1, (int) $request->get_param( 'paged' ) );

		$entries       = array();
		$total_entries = 0;

		if ( ! empty( $log_files ) ) {
			// Parse up to ten pages' worth so pagination has a total to work with.
			$all_entries   = $this->logging->parse_log_file( $log_files[0], $per_page * 10 );
			$total_entries = count( $all_entries );
			$offset        = ( $current_page - 1 ) * $per_page;
			$entries       = array_slice( $all_entries, $offset, $per_page );
		}

		return array(
			'entries'    => array_map( array( $this, 'api_entry' ), $entries ),
			'file_count' => count( $log_files ),
			'pagination' => array(
				'current_page'  => $current_page,
				'per_page'      => $per_page,
				'total_entries' => $total_entries,
				'total_pages'   => $total_entries > 0 ? (int) ceil( $total_entries / $per_page ) : 1,
			),
		);
	}

	/**
	 * Normalize one parsed API-log entry into a JSON-safe row.
	 *
	 * The WooCommerce logger nests the real payload at context.context; we flatten
	 * to that inner array so the React "View context" modal shows the meaningful
	 * fields (endpoint, method, status, request/response in debug mode).
	 *
	 * @param array<string, mixed> $entry Parsed log entry.
	 * @return array<string, mixed>
	 */
	private function api_entry( array $entry ): array {
		$context = ( isset( $entry['context']['context'] ) && is_array( $entry['context']['context'] ) )
			? $entry['context']['context']
			: array();

		return array(
			'timestamp' => (string) ( $entry['timestamp'] ?? '' ),
			'level'     => (string) ( $entry['level'] ?? 'info' ),
			'message'   => (string) ( $entry['message'] ?? '' ),
			'context'   => (object) $context,
		);
	}

	/**
	 * Build the error-logs payload (recent entries + 24h statistics).
	 *
	 * @param \WP_REST_Request $request The REST request (reads `category`).
	 * @return array<string, mixed>
	 */
	private function errors_payload( \WP_REST_Request $request ): array {
		$category = (string) $request->get_param( 'category' );
		$category = in_array( $category, self::ERROR_CATEGORIES, true ) ? $category : null;

		$entries = $this->error_log->get_recent_errors( 50, $category );

		return array(
			'entries'           => array_map( array( $this, 'error_entry' ), $entries ),
			'statistics'        => $this->error_log->get_error_statistics( 24 ),
			'selected_category' => $category,
		);
	}

	/**
	 * Normalize one parsed error entry into a JSON-safe row.
	 *
	 * @param array<string, mixed> $entry Parsed log entry.
	 * @return array<string, mixed>
	 */
	private function error_entry( array $entry ): array {
		$context  = ( isset( $entry['context']['context'] ) && is_array( $entry['context']['context'] ) )
			? $entry['context']['context']
			: array();
		$category = isset( $context['category'] ) ? (string) $context['category'] : 'unknown';

		return array(
			'timestamp' => (string) ( $entry['timestamp'] ?? '' ),
			'level'     => (string) ( $entry['level'] ?? 'error' ),
			'message'   => (string) ( $entry['message'] ?? '' ),
			'category'  => $category,
			'context'   => (object) $context,
		);
	}

	/**
	 * Build the webhook-activity payload (recent deliveries + stats + retry queue).
	 *
	 * @param \WP_REST_Request $request The REST request (reads `event`, `success`).
	 * @return array<string, mixed>
	 */
	private function webhooks_payload( \WP_REST_Request $request ): array {
		$event = (string) $request->get_param( 'event' );
		$event = '' !== $event ? $event : null;

		$success_raw    = (string) $request->get_param( 'success' );
		$success_filter = ( '1' === $success_raw || '0' === $success_raw ) ? ( '1' === $success_raw ) : null;

		$entries = $this->webhook_logger->get_recent_webhooks( 50, $event, $success_filter );

		return array(
			'entries'     => array_map( array( $this, 'webhook_entry' ), $entries ),
			'statistics'  => $this->webhook_logger->get_statistics( 24 ),
			'retry_queue' => array_map( array( $this, 'retry_entry' ), $this->webhook_logger->get_retry_queue() ),
		);
	}

	/**
	 * Normalize one webhook delivery row (already flat from WebhookLogger).
	 *
	 * @param array<string, mixed> $entry Webhook entry from get_recent_webhooks().
	 * @return array<string, mixed>
	 */
	private function webhook_entry( array $entry ): array {
		return array(
			'timestamp'   => (string) ( $entry['timestamp'] ?? '' ),
			'event_type'  => (string) ( $entry['event_type'] ?? 'unknown' ),
			'url'         => (string) ( $entry['url'] ?? '' ),
			'status_code' => (int) ( $entry['status_code'] ?? 0 ),
			'attempt'     => (int) ( $entry['attempt'] ?? 1 ),
			'success'     => (bool) ( $entry['success'] ?? false ),
			'level'       => (string) ( $entry['level'] ?? 'info' ),
			'message'     => (string) ( $entry['message'] ?? '' ),
		);
	}

	/**
	 * Normalize one retry-queue row into a JSON-safe summary.
	 *
	 * @param array<string, mixed> $item Retry queue entry.
	 * @return array<string, mixed>
	 */
	private function retry_entry( array $item ): array {
		return array(
			'event_type'   => (string) ( $item['event_type'] ?? 'unknown' ),
			'url'          => (string) ( $item['url'] ?? '' ),
			'last_attempt' => (int) ( $item['last_attempt'] ?? 0 ),
			'queued_at'    => (int) ( $item['queued_at'] ?? 0 ),
		);
	}

	/**
	 * Build the session-status payload.
	 *
	 * Replicates LogsViewer::get_session_status_data(): reads the session
	 * transients directly, derives status from the linked order where needed, and
	 * appends performance + system-health metrics.
	 *
	 * @return array<string, mixed>
	 */
	private function sessions_payload(): array {
		global $wpdb;

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
		$total_count     = 0;
		$status_counts   = array_fill_keys( self::SESSION_STATUSES, 0 );

		foreach ( (array) $session_transients as $transient ) {
			$session_id   = str_replace( '_transient_carticy_ai_checkout_session_', '', $transient['option_name'] );
			$session_data = maybe_unserialize( $transient['option_value'] );

			if ( ! is_array( $session_data ) ) {
				continue;
			}

			++$total_count;

			$timeout_key = '_transient_timeout_carticy_ai_checkout_session_' . $session_id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$expires_at = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
					$timeout_key
				)
			);

			$status   = $session_data['status'] ?? 'unknown';
			$order_id = $session_data['order_id'] ?? null;

			// A webhook may have advanced the order past the session's last known
			// state; derive the true status from the order when it still says active.
			if ( $order_id && 'active' === $status ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order_status = $order->get_status();
					if ( in_array( $order_status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
						$status = 'completed';
					} elseif ( in_array( $order_status, array( 'failed', 'cancelled', 'refunded' ), true ) ) {
						$status = $order_status;
					}
				}
			}

			if ( 'active' === $status ) {
				++$active_count;
			}

			if ( isset( $status_counts[ $status ] ) ) {
				++$status_counts[ $status ];
			} else {
				++$status_counts['unknown'];
			}

			$recent_sessions[] = array(
				'session_id'   => (string) $session_id,
				'status'       => (string) $status,
				'created_at'   => isset( $session_data['created_at'] ) ? (int) $session_data['created_at'] : null,
				'updated_at'   => isset( $session_data['updated_at'] ) ? (int) $session_data['updated_at'] : null,
				'expires_at'   => $expires_at ? (int) $expires_at : null,
				'items_count'  => isset( $session_data['items'] ) ? count( (array) $session_data['items'] ) : 0,
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
	 * Clear a cached statistics set / queue for one observability surface.
	 *
	 * Replaces LogsViewer::handle_actions(); each target maps to the same service
	 * call the corresponding redirect-based action used.
	 *
	 * @param \WP_REST_Request $request The REST request (reads the `target` body param).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function clear_cache( \WP_REST_Request $request ) {
		$body   = $this->body( $request );
		$target = isset( $body['target'] ) ? sanitize_key( (string) $body['target'] ) : '';

		switch ( $target ) {
			case 'error':
				$this->error_log->clear_statistics_cache();
				$message = __( 'Error statistics cache cleared.', 'carticy-ai-checkout-for-woocommerce' );
				break;
			case 'webhook':
				$this->webhook_logger->clear_statistics_cache();
				$message = __( 'Webhook statistics cache cleared.', 'carticy-ai-checkout-for-woocommerce' );
				break;
			case 'performance':
				$this->performance_metrics->clear_metrics_cache();
				$message = __( 'Performance metrics cache cleared.', 'carticy-ai-checkout-for-woocommerce' );
				break;
			case 'retry-queue':
				$this->webhook_logger->clear_retry_queue();
				$message = __( 'Webhook retry queue cleared.', 'carticy-ai-checkout-for-woocommerce' );
				break;
			default:
				return $this->fail(
					'invalid_target',
					__( 'Unknown cache target.', 'carticy-ai-checkout-for-woocommerce' ),
					400
				);
		}

		return $this->ok( array( 'message' => $message ) );
	}
}
