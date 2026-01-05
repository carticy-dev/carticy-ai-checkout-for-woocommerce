<?php
/**
 * Performance Metrics Service
 *
 * Tracks API performance and system health metrics
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PerformanceMetrics
 *
 * Handles performance tracking and metrics collection
 */
final class PerformanceMetrics {

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
	 * Track API request performance.
	 *
	 * @param string $endpoint    Endpoint path.
	 * @param float  $duration    Request duration in seconds.
	 * @param int    $memory_peak Peak memory usage in bytes.
	 * @param int    $db_queries  Number of database queries.
	 */
	public function track_api_request(
		string $endpoint,
		float $duration,
		int $memory_peak,
		int $db_queries
	): void {
		$this->logger->log_performance(
			sprintf( 'API Request: %s', $endpoint ),
			$duration,
			array(
				'endpoint'       => $endpoint,
				'memory_peak_mb' => round( $memory_peak / 1024 / 1024, 2 ),
				'db_queries'     => $db_queries,
			)
		);
	}

	/**
	 * Get performance statistics.
	 *
	 * @param int $hours Number of hours to analyze.
	 * @return array Performance statistics.
	 */
	public function get_statistics( int $hours = 24 ): array {
		$cache_key = 'carticy_ai_checkout_performance_stats_' . $hours;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$stats = array(
			'api_requests' => array(
				'total'              => 0,
				'average_duration'   => 0,
				'slowest_duration'   => 0,
				'fastest_duration'   => PHP_FLOAT_MAX,
				'average_memory'     => 0,
				'average_db_queries' => 0,
			),
			'by_endpoint'  => array(),
		);

		$log_files = $this->logger->get_log_files( 'carticy-performance' );

		if ( empty( $log_files ) ) {
			return $stats;
		}

		$cutoff_time     = time() - ( $hours * HOUR_IN_SECONDS );
		$durations       = array();
		$memory_values   = array();
		$db_query_counts = array();

		foreach ( $log_files as $file ) {
			if ( filemtime( $file ) < $cutoff_time ) {
				continue;
			}

			$entries = $this->logger->parse_log_file( $file, 1000 );

			foreach ( $entries as $entry ) {
				if ( ! isset( $entry['context']['context']['metrics'] ) ) {
					continue;
				}

				$context = $entry['context']['context'];
				$metrics = $context['metrics'];

				if ( ! isset( $metrics['endpoint'] ) ) {
					continue;
				}

				$endpoint   = $metrics['endpoint'];
				$duration   = $context['duration'] ?? 0;
				$memory     = $metrics['memory_peak_mb'] ?? 0;
				$db_queries = $metrics['db_queries'] ?? 0;

				++$stats['api_requests']['total'];
				$durations[]       = $duration;
				$memory_values[]   = $memory;
				$db_query_counts[] = $db_queries;

				// Track slowest and fastest
				if ( $duration > $stats['api_requests']['slowest_duration'] ) {
					$stats['api_requests']['slowest_duration'] = $duration;
				}

				if ( $duration < $stats['api_requests']['fastest_duration'] ) {
					$stats['api_requests']['fastest_duration'] = $duration;
				}

				// Track by endpoint
				if ( ! isset( $stats['by_endpoint'][ $endpoint ] ) ) {
					$stats['by_endpoint'][ $endpoint ] = array(
						'count'            => 0,
						'total_duration'   => 0,
						'average_duration' => 0,
						'slowest_duration' => 0,
					);
				}

				++$stats['by_endpoint'][ $endpoint ]['count'];
				$stats['by_endpoint'][ $endpoint ]['total_duration'] += $duration;

				if ( $duration > $stats['by_endpoint'][ $endpoint ]['slowest_duration'] ) {
					$stats['by_endpoint'][ $endpoint ]['slowest_duration'] = $duration;
				}
			}
		}

		// Calculate averages
		if ( ! empty( $durations ) ) {
			$stats['api_requests']['average_duration'] = round( array_sum( $durations ) / count( $durations ), 3 );
		}

		if ( ! empty( $memory_values ) ) {
			$stats['api_requests']['average_memory'] = round( array_sum( $memory_values ) / count( $memory_values ), 2 );
		}

		if ( ! empty( $db_query_counts ) ) {
			$stats['api_requests']['average_db_queries'] = round( array_sum( $db_query_counts ) / count( $db_query_counts ), 1 );
		}

		// Fix fastest duration if no requests
		if ( PHP_FLOAT_MAX === $stats['api_requests']['fastest_duration'] ) {
			$stats['api_requests']['fastest_duration'] = 0;
		}

		// Calculate average per endpoint
		foreach ( $stats['by_endpoint'] as $endpoint => &$data ) {
			if ( $data['count'] > 0 ) {
				$data['average_duration'] = round( $data['total_duration'] / $data['count'], 3 );
			}
		}

		// Sort endpoints by slowest first
		uasort(
			$stats['by_endpoint'],
			function ( $a, $b ) {
				return $b['average_duration'] <=> $a['average_duration'];
			}
		);

		// Cache for 5 minutes
		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Get system health metrics.
	 *
	 * @return array System health data.
	 */
	public function get_system_health(): array {
		return array(
			'php_version'         => PHP_VERSION,
			'memory_limit'        => ini_get( 'memory_limit' ),
			'max_execution_time'  => ini_get( 'max_execution_time' ),
			'wordpress_version'   => get_bloginfo( 'version' ),
			'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A',
			'active_sessions'     => $this->count_active_sessions(),
			'cache_status'        => $this->get_cache_status(),
		);
	}

	/**
	 * Count active checkout sessions.
	 *
	 * Only counts sessions with status='active', excluding completed, cancelled, and failed sessions.
	 * This prevents completed/failed sessions from inflating the active session count.
	 *
	 * @return int Number of active sessions.
	 */
	private function count_active_sessions(): int {
		global $wpdb;

		// Get all session transient option names and values.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
                 WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_carticy_ai_checkout_session_' ) . '%'
			)
		);

		$active_count = 0;

		foreach ( $sessions as $session ) {
			// Unserialize session data to check status.
			$session_data = maybe_unserialize( $session->option_value );

			// Only count if session data is valid and status is 'active' (or status is not set, defaulting to active).
			if ( is_array( $session_data ) ) {
				$status = $session_data['status'] ?? 'active';

				if ( 'active' === $status ) {
					++$active_count;
				}
			}
		}

		return $active_count;
	}

	/**
	 * Get cache status.
	 *
	 * @return array Cache status information.
	 */
	private function get_cache_status(): array {
		$status = array(
			'object_cache' => wp_using_ext_object_cache(),
			'opcode_cache' => function_exists( 'opcache_get_status' ) && false !== opcache_get_status(),
		);

		return $status;
	}

	/**
	 * Clear performance metrics cache.
	 */
	public function clear_metrics_cache(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_carticy_ai_checkout_performance_%'
             OR option_name LIKE '_transient_timeout_carticy_ai_checkout_performance_%'"
		);
	}
}
