<?php
/**
 * Authentication Service
 *
 * Handles API authentication with comprehensive security checks including
 * Bearer token validation, IP allowlisting, rate limiting, and idempotency.
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

use WP_REST_Request;
use WP_Error;

/**
 * Handles API authentication and security
 */
final class AuthenticationService {
	/**
	 * IP allowlist service
	 *
	 * @var IpAllowlistService|null
	 */
	private ?IpAllowlistService $ip_allowlist = null;

	/**
	 * Rate limit service
	 *
	 * @var RateLimitService|null
	 */
	private ?RateLimitService $rate_limit = null;

	/**
	 * Set IP allowlist service
	 *
	 * @param IpAllowlistService $service IP allowlist service instance.
	 * @return void
	 */
	public function set_ip_allowlist_service( IpAllowlistService $service ): void {
		$this->ip_allowlist = $service;
	}

	/**
	 * Set rate limit service
	 *
	 * @param RateLimitService $service Rate limit service instance.
	 * @return void
	 */
	public function set_rate_limit_service( RateLimitService $service ): void {
		$this->rate_limit = $service;
	}

	/**
	 * Validate request with comprehensive security checks
	 *
	 * Performs layered security validation:
	 * 1. SSL/HTTPS enforcement (production only)
	 * 2. IP allowlisting (if enabled)
	 * 3. Bearer token authentication
	 * 4. Rate limiting
	 *
	 * @param WP_REST_Request $request REST API request.
	 * @param string          $endpoint Endpoint identifier for rate limiting.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_request_security( WP_REST_Request $request, string $endpoint ): bool|WP_Error {
		// 1. Check SSL/HTTPS requirement (production only, skip for localhost).
		if ( ! $this->is_test_mode() && ! is_ssl() && ! $this->is_localhost() ) {
			return new WP_Error(
				'ssl_required',
				'HTTPS is required for API requests. All traffic must use TLS 1.2+ on port 443.',
				array( 'status' => 403 )
			);
		}

		// 2. Validate IP address against allowlist.
		if ( $this->ip_allowlist ) {
			$client_ip = $this->get_client_ip();

			if ( ! $this->ip_allowlist->is_ip_allowed( $client_ip ) ) {
				return new WP_Error(
					'ip_not_allowed',
					'Request from unauthorized IP address',
					array(
						'status' => 403,
						'ip'     => $client_ip,
					)
				);
			}
		}

		// 3. Validate Bearer token.
		$token_validation = $this->validate_bearer_token( $request );
		if ( is_wp_error( $token_validation ) ) {
			return $token_validation;
		}

		// 4. Check rate limiting.
		if ( $this->rate_limit ) {
			$client_id         = $this->rate_limit->get_client_id();
			$rate_limit_result = $this->rate_limit->check_rate_limit( $endpoint, $client_id );

			if ( is_wp_error( $rate_limit_result ) ) {
				return $rate_limit_result;
			}
		}

		return true;
	}

	/**
	 * Validate Bearer token from request
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_bearer_token( WP_REST_Request $request ): bool|WP_Error {
		// Get Authorization header.
		$auth_header = $request->get_header( 'authorization' );

		if ( empty( $auth_header ) ) {
			return new WP_Error(
				'missing_authorization',
				'Missing Authorization header',
				array( 'status' => 401 )
			);
		}

		// Extract Bearer token.
		if ( ! preg_match( '/Bearer\s+(.+)/i', $auth_header, $matches ) ) {
			return new WP_Error(
				'invalid_authorization_format',
				'Invalid Authorization header format. Expected: Bearer {token}',
				array( 'status' => 401 )
			);
		}

		$token = $matches[1];

		// Get stored API key.
		$api_key = get_option( 'carticy_ai_checkout_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'api_key_not_configured',
				'API key not configured',
				array( 'status' => 500 )
			);
		}

		// Validate token using timing-safe comparison.
		if ( ! hash_equals( $api_key, $token ) ) {
			return new WP_Error(
				'invalid_token',
				'Invalid Bearer token',
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for protected REST endpoints
	 *
	 * Legacy method for backward compatibility.
	 * New endpoints should use validate_request_security() directly.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error if not.
	 */
	public function check_permission( WP_REST_Request $request ): bool|WP_Error {
		// Check SSL requirement in production (skip for localhost).
		if ( ! $this->is_test_mode() && ! is_ssl() && ! $this->is_localhost() ) {
			return new WP_Error(
				'ssl_required',
				'HTTPS is required for API requests',
				array( 'status' => 403 )
			);
		}

		// Validate Bearer token.
		return $this->validate_bearer_token( $request );
	}

	/**
	 * Generate a new API key
	 *
	 * @return string Generated API key.
	 */
	public function generate_api_key(): string {
		return wp_generate_password( 32, false );
	}

	/**
	 * Regenerate API key
	 *
	 * @return string New API key.
	 */
	public function regenerate_api_key(): string {
		$new_key = $this->generate_api_key();
		update_option( 'carticy_ai_checkout_api_key', $new_key );

		return $new_key;
	}

	/**
	 * Check if test mode is enabled
	 *
	 * @return bool True if test mode is enabled.
	 */
	private function is_test_mode(): bool {
		return 'yes' === get_option( 'carticy_ai_checkout_test_mode', 'yes' );
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address.
	 */
	public function get_client_ip(): string {
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
	 * Get rate limit headers for response
	 *
	 * @param string $endpoint Endpoint identifier.
	 * @return array<string, string> Rate limit headers.
	 */
	public function get_rate_limit_headers( string $endpoint ): array {
		if ( ! $this->rate_limit ) {
			return array();
		}

		$client_id = $this->rate_limit->get_client_id();
		return $this->rate_limit->get_rate_limit_headers( $endpoint, $client_id );
	}

	/**
	 * Check if running on localhost/development environment
	 *
	 * @return bool True if localhost environment.
	 */
	private function is_localhost(): bool {
		$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$server_name = isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : '';
		$host        = $http_host ? $http_host : $server_name;

		$localhost_patterns = array(
			'localhost',
			'127.0.0.1',
			'::1',
			'.local',
			'.test',
			'.dev',
		);

		foreach ( $localhost_patterns as $pattern ) {
			if ( str_contains( strtolower( $host ), $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}
