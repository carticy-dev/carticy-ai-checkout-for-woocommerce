<?php
/**
 * Plugin Activation Handler
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Core;

/**
 * Handles plugin activation
 */
final class Activator {
	/**
	 * Activate the plugin
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Set default options.
		self::set_defaults();

		// Schedule cron events.
		self::schedule_events();

		// Flush rewrite rules for REST API endpoints.
		flush_rewrite_rules();
	}

	/**
	 * Set default options
	 *
	 * @return void
	 */
	private static function set_defaults(): void {
		$defaults = array(
			'carticy_ai_checkout_test_mode'      => 'yes',
			'carticy_ai_checkout_api_key'        => wp_generate_password( 32, false ),
			'carticy_ai_checkout_webhook_secret' => wp_generate_password( 32, false ),
			'carticy_ai_checkout_webhook_url'    => '',
			'carticy_ai_checkout_settings'       => array(),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Schedule cron events
	 *
	 * @return void
	 */
	private static function schedule_events(): void {
		// Product feed refresh every 15 minutes.
		if ( ! wp_next_scheduled( 'carticy_ai_checkout_refresh_product_feed' ) ) {
			wp_schedule_event( time(), 'every_15_minutes', 'carticy_ai_checkout_refresh_product_feed', array() );
		}

		// Session cleanup daily.
		if ( ! wp_next_scheduled( 'carticy_ai_checkout_cleanup_sessions' ) ) {
			wp_schedule_event( time(), 'daily', 'carticy_ai_checkout_cleanup_sessions', array() );
		}

		// OpenAI IP ranges update hourly.
		if ( ! wp_next_scheduled( 'carticy_ai_checkout_update_openai_ips' ) ) {
			wp_schedule_event( time(), 'hourly', 'carticy_ai_checkout_update_openai_ips', array() );
		}
	}
}
