<?php
/**
 * Test Mode Service
 *
 * Reads whether the store runs against the test webhook and Stripe test keys.
 *
 * @package Carticy\AiCheckout\Services
 */

namespace Carticy\AiCheckout\Services;

/**
 * Test Mode Service class
 *
 * The option holds 'yes' or 'no', so a plain truthiness check reads 'no' as on;
 * every reader goes through is_test_mode().
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
	 * Check if test mode is enabled
	 *
	 * @return bool True if test mode is active, false otherwise.
	 */
	public function is_test_mode(): bool {
		return 'yes' === get_option( self::OPTION_KEY, 'yes' );
	}
}
