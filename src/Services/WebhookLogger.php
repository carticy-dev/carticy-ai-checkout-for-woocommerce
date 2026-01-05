<?php
/**
 * Webhook Logger Service
 *
 * Tracks webhook delivery attempts and status
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebhookLogger
 *
 * Handles webhook activity logging and tracking
 */
final class WebhookLogger {

	/**
	 * Logger service instance.
	 *
	 * @var LoggingService
	 */
	private LoggingService $logger;

	/**
	 * Constructor.
	 *
	 * @param LoggingService $logger Logger service instance.
	 */
	public function __construct( LoggingService $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Log successful webhook delivery.
	 *
	 * @param string $event_type  Webhook event type.
	 * @param string $url         Webhook URL.
	 * @param array  $payload     Webhook payload.
	 * @param int    $status_code HTTP status code.
	 * @param int    $attempt     Attempt number.
	 * @param float  $duration    Request duration (unused, kept for API compatibility).
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Kept for API compatibility.
	 */
	public function log_success(
		string $event_type,
		string $url,
		array $payload,
		int $status_code,
		int $attempt = 1,
		float $duration = 0.0
	): void {
		$this->logger->log_webhook(
			$event_type,
			$url,
			$payload,
			$status_code,
			$attempt,
			true
		);

		// Track successful delivery in transient
		$this->increment_success_count( $event_type );
	}

	/**
	 * Log failed webhook delivery.
	 *
	 * @param string $event_type    Webhook event type.
	 * @param string $url           Webhook URL.
	 * @param array  $payload       Webhook payload.
	 * @param int    $status_code   HTTP status code.
	 * @param int    $attempt       Attempt number.
	 * @param string $error_message Error message (unused, kept for API compatibility).
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Kept for API compatibility.
	 */
	public function log_failure(
		string $event_type,
		string $url,
		array $payload,
		int $status_code,
		int $attempt,
		string $error_message
	): void {
		$this->logger->log_webhook(
			$event_type,
			$url,
			$payload,
			$status_code,
			$attempt,
			false
		);

		// Track failed delivery in transient
		$this->increment_failure_count( $event_type );

		// Store failed webhook for retry queue
		if ( $attempt < 3 ) {
			$this->queue_for_retry( $event_type, $url, $payload, $attempt );
		}
	}

	/**
	 * Get webhook delivery statistics.
	 *
	 * @param int $hours Number of hours to analyze.
	 * @return array Webhook statistics.
	 */
	public function get_statistics( int $hours = 24 ): array {
		$cache_key = 'carticy_ai_checkout_webhook_stats_' . $hours;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$stats = array(
			'total_sent'       => 0,
			'total_success'    => 0,
			'total_failed'     => 0,
			'by_event_type'    => array(),
			'average_duration' => 0,
			'retry_rate'       => 0,
		);

		$log_files = $this->logger->get_log_files( 'carticy-webhooks' );

		if ( empty( $log_files ) ) {
			return $stats;
		}

		$cutoff_time = time() - ( $hours * HOUR_IN_SECONDS );
		$durations   = array();
		$retry_count = 0;

		foreach ( $log_files as $file ) {
			if ( filemtime( $file ) < $cutoff_time ) {
				continue;
			}

			$entries = $this->logger->parse_log_file( $file, 1000 );

			foreach ( $entries as $entry ) {
				if ( ! isset( $entry['context']['context'] ) ) {
					continue;
				}

				$context    = $entry['context']['context'];
				$event_type = $context['event_type'] ?? 'unknown';
				$success    = $context['success'] ?? false;
				$attempt    = $context['attempt'] ?? 1;

				++$stats['total_sent'];

				if ( $success ) {
					++$stats['total_success'];
				} else {
					++$stats['total_failed'];
				}

				if ( $attempt > 1 ) {
					++$retry_count;
				}

				// Track by event type
				if ( ! isset( $stats['by_event_type'][ $event_type ] ) ) {
					$stats['by_event_type'][ $event_type ] = array(
						'sent'    => 0,
						'success' => 0,
						'failed'  => 0,
					);
				}

				++$stats['by_event_type'][ $event_type ]['sent'];

				if ( $success ) {
					++$stats['by_event_type'][ $event_type ]['success'];
				} else {
					++$stats['by_event_type'][ $event_type ]['failed'];
				}
			}
		}

		// Calculate retry rate
		if ( $stats['total_sent'] > 0 ) {
			$stats['retry_rate'] = round( ( $retry_count / $stats['total_sent'] ) * 100, 2 );
		}

		// Cache for 5 minutes
		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Get recent webhook deliveries.
	 *
	 * @param int         $limit        Number of webhooks to retrieve.
	 * @param string|null $event_type   Filter by event type.
	 * @param bool|null   $success_only Filter by success/failure.
	 * @return array Recent webhook entries.
	 */
	public function get_recent_webhooks(
		int $limit = 50,
		?string $event_type = null,
		?bool $success_only = null
	): array {
		$log_files = $this->logger->get_log_files( 'carticy-webhooks' );

		if ( empty( $log_files ) ) {
			return array();
		}

		$webhooks = array();

		foreach ( $log_files as $file ) {
			$entries = $this->logger->parse_log_file( $file, $limit * 2 );

			foreach ( $entries as $entry ) {
				if ( ! isset( $entry['context']['context'] ) ) {
					continue;
				}

				$context = $entry['context']['context'];

				// Filter by event type if specified
				if ( null !== $event_type && ( $context['event_type'] ?? '' ) !== $event_type ) {
					continue;
				}

				// Filter by success status if specified
				if ( null !== $success_only && ( $context['success'] ?? false ) !== $success_only ) {
					continue;
				}

				$webhooks[] = array(
					'timestamp'   => $entry['timestamp'],
					'event_type'  => $context['event_type'] ?? 'unknown',
					'url'         => $context['url'] ?? '',
					'status_code' => $context['status_code'] ?? 0,
					'attempt'     => $context['attempt'] ?? 1,
					'success'     => $context['success'] ?? false,
					'level'       => $entry['level'],
					'message'     => $entry['message'],
				);

				if ( count( $webhooks ) >= $limit ) {
					break 2;
				}
			}
		}

		return $webhooks;
	}

	/**
	 * Get failed webhooks that need retry.
	 *
	 * @return array Failed webhooks queue.
	 */
	public function get_retry_queue(): array {
		return get_option( 'carticy_ai_checkout_webhook_retry_queue', array() );
	}

	/**
	 * Queue webhook for retry.
	 *
	 * @param string $event_type   Event type.
	 * @param string $url          Webhook URL.
	 * @param array  $payload      Webhook payload.
	 * @param int    $last_attempt Last attempt number.
	 */
	private function queue_for_retry(
		string $event_type,
		string $url,
		array $payload,
		int $last_attempt
	): void {
		$queue = $this->get_retry_queue();

		$queue[] = array(
			'event_type'   => $event_type,
			'url'          => $url,
			'payload'      => $payload,
			'last_attempt' => $last_attempt,
			'queued_at'    => time(),
		);

		update_option( 'carticy_ai_checkout_webhook_retry_queue', $queue );
	}

	/**
	 * Remove webhook from retry queue.
	 *
	 * @param int $index Queue index.
	 */
	public function remove_from_retry_queue( int $index ): void {
		$queue = $this->get_retry_queue();

		if ( isset( $queue[ $index ] ) ) {
			unset( $queue[ $index ] );
			update_option( 'carticy_ai_checkout_webhook_retry_queue', array_values( $queue ) );
		}
	}

	/**
	 * Clear retry queue.
	 */
	public function clear_retry_queue(): void {
		delete_option( 'carticy_ai_checkout_webhook_retry_queue' );
	}

	/**
	 * Increment success count for event type.
	 *
	 * @param string $event_type Event type.
	 */
	private function increment_success_count( string $event_type ): void {
		$key   = 'carticy_ai_checkout_webhook_success_' . $event_type;
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
	}

	/**
	 * Increment failure count for event type.
	 *
	 * @param string $event_type Event type.
	 */
	private function increment_failure_count( string $event_type ): void {
		$key   = 'carticy_ai_checkout_webhook_failed_' . $event_type;
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
	}

	/**
	 * Clear webhook statistics cache.
	 */
	public function clear_statistics_cache(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_carticy_ai_checkout_webhook_%'
             OR option_name LIKE '_transient_timeout_carticy_ai_checkout_webhook_%'"
		);
	}
}
