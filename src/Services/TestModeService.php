<?php
/**
 * Test Mode Service
 *
 * Manages test/production mode state and environment configuration.
 *
 * @package Carticy\AiCheckout\Services
 */

namespace Carticy\AiCheckout\Services;

/**
 * Test Mode Service class
 *
 * Handles test mode toggle, state detection, and test data isolation.
 *
 * @since 1.0.0
 */
final class TestModeService {

	/**
	 * Test mode option key
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'carticy_ai_checkout_test_mode';

	/**
	 * Test data prefix
	 *
	 * @var string
	 */
	private const TEST_PREFIX = 'test_';

	/**
	 * Check if test mode is enabled
	 *
	 * @return bool True if test mode is active, false otherwise.
	 */
	public function is_test_mode(): bool {
		return 'yes' === get_option( self::OPTION_KEY, 'yes' );
	}

	/**
	 * Enable test mode
	 *
	 * @return bool True on success, false on failure.
	 */
	public function enable_test_mode(): bool {
		$result = update_option( self::OPTION_KEY, 'yes' );

		if ( $result ) {
			do_action( 'carticy_ai_checkout_test_mode_enabled' );
		}

		return $result;
	}

	/**
	 * Disable test mode
	 *
	 * @return bool True on success, false on failure.
	 */
	public function disable_test_mode(): bool {
		$result = update_option( self::OPTION_KEY, 'no' );

		if ( $result ) {
			do_action( 'carticy_ai_checkout_test_mode_disabled' );
		}

		return $result;
	}

	/**
	 * Toggle test mode
	 *
	 * @return array{success: bool, new_state: bool, message: string} Toggle result with success status, new state, and message.
	 */
	public function toggle_test_mode(): array {
		$current_state = $this->is_test_mode();
		$new_state     = ! $current_state;
		$new_value     = $new_state ? 'yes' : 'no';

		$result = update_option( self::OPTION_KEY, $new_value );

		// Verify the option was actually updated.
		$verified_state = $this->is_test_mode();

		if ( ! $result || $verified_state !== $new_state ) {
			return array(
				'success'   => false,
				'new_state' => $current_state,
				'message'   => __( 'Failed to update test mode. Please try again.', 'carticy-ai-checkout-for-woocommerce' ),
			);
		}

		if ( $new_state ) {
			do_action( 'carticy_ai_checkout_test_mode_enabled' );
		} else {
			do_action( 'carticy_ai_checkout_test_mode_disabled' );
		}

		return array(
			'success'   => true,
			'new_state' => $new_state,
			'message'   => '',
		);
	}

	/**
	 * Get test mode status string
	 *
	 * @return string 'test' or 'production'.
	 */
	public function get_mode(): string {
		return $this->is_test_mode() ? 'test' : 'production';
	}

	/**
	 * Get prefixed transient key for test mode
	 *
	 * @param string $key Base transient key.
	 * @return string Prefixed key if in test mode, original key otherwise.
	 */
	public function get_transient_key( string $key ): string {
		return $this->is_test_mode() ? self::TEST_PREFIX . $key : $key;
	}

	/**
	 * Get prefixed option key for test mode
	 *
	 * @param string $key Base option key.
	 * @return string Prefixed key if in test mode, original key otherwise.
	 */
	public function get_option_key( string $key ): string {
		return $this->is_test_mode() ? self::TEST_PREFIX . $key : $key;
	}

	/**
	 * Get Stripe API mode
	 *
	 * @return string 'test' or 'live'.
	 */
	public function get_stripe_mode(): string {
		return $this->is_test_mode() ? 'test' : 'live';
	}

	/**
	 * Should bypass IP allowlist in test mode
	 *
	 * @return bool True to bypass IP allowlist, false otherwise.
	 */
	public function should_bypass_ip_allowlist(): bool {
		$bypass_enabled = get_option( 'carticy_ai_checkout_test_bypass_ip', true );
		return $this->is_test_mode() && $bypass_enabled;
	}

	/**
	 * Get test mode indicator HTML
	 *
	 * @return string HTML for admin bar indicator.
	 */
	public function get_admin_bar_indicator(): string {
		if ( ! $this->is_test_mode() ) {
			return '';
		}

		return sprintf(
			'<div style="background: #f0ad4e; color: #000; padding: 5px 15px; font-weight: bold; text-align: center;">
				⚠️ %s
			</div>',
			esc_html__( 'TEST MODE ACTIVE - Using Test Stripe Keys', 'carticy-ai-checkout-for-woocommerce' )
		);
	}

	/**
	 * Get test mode label for admin UI
	 *
	 * @return string Label text.
	 */
	public function get_mode_label(): string {
		if ( $this->is_test_mode() ) {
			return __( 'Test Mode', 'carticy-ai-checkout-for-woocommerce' );
		}

		return __( 'Production Mode', 'carticy-ai-checkout-for-woocommerce' );
	}

	/**
	 * Get test mode badge HTML
	 *
	 * @return string HTML badge.
	 */
	public function get_mode_badge(): string {
		$is_test = $this->is_test_mode();
		$class   = $is_test ? 'test-mode' : 'production-mode';
		$label   = $is_test ? __( 'TEST', 'carticy-ai-checkout-for-woocommerce' ) : __( 'LIVE', 'carticy-ai-checkout-for-woocommerce' );
		$color   = $is_test ? '#f0ad4e' : '#5cb85c';

		return sprintf(
			'<span class="carticy-mode-badge %s" style="background: %s; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">%s</span>',
			esc_attr( $class ),
			esc_attr( $color ),
			esc_html( $label )
		);
	}

	/**
	 * Clean up test data
	 *
	 * Removes all test transients and options.
	 *
	 * @return int Number of items cleaned up.
	 */
	public function cleanup_test_data(): int {
		global $wpdb;

		$count = 0;

		// Clean up test transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transient_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'_transient_' . self::TEST_PREFIX . '%',
				'_transient_timeout_' . self::TEST_PREFIX . '%'
			)
		);

		foreach ( $transient_keys as $key ) {
			if ( str_starts_with( $key, '_transient_timeout_' ) ) {
				$transient_name = str_replace( '_transient_timeout_', '', $key );
			} else {
				$transient_name = str_replace( '_transient_', '', $key );
			}

			if ( delete_transient( $transient_name ) ) {
				++$count;
			}
		}

		// Clean up test options.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name != %s",
				self::TEST_PREFIX . '%',
				self::OPTION_KEY
			)
		);

		foreach ( $option_keys as $key ) {
			if ( delete_option( $key ) ) {
				++$count;
			}
		}

		do_action( 'carticy_ai_checkout_test_data_cleaned', $count );

		return $count;
	}

	/**
	 * Get test data statistics
	 *
	 * @return array{transients: int, options: int, sessions: int}
	 */
	public function get_test_data_stats(): array {
		global $wpdb;

		// Count test transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transient_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				WHERE option_name LIKE %s",
				'_transient_' . self::TEST_PREFIX . '%'
			)
		);

		// Count test options.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name != %s",
				self::TEST_PREFIX . '%',
				self::OPTION_KEY
			)
		);

		// Count test sessions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				WHERE option_name LIKE %s",
				'_transient_' . self::TEST_PREFIX . 'carticy_ai_checkout_session_%'
			)
		);

		return array(
			'transients' => $transient_count,
			'options'    => $option_count,
			'sessions'   => $session_count,
		);
	}
}
