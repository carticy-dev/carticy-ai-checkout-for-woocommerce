<?php
/**
 * API Key Service
 *
 * Handles generation and management of API keys and webhook secrets
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

/**
 * API Key Service Class
 */
final class ApiKeyService {
	private const OPTION_API_KEY        = 'carticy_ai_checkout_api_key';
	private const OPTION_WEBHOOK_SECRET = 'carticy_ai_checkout_webhook_secret';

	/**
	 * Generate a secure bearer token.
	 *
	 * @return string The generated bearer token.
	 */
	public function generate_bearer_token(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Generate a secure webhook secret.
	 *
	 * @return string The generated webhook secret.
	 */
	public function generate_webhook_secret(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Get the current API key (bearer token).
	 *
	 * @return string|null The API key or null if not set.
	 */
	public function get_api_key(): ?string {
		$key = get_option( self::OPTION_API_KEY );
		return $key ? (string) $key : null;
	}

	/**
	 * Get the current webhook secret.
	 *
	 * @return string|null The webhook secret or null if not set.
	 */
	public function get_webhook_secret(): ?string {
		$secret = get_option( self::OPTION_WEBHOOK_SECRET );
		return $secret ? (string) $secret : null;
	}

	/**
	 * Save API key.
	 *
	 * @param string $key The API key to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_api_key( string $key ): bool {
		return update_option( self::OPTION_API_KEY, $key );
	}

	/**
	 * Save webhook secret.
	 *
	 * @param string $secret The webhook secret to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_webhook_secret( string $secret ): bool {
		return update_option( self::OPTION_WEBHOOK_SECRET, $secret );
	}

	/**
	 * Regenerate API key.
	 *
	 * @return string The new API key.
	 */
	public function regenerate_api_key(): string {
		$new_key = $this->generate_bearer_token();
		$this->save_api_key( $new_key );
		return $new_key;
	}

	/**
	 * Regenerate webhook secret.
	 *
	 * @return string The new webhook secret.
	 */
	public function regenerate_webhook_secret(): string {
		$new_secret = $this->generate_webhook_secret();
		$this->save_webhook_secret( $new_secret );
		return $new_secret;
	}

	/**
	 * Initialize API key if not exists.
	 *
	 * @return void
	 */
	public function init_keys_if_not_exists(): void {
		if ( ! $this->get_api_key() ) {
			$this->save_api_key( $this->generate_bearer_token() );
		}

		if ( ! $this->get_webhook_secret() ) {
			$this->save_webhook_secret( $this->generate_webhook_secret() );
		}
	}

	/**
	 * Validate API key format.
	 *
	 * @param string $key The key to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_api_key( string $key ): bool {
		// API key should be 64 characters hex string
		return (bool) preg_match( '/^[a-f0-9]{64}$/i', $key );
	}
}
