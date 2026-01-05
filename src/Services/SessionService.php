<?php
/**
 * Session Service
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

/**
 * Manages checkout session storage and lifecycle
 */
final class SessionService {
	/**
	 * Session transient prefix
	 */
	private const SESSION_PREFIX = 'carticy_ai_checkout_session_';

	/**
	 * Session expiration time (24 hours)
	 */
	private const SESSION_TTL = DAY_IN_SECONDS;

	/**
	 * Create a new checkout session
	 *
	 * @param string               $session_id Unique session identifier.
	 * @param array<string, mixed> $data Session data.
	 * @return bool True if session created successfully.
	 */
	public function create( string $session_id, array $data ): bool {
		$session_key = self::SESSION_PREFIX . $session_id;

		// Add metadata.
		$data['session_id'] = $session_id;
		$data['created_at'] = time();
		$data['updated_at'] = time();
		$data['expires_at'] = time() + self::SESSION_TTL;
		$data['status']     = $data['status'] ?? 'active';

		return set_transient( $session_key, $data, self::SESSION_TTL );
	}

	/**
	 * Retrieve a checkout session
	 *
	 * @param string $session_id Session identifier.
	 * @return array<string, mixed>|null Session data or null if not found.
	 */
	public function get( string $session_id ): ?array {
		$session_key = self::SESSION_PREFIX . $session_id;
		$data        = get_transient( $session_key );

		return false !== $data ? $data : null;
	}

	/**
	 * Update an existing checkout session
	 *
	 * @param string               $session_id Session identifier.
	 * @param array<string, mixed> $data Updated session data.
	 * @return bool True if session updated successfully.
	 */
	public function update( string $session_id, array $data ): bool {
		$existing = $this->get( $session_id );

		if ( null === $existing ) {
			return false;
		}

		// Merge with existing data and update timestamp.
		$updated               = array_merge( $existing, $data );
		$updated['updated_at'] = time();

		$session_key = self::SESSION_PREFIX . $session_id;

		return set_transient( $session_key, $updated, self::SESSION_TTL );
	}

	/**
	 * Delete a checkout session
	 *
	 * @param string $session_id Session identifier.
	 * @return bool True if session deleted successfully.
	 */
	public function delete( string $session_id ): bool {
		$session_key = self::SESSION_PREFIX . $session_id;

		return delete_transient( $session_key );
	}

	/**
	 * Check if a session exists
	 *
	 * @param string $session_id Session identifier.
	 * @return bool True if session exists.
	 */
	public function exists( string $session_id ): bool {
		return null !== $this->get( $session_id );
	}

	/**
	 * Update session status
	 *
	 * @param string $session_id Session identifier.
	 * @param string $status New status (active, completed, cancelled, expired).
	 * @return bool True if status updated successfully.
	 */
	public function update_status( string $session_id, string $status ): bool {
		return $this->update( $session_id, array( 'status' => $status ) );
	}

	/**
	 * Clean up expired sessions
	 *
	 * Optimized cleanup that queries only expired timeout entries instead of loading
	 * all session data into memory. This prevents memory exhaustion on high-traffic sites.
	 *
	 * WordPress stores transient timeouts as separate database rows with prefix '_transient_timeout_'.
	 * We query these timeout rows to find expired sessions without loading session data.
	 *
	 * @return int Number of sessions cleaned up.
	 */
	public function cleanup_expired(): int {
		global $wpdb;

		$prefix = '_transient_timeout_' . self::SESSION_PREFIX;
		$count  = 0;

		// Query ONLY expired timeout entries (option_value < current_time).
		// Limit to 100 sessions per run to prevent long-running queries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired_timeouts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_value < %d
				LIMIT 100",
				$wpdb->esc_like( $prefix ) . '%',
				time()
			)
		);

		// Extract session IDs from timeout option names and delete sessions.
		foreach ( $expired_timeouts as $timeout_option ) {
			// Extract session ID by removing the timeout prefix.
			$session_id = str_replace( $prefix, '', $timeout_option->option_name );

			// Delete session without loading data into memory.
			if ( $this->delete( $session_id ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Clean up orphaned sessions (sessions where order failed/completed)
	 *
	 * Finds sessions that have corresponding orders with completed/failed status
	 * and deletes them since they're no longer needed.
	 *
	 * @return int Number of sessions cleaned up.
	 */
	public function cleanup_orphaned_sessions(): int {
		global $wpdb;

		$count = 0;

		// Get all session transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 LIMIT 100",
				$wpdb->esc_like( '_transient_' . self::SESSION_PREFIX ) . '%'
			)
		);

		foreach ( $sessions as $session_option ) {
			// Extract session ID.
			$session_id = str_replace( '_transient_' . self::SESSION_PREFIX, '', $session_option->option_name );

			// Unserialize session data.
			$session_data = maybe_unserialize( $session_option->option_value );

			if ( ! is_array( $session_data ) ) {
				continue;
			}

			// Check if session has an associated order.
			$order_id = $session_data['order_id'] ?? null;

			if ( $order_id ) {
				// Order exists - check its status.
				$order = wc_get_order( $order_id );

				if ( $order ) {
					$order_status = $order->get_status();

					// Delete session if order is completed or failed.
					if ( in_array( $order_status, array( 'completed', 'failed', 'cancelled', 'refunded' ), true ) ) {
						if ( $this->delete( $session_id ) ) {
							++$count;
						}
					}
				}
			} else {
				// No order associated - check session status.
				$session_status = $session_data['status'] ?? 'active';

				// Delete if session status is completed, failed, or cancelled.
				if ( in_array( $session_status, array( 'completed', 'failed', 'cancelled' ), true ) ) {
					if ( $this->delete( $session_id ) ) {
						++$count;
					}
				}
			}
		}

		return $count;
	}

	/**
	 * Clean up old completed and failed sessions
	 *
	 * Removes sessions that have been completed or failed for more than 7 days.
	 * Per ACP specification, completed sessions should remain accessible for audit,
	 * but can be cleaned up after a reasonable retention period.
	 *
	 * Called by Init::cleanup_expired_sessions() via WP-Cron (twice daily).
	 *
	 * @return int Number of sessions cleaned up.
	 */
	public function cleanup_completed_sessions(): int {
		global $wpdb;

		$count          = 0;
		$seven_days_ago = time() - ( 7 * DAY_IN_SECONDS );

		// Get all session transients (limit 100 per run to prevent performance issues).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 LIMIT 100",
				$wpdb->esc_like( '_transient_' . self::SESSION_PREFIX ) . '%'
			)
		);

		foreach ( $sessions as $session_option ) {
			// Extract session ID.
			$session_id = str_replace( '_transient_' . self::SESSION_PREFIX, '', $session_option->option_name );

			// Unserialize session data.
			$session_data = maybe_unserialize( $session_option->option_value );

			if ( ! is_array( $session_data ) ) {
				continue;
			}

			$session_status = $session_data['status'] ?? 'unknown';
			$updated_at     = $session_data['updated_at'] ?? 0;

			// Delete completed or failed sessions older than 7 days.
			// These have served their audit purpose and can be safely removed.
			if ( in_array( $session_status, array( 'completed', 'failed' ), true ) && $updated_at < $seven_days_ago ) {
				if ( $this->delete( $session_id ) ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Clean up abandoned active sessions
	 *
	 * Removes sessions with status='active' that have no activity for 2+ hours.
	 * These represent abandoned checkouts (user left ChatGPT, network errors, etc.).
	 * Real checkouts complete within minutes - anything older than 2 hours is abandoned.
	 *
	 * Called by Init::cleanup_expired_sessions() via WP-Cron (twice daily).
	 *
	 * @return int Number of sessions cleaned up.
	 */
	public function cleanup_abandoned_sessions(): int {
		global $wpdb;

		$count         = 0;
		$two_hours_ago = time() - ( 2 * HOUR_IN_SECONDS );

		// Get all session transients (limit 100 per run).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 LIMIT 100",
				$wpdb->esc_like( '_transient_' . self::SESSION_PREFIX ) . '%'
			)
		);

		foreach ( $sessions as $session_option ) {
			// Extract session ID.
			$session_id = str_replace( '_transient_' . self::SESSION_PREFIX, '', $session_option->option_name );

			// Unserialize session data.
			$session_data = maybe_unserialize( $session_option->option_value );

			if ( ! is_array( $session_data ) ) {
				continue;
			}

			$session_status = $session_data['status'] ?? 'unknown';
			$updated_at     = $session_data['updated_at'] ?? 0;
			$order_id       = $session_data['order_id'] ?? null;

			// Delete active sessions without orders that haven't been updated in 2+ hours.
			// Real checkouts complete within minutes - anything older is abandoned.
			if ( 'active' === $session_status && ! $order_id && $updated_at < $two_hours_ago ) {
				if ( $this->delete( $session_id ) ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Store SharedPaymentToken for order
	 *
	 * @param int    $order_id Order ID.
	 * @param string $token SharedPaymentToken.
	 * @return bool True if token stored successfully.
	 */
	public function store_payment_token( int $order_id, string $token ): bool {
		$key = 'carticy_ai_checkout_spt_' . $order_id;
		return set_transient( $key, $token, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Retrieve SharedPaymentToken for order
	 *
	 * @param int $order_id Order ID.
	 * @return string|null Token or null if not found.
	 */
	public function get_payment_token( int $order_id ): ?string {
		$key   = 'carticy_ai_checkout_spt_' . $order_id;
		$token = get_transient( $key );

		return false !== $token ? $token : null;
	}

	/**
	 * Delete SharedPaymentToken for order
	 *
	 * @param int $order_id Order ID.
	 * @return bool True if token deleted successfully.
	 */
	public function delete_payment_token( int $order_id ): bool {
		$key = 'carticy_ai_checkout_spt_' . $order_id;
		return delete_transient( $key );
	}
}
