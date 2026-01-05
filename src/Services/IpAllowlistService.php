<?php
/**
 * IP Allowlist Service
 *
 * Manages OpenAI IP address allowlisting with automatic updates
 * from chatgpt-connectors.json.
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

use WP_Error;

/**
 * Handles IP allowlisting for API security
 */
final class IpAllowlistService {
	/**
	 * Logging service
	 *
	 * @var LoggingService
	 */
	private LoggingService $logging_service;

	/**
	 * Constructor
	 *
	 * @param LoggingService $logging_service Logging service instance.
	 */
	public function __construct( LoggingService $logging_service ) {
		$this->logging_service = $logging_service;
	}

	/**
	 * OpenAI IP ranges URL
	 *
	 * @link https://platform.openai.com/docs/bots
	 */
	private const OPENAI_IP_RANGES_URL = 'https://openai.com/chatgpt-connectors.json';

	/**
	 * Option key for storing IP ranges
	 */
	private const OPTION_IP_RANGES = 'carticy_ai_checkout_openai_ip_ranges';

	/**
	 * Option key for backup IP ranges
	 */
	private const OPTION_IP_RANGES_BACKUP = 'carticy_ai_checkout_openai_ip_ranges_backup';

	/**
	 * Option key for last updated timestamp
	 */
	private const OPTION_LAST_UPDATED = 'carticy_ai_checkout_openai_ip_ranges_last_updated';

	/**
	 * Transient key for IP ranges
	 */
	private const TRANSIENT_IP_RANGES = 'carticy_ai_checkout_openai_ip_ranges';

	/**
	 * Transient expiration (1 hour)
	 */
	private const TRANSIENT_EXPIRATION = HOUR_IN_SECONDS;

	/**
	 * Hardcoded fallback IP ranges from OpenAI (ChatGPT-User)
	 *
	 * @link https://platform.openai.com/docs/bots
	 */
	private const FALLBACK_IP_RANGES = array(
		'23.98.179.16/28',
		'172.183.222.128/28',
		'52.190.190.16/28',
		'51.8.155.64/28',
		'51.8.155.48/28',
		'135.237.131.208/28',
		'51.8.155.112/28',
		'52.159.249.96/28',
		'172.178.141.112/28',
		'172.178.140.144/28',
		'172.178.141.128/28',
		'4.196.118.112/28',
		'20.215.188.192/28',
		'4.197.22.112/28',
		'172.213.21.16/28',
	);

	/**
	 * Fetch OpenAI IP ranges from chatgpt-connectors.json
	 *
	 * @return array Array of CIDR blocks.
	 */
	public function fetch_openai_ip_ranges(): array {
		// Check transient cache first.
		$cached_ranges = get_transient( self::TRANSIENT_IP_RANGES );
		if ( false !== $cached_ranges && is_array( $cached_ranges ) ) {
			return $cached_ranges;
		}

		// Fetch from OpenAI.
		$response = wp_remote_get(
			self::OPENAI_IP_RANGES_URL,
			array(
				'timeout'   => 10,
				'sslverify' => true,
			)
		);

		// Handle fetch errors.
		if ( is_wp_error( $response ) ) {
			// Return backup if available, otherwise hardcoded fallback.
			return $this->get_fallback_ranges( $response->get_error_message() );
		}

		// Parse response.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || ! isset( $data['prefixes'] ) || ! is_array( $data['prefixes'] ) ) {
			// Return backup if available, otherwise hardcoded fallback.
			return $this->get_fallback_ranges( 'Invalid JSON format from OpenAI' );
		}

		// Extract ipv4Prefix values from prefixes array.
		$ip_ranges = array_map(
			function ( $prefix ) {
				return $prefix['ipv4Prefix'] ?? '';
			},
			$data['prefixes']
		);

		// Filter out any empty values.
		$ip_ranges = array_filter( $ip_ranges );

		// Validate we got actual IP ranges.
		if ( empty( $ip_ranges ) ) {
			return $this->get_fallback_ranges( 'Empty IP ranges from OpenAI' );
		}

		// Cache in transient (1 hour).
		set_transient( self::TRANSIENT_IP_RANGES, $ip_ranges, self::TRANSIENT_EXPIRATION );

		// Store in options for backup.
		update_option( self::OPTION_IP_RANGES_BACKUP, $ip_ranges );

		// Update last updated timestamp.
		update_option( self::OPTION_LAST_UPDATED, time() );

		return $ip_ranges;
	}

	/**
	 * Get fallback IP ranges
	 *
	 * @param string $error_message Error message to log.
	 * @return array Fallback IP ranges.
	 */
	private function get_fallback_ranges( string $error_message ): array {
		// Log error (only in debug mode to avoid log spam).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logging_service->debug( $error_message );
		}

		// Try backup from database first.
		$backup_ranges = get_option( self::OPTION_IP_RANGES_BACKUP, array() );
		if ( ! empty( $backup_ranges ) && is_array( $backup_ranges ) ) {
			return $backup_ranges;
		}

		// Use hardcoded fallback as last resort.
		return self::FALLBACK_IP_RANGES;
	}

	/**
	 * Check if IP address is allowed
	 *
	 * @param string $ip_address IP address to validate.
	 * @return bool True if allowed, false otherwise.
	 */
	public function is_ip_allowed( string $ip_address ): bool {
		// Skip validation in test mode.
		if ( $this->is_test_mode() ) {
			return true;
		}

		// Skip if IP allowlisting is disabled.
		if ( ! $this->is_enabled() ) {
			return true;
		}

		// Get IP ranges (includes fallbacks).
		$ip_ranges = $this->fetch_openai_ip_ranges();

		// Should never be empty due to hardcoded fallbacks, but safety check.
		if ( empty( $ip_ranges ) ) {
			// Fail-open for availability.
			return true;
		}

		// Check if IP is in any of the CIDR blocks.
		foreach ( $ip_ranges as $cidr ) {
			if ( $this->ip_in_range( $ip_address, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Manually refresh IP ranges
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function refresh_ip_ranges(): bool|WP_Error {
		// Get timestamp before refresh to detect if fetch was successful.
		$timestamp_before = $this->get_last_updated();

		// Delete transient to force fresh fetch.
		delete_transient( self::TRANSIENT_IP_RANGES );

		// Fetch new ranges.
		$ranges = $this->fetch_openai_ip_ranges();

		// Check if timestamp was updated (indicates successful fetch).
		$timestamp_after = $this->get_last_updated();

		// If timestamp didn't change, fetch used fallback data (not a successful refresh).
		if ( $timestamp_after === $timestamp_before ) {
			return new WP_Error(
				'fetch_failed',
				'Failed to fetch fresh IP ranges from OpenAI. Using cached data.'
			);
		}

		return true;
	}

	/**
	 * Get current IP ranges
	 *
	 * @return array Current IP ranges.
	 */
	public function get_current_ranges(): array {
		return $this->fetch_openai_ip_ranges();
	}

	/**
	 * Get last updated timestamp
	 *
	 * @return int Timestamp or 0 if never updated.
	 */
	public function get_last_updated(): int {
		return (int) get_option( self::OPTION_LAST_UPDATED, 0 );
	}

	/**
	 * Check if IP is in CIDR range
	 *
	 * @param string $ip IP address to check.
	 * @param string $cidr CIDR notation (e.g., 192.168.1.0/24) or single IP address.
	 * @return bool True if IP is in range.
	 */
	private function ip_in_range( string $ip, string $cidr ): bool {
		// Handle single IP address without CIDR notation (treat as /32).
		if ( ! str_contains( $cidr, '/' ) ) {
			return $ip === $cidr;
		}

		list( $subnet, $mask ) = explode( '/', $cidr );

		// Convert to long integers.
		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );

		if ( false === $ip_long || false === $subnet_long ) {
			return false;
		}

		// Calculate network mask.
		$mask_long = -1 << ( 32 - (int) $mask );

		// Ensure subnet is properly masked.
		$subnet_long &= $mask_long;

		// Check if IP is in range.
		return ( $ip_long & $mask_long ) === $subnet_long;
	}

	/**
	 * Check if test mode is enabled
	 *
	 * @return bool True if test mode enabled.
	 */
	private function is_test_mode(): bool {
		return 'yes' === get_option( 'carticy_ai_checkout_test_mode', 'yes' );
	}

	/**
	 * Check if IP allowlisting is enabled
	 *
	 * @return bool True if enabled.
	 */
	private function is_enabled(): bool {
		return 'yes' === get_option( 'carticy_ai_checkout_enable_ip_allowlist', 'no' );
	}

	/**
	 * Schedule automatic IP range updates
	 *
	 * @return void
	 */
	public function schedule_auto_update(): void {
		if ( ! wp_next_scheduled( 'carticy_ai_checkout_update_openai_ips' ) ) {
			wp_schedule_event( time(), 'hourly', 'carticy_ai_checkout_update_openai_ips', array() );
		}
	}

	/**
	 * Unschedule automatic IP range updates
	 *
	 * @return void
	 */
	public function unschedule_auto_update(): void {
		$timestamp = wp_next_scheduled( 'carticy_ai_checkout_update_openai_ips' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'carticy_ai_checkout_update_openai_ips' );
		}
	}
}
