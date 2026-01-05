<?php
/**
 * Rate Limiting Service
 *
 * Implements rate limiting for API endpoints to prevent abuse
 * and ensure service availability.
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

use WP_Error;

/**
 * Handles rate limiting for REST API endpoints
 */
final class RateLimitService {
	/**
	 * Transient key prefix
	 */
	private const TRANSIENT_PREFIX = 'carticy_rate_limit_';

	/**
	 * Time window for rate limiting (60 seconds)
	 */
	private const TIME_WINDOW = 60;

	/**
	 * Default rate limit per endpoint
	 *
	 * @var array<string, int>
	 */
	private array $rate_limits = array(
		'create_session'   => 100,  // Create checkout session.
		'get_session'      => 200,  // Get checkout session.
		'update_session'   => 100,  // Update checkout session.
		'complete_session' => 50,   // Complete checkout session.
		'cancel_session'   => 50,   // Cancel checkout session.
		'product_feed'     => 100,  // Product feed.
	);

	/**
	 * Check rate limit for endpoint
	 *
	 * @param string $endpoint Endpoint identifier.
	 * @param string $client_id Client identifier (user ID or IP hash).
	 * @return bool|WP_Error True if within limit, WP_Error if exceeded.
	 */
	public function check_rate_limit( string $endpoint, string $client_id ): bool|WP_Error {
		$limit = $this->get_endpoint_limit( $endpoint );

		if ( 0 === $limit ) {
			// No rate limit for this endpoint.
			return true;
		}

		$transient_key = $this->get_transient_key( $endpoint, $client_id );
		$current_count = $this->get_current_count( $transient_key );

		if ( $current_count >= $limit ) {
			// Rate limit exceeded.
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					'Rate limit exceeded. Maximum %d requests per %d seconds.',
					$limit,
					self::TIME_WINDOW
				),
				array(
					'status'    => 429,
					'limit'     => $limit,
					'remaining' => 0,
					'reset'     => $this->get_reset_time( $transient_key ),
				)
			);
		}

		// Increment counter.
		$this->increment_count( $transient_key );

		return true;
	}

	/**
	 * Get rate limit headers for response
	 *
	 * @param string $endpoint Endpoint identifier.
	 * @param string $client_id Client identifier.
	 * @return array<string, string> Rate limit headers.
	 */
	public function get_rate_limit_headers( string $endpoint, string $client_id ): array {
		$limit         = $this->get_endpoint_limit( $endpoint );
		$transient_key = $this->get_transient_key( $endpoint, $client_id );
		$current_count = $this->get_current_count( $transient_key );
		$remaining     = max( 0, $limit - $current_count );
		$reset         = $this->get_reset_time( $transient_key );

		return array(
			'X-RateLimit-Limit'     => (string) $limit,
			'X-RateLimit-Remaining' => (string) $remaining,
			'X-RateLimit-Reset'     => (string) $reset,
		);
	}

	/**
	 * Get client identifier
	 *
	 * @return string Client identifier (user_id or IP hash).
	 */
	public function get_client_id(): string {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			return 'user_' . $user_id;
		}

		// Use IP address hash for anonymous requests.
		$ip_address = $this->get_client_ip();
		return 'ip_' . md5( $ip_address );
	}

	/**
	 * Get rate limit for specific endpoint
	 *
	 * @param string $endpoint Endpoint identifier.
	 * @return int Rate limit count.
	 */
	private function get_endpoint_limit( string $endpoint ): int {
		return $this->rate_limits[ $endpoint ] ?? 100; // Default to 100 if not configured.
	}

	/**
	 * Get transient key for rate limiting
	 *
	 * @param string $endpoint Endpoint identifier.
	 * @param string $client_id Client identifier.
	 * @return string Transient key.
	 */
	private function get_transient_key( string $endpoint, string $client_id ): string {
		return self::TRANSIENT_PREFIX . md5( $endpoint . '_' . $client_id );
	}

	/**
	 * Get current request count
	 *
	 * @param string $transient_key Transient key.
	 * @return int Current count.
	 */
	private function get_current_count( string $transient_key ): int {
		$data = get_transient( $transient_key );

		if ( false === $data ) {
			return 0;
		}

		return (int) ( $data['count'] ?? 0 );
	}

	/**
	 * Increment request count
	 *
	 * @param string $transient_key Transient key.
	 * @return void
	 */
	private function increment_count( string $transient_key ): void {
		$data = get_transient( $transient_key );

		if ( false === $data ) {
			// First request in window.
			$data = array(
				'count'      => 1,
				'started_at' => time(),
			);
		} else {
			// Increment count.
			$data['count'] = ( $data['count'] ?? 0 ) + 1;
		}

		// Store for TIME_WINDOW seconds.
		set_transient( $transient_key, $data, self::TIME_WINDOW );
	}

	/**
	 * Get reset time (Unix timestamp)
	 *
	 * @param string $transient_key Transient key.
	 * @return int Reset timestamp.
	 */
	private function get_reset_time( string $transient_key ): int {
		$data = get_transient( $transient_key );

		if ( false === $data || ! isset( $data['started_at'] ) ) {
			return time() + self::TIME_WINDOW;
		}

		return (int) $data['started_at'] + self::TIME_WINDOW;
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// Handle comma-separated list (X-Forwarded-For).
				if ( str_contains( $ip, ',' ) ) {
					$ips = array_map( 'trim', explode( ',', $ip ) );
					$ip  = $ips[0];
				}

				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Clean up expired rate limit records
	 *
	 * WordPress automatically handles transient expiration,
	 * but this method can be used for manual cleanup if needed.
	 *
	 * @return int Number of records cleaned.
	 */
	public function cleanup_expired(): int {
		global $wpdb;

		// Delete expired transients with our prefix.
		$prefix = '_transient_' . self::TRANSIENT_PREFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup operation, caching not applicable for DELETE.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_value < %d",
				$wpdb->esc_like( $prefix ) . '%',
				time()
			)
		);

		return (int) $deleted;
	}
}
