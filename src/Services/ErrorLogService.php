<?php
/**
 * Error Log Service
 *
 * Provides structured error logging with categorization
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ErrorLogService
 *
 * Handles error logging with categorization and context
 */
final class ErrorLogService {

	/**
	 * Error categories
	 */
	public const CATEGORY_PAYMENT    = 'payment';
	public const CATEGORY_API        = 'api';
	public const CATEGORY_WEBHOOK    = 'webhook';
	public const CATEGORY_VALIDATION = 'validation';
	public const CATEGORY_SYSTEM     = 'system';

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
	 * Log payment error.
	 *
	 * @param string          $message   Error message.
	 * @param array           $context   Additional context.
	 * @param \Throwable|null $exception Exception if available.
	 */
	public function log_payment_error(
		string $message,
		array $context = array(),
		?\Throwable $exception = null
	): void {
		$this->logger->log_error(
			$message,
			self::CATEGORY_PAYMENT,
			$context,
			$exception
		);
	}

	/**
	 * Log API error.
	 *
	 * @param string          $message   Error message.
	 * @param string          $endpoint  API endpoint.
	 * @param array           $context   Additional context.
	 * @param \Throwable|null $exception Exception if available.
	 */
	public function log_api_error(
		string $message,
		string $endpoint,
		array $context = array(),
		?\Throwable $exception = null
	): void {
		$context['endpoint'] = $endpoint;

		$this->logger->log_error(
			$message,
			self::CATEGORY_API,
			$context,
			$exception
		);
	}

	/**
	 * Log webhook error.
	 *
	 * @param string          $message    Error message.
	 * @param string          $event_type Webhook event type.
	 * @param array           $context    Additional context.
	 * @param \Throwable|null $exception  Exception if available.
	 */
	public function log_webhook_error(
		string $message,
		string $event_type,
		array $context = array(),
		?\Throwable $exception = null
	): void {
		$context['event_type'] = $event_type;

		$this->logger->log_error(
			$message,
			self::CATEGORY_WEBHOOK,
			$context,
			$exception
		);
	}

	/**
	 * Log validation error.
	 *
	 * @param string $message       Error message.
	 * @param array  $failed_fields Failed validation fields.
	 * @param array  $context       Additional context.
	 */
	public function log_validation_error(
		string $message,
		array $failed_fields = array(),
		array $context = array()
	): void {
		if ( ! empty( $failed_fields ) ) {
			$context['failed_fields'] = $failed_fields;
		}

		$this->logger->log_error(
			$message,
			self::CATEGORY_VALIDATION,
			$context
		);
	}

	/**
	 * Log system error.
	 *
	 * @param string          $message   Error message.
	 * @param array           $context   Additional context.
	 * @param \Throwable|null $exception Exception if available.
	 */
	public function log_system_error(
		string $message,
		array $context = array(),
		?\Throwable $exception = null
	): void {
		$this->logger->log_error(
			$message,
			self::CATEGORY_SYSTEM,
			$context,
			$exception
		);
	}

	/**
	 * Log SharedPaymentToken error.
	 *
	 * @param string $error_code    Stripe error code.
	 * @param string $error_message Error message.
	 * @param string $order_id      Order ID.
	 * @param array  $context       Additional context.
	 */
	public function log_shared_payment_token_error(
		string $error_code,
		string $error_message,
		string $order_id,
		array $context = array()
	): void {
		$context['error_code'] = $error_code;
		$context['order_id']   = $order_id;

		$this->logger->log_error(
			sprintf(
				'SharedPaymentToken processing failed: %s - %s',
				$error_code,
				$error_message
			),
			self::CATEGORY_PAYMENT,
			$context
		);
	}

	/**
	 * Log authentication failure.
	 *
	 * @param string $reason  Failure reason.
	 * @param array  $context Additional context.
	 */
	public function log_authentication_failure(
		string $reason,
		array $context = array()
	): void {
		$context['ip_address'] = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$context['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';

		$this->logger->log_error(
			'Authentication failed: ' . $reason,
			self::CATEGORY_API,
			$context
		);
	}

	/**
	 * Log session error.
	 *
	 * @param string $session_id Session ID.
	 * @param string $message    Error message.
	 * @param array  $context    Additional context.
	 */
	public function log_session_error(
		string $session_id,
		string $message,
		array $context = array()
	): void {
		$context['session_id'] = $session_id;

		$this->logger->log_error(
			$message,
			self::CATEGORY_SYSTEM,
			$context
		);
	}

	/**
	 * Log shipping calculation error.
	 *
	 * @param string $message Error message.
	 * @param array  $address Shipping address.
	 * @param array  $context Additional context.
	 */
	public function log_shipping_error(
		string $message,
		array $address,
		array $context = array()
	): void {
		$context['address'] = array(
			'country'  => $address['country'] ?? '',
			'state'    => $address['state'] ?? '',
			'postcode' => $address['postcode'] ?? '',
		);

		$this->logger->log_error(
			$message,
			self::CATEGORY_SYSTEM,
			$context
		);
	}

	/**
	 * Log product feed error.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context.
	 */
	public function log_product_feed_error(
		string $message,
		array $context = array()
	): void {
		$this->logger->log_error(
			$message,
			self::CATEGORY_SYSTEM,
			$context
		);
	}

	/**
	 * Get error statistics from transient cache.
	 *
	 * @param int $hours Number of hours to analyze.
	 * @return array Error statistics by category.
	 */
	public function get_error_statistics( int $hours = 24 ): array {
		$cache_key = 'carticy_ai_checkout_error_stats_' . $hours;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$stats = array(
			self::CATEGORY_PAYMENT    => 0,
			self::CATEGORY_API        => 0,
			self::CATEGORY_WEBHOOK    => 0,
			self::CATEGORY_VALIDATION => 0,
			self::CATEGORY_SYSTEM     => 0,
		);

		// Parse recent error logs
		$log_files = $this->logger->get_log_files( 'carticy-errors' );

		if ( empty( $log_files ) ) {
			return $stats;
		}

		$cutoff_time = time() - ( $hours * HOUR_IN_SECONDS );

		foreach ( $log_files as $file ) {
			if ( filemtime( $file ) < $cutoff_time ) {
				continue;
			}

			$entries = $this->logger->parse_log_file( $file, 1000 );

			foreach ( $entries as $entry ) {
				if ( isset( $entry['context']['context']['category'] ) ) {
					$category = $entry['context']['context']['category'];

					if ( isset( $stats[ $category ] ) ) {
						++$stats[ $category ];
					}
				}
			}
		}

		// Cache for 5 minutes
		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Get recent errors.
	 *
	 * @param int         $limit    Number of errors to retrieve.
	 * @param string|null $category Filter by category.
	 * @return array Recent error entries.
	 */
	public function get_recent_errors( int $limit = 50, ?string $category = null ): array {
		$log_files = $this->logger->get_log_files( 'carticy-errors' );

		if ( empty( $log_files ) ) {
			return array();
		}

		$errors = array();

		foreach ( $log_files as $file ) {
			$entries = $this->logger->parse_log_file( $file, $limit * 2 );

			foreach ( $entries as $entry ) {
				// Filter by category if specified
				if ( null !== $category ) {
					$entry_category = $entry['context']['context']['category'] ?? null;

					if ( $entry_category !== $category ) {
						continue;
					}
				}

				$errors[] = $entry;

				if ( count( $errors ) >= $limit ) {
					break 2;
				}
			}
		}

		return $errors;
	}

	/**
	 * Clear error statistics cache.
	 */
	public function clear_statistics_cache(): void {
		// Delete all error stats transients
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_carticy_ai_checkout_error_stats_%'
             OR option_name LIKE '_transient_timeout_carticy_ai_checkout_error_stats_%'"
		);
	}
}
