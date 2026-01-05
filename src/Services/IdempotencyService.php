<?php
/**
 * Idempotency Service
 *
 * Handles Idempotency-Key header validation and response caching
 * to prevent duplicate processing of requests.
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

use WP_REST_Request;
use WP_Error;

/**
 * Manages idempotency for API requests
 */
final class IdempotencyService {
	/**
	 * Cache key prefix
	 */
	private const CACHE_PREFIX = 'carticy_idempotency_';

	/**
	 * Cache expiration time (24 hours)
	 */
	private const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Check if request has already been processed
	 *
	 * @param WP_REST_Request $request REST API request.
	 * @param string          $endpoint Endpoint identifier.
	 * @return array|null Cached response if exists, null otherwise.
	 */
	public function check_idempotency( WP_REST_Request $request, string $endpoint ): ?array {
		$idempotency_key = $request->get_header( 'idempotency-key' );

		// Idempotency not requested.
		if ( empty( $idempotency_key ) ) {
			return null;
		}

		// Generate cache key.
		$cache_key = $this->generate_cache_key( $endpoint, $idempotency_key );
		$cached    = get_transient( $cache_key );

		if ( false === $cached ) {
			// No cached response, proceed with request.
			return null;
		}

		// Validate request parameters match cached request.
		$current_params = $this->get_request_params( $request );
		$cached_params  = $cached['params'] ?? array();

		if ( $current_params !== $cached_params ) {
			// Same key, different parameters - return 409 conflict.
			return array(
				'error'  => 'idempotency_conflict',
				'status' => 409,
			);
		}

		// Same key, same parameters - return cached response.
		return array(
			'cached_response' => $cached['response'],
			'status'          => 200,
		);
	}

	/**
	 * Store response for future idempotency checks
	 *
	 * @param WP_REST_Request $request REST API request.
	 * @param string          $endpoint Endpoint identifier.
	 * @param mixed           $response Response data to cache.
	 * @return void
	 */
	public function store_idempotent_response( WP_REST_Request $request, string $endpoint, $response ): void {
		$idempotency_key = $request->get_header( 'idempotency-key' );

		// Only store if idempotency key provided.
		if ( empty( $idempotency_key ) ) {
			return;
		}

		// Generate cache key.
		$cache_key = $this->generate_cache_key( $endpoint, $idempotency_key );

		// Prepare cache data.
		$cache_data = array(
			'params'    => $this->get_request_params( $request ),
			'response'  => $response,
			'timestamp' => time(),
		);

		// Store in transient for 24 hours.
		set_transient( $cache_key, $cache_data, self::CACHE_EXPIRATION );
	}

	/**
	 * Generate cache key from endpoint and idempotency key
	 *
	 * @param string $endpoint Endpoint identifier.
	 * @param string $idempotency_key Idempotency key from header.
	 * @return string Cache key.
	 */
	private function generate_cache_key( string $endpoint, string $idempotency_key ): string {
		return self::CACHE_PREFIX . md5( $endpoint . $idempotency_key );
	}

	/**
	 * Get normalized request parameters for comparison
	 *
	 * @param WP_REST_Request $request REST API request.
	 * @return array Normalized request parameters.
	 */
	private function get_request_params( WP_REST_Request $request ): array {
		// Get JSON body parameters.
		$params = $request->get_json_params();

		if ( empty( $params ) ) {
			// Fallback to body parameters.
			$params = $request->get_body_params();
		}

		if ( empty( $params ) ) {
			// Fallback to URL parameters.
			$params = $request->get_params();
		}

		// Remove WordPress internal parameters.
		unset( $params['rest_route'] );
		unset( $params['_wpnonce'] );
		unset( $params['_locale'] );

		// Sort for consistent comparison.
		ksort( $params );

		return $params;
	}

	/**
	 * Clean up expired idempotency records
	 *
	 * WordPress automatically handles transient expiration,
	 * but this method can be used for manual cleanup if needed.
	 *
	 * @return int Number of records cleaned.
	 */
	public function cleanup_expired(): int {
		global $wpdb;

		// Delete expired transients with our prefix.
		$prefix = '_transient_' . self::CACHE_PREFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
