<?php
/**
 * Error Log Service
 *
 * Reads the plugin error log by category
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
 * Counts and lists the entries LoggingService writes to the error log, by category, for the logs screen
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
	 * Get error statistics from transient cache.
	 *
	 * @param int $hours Number of hours to analyze.
	 * @return array<string, int> Error statistics by category.
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
	 * @return array<int, array<string, mixed>> Recent error entries.
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
