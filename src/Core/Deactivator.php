<?php
/**
 * Plugin Deactivation Handler
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Core;

/**
 * Handles plugin deactivation
 */
final class Deactivator {
	/**
	 * Deactivate the plugin
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Unschedule cron events.
		self::unschedule_events();

		// Clear transients.
		self::clear_transients();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Unschedule all cron events
	 *
	 * @return void
	 */
	private static function unschedule_events(): void {
		$events = array(
			'carticy_ai_checkout_refresh_product_feed',
			'carticy_ai_checkout_cleanup_sessions',
			'carticy_ai_checkout_update_openai_ips',
		);

		foreach ( $events as $event ) {
			$timestamp = wp_next_scheduled( $event );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
			}
		}
	}

	/**
	 * Clear plugin transients
	 *
	 * @return void
	 */
	private static function clear_transients(): void {
		global $wpdb;

		// Delete all transients starting with carticy_ai_checkout_.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_carticy_ai_checkout_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_carticy_ai_checkout_' ) . '%'
			)
		);
	}
}
