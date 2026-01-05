<?php
/**
 * Plugin Uninstall
 *
 * Cleans up all plugin data when the plugin is uninstalled.
 * Only runs if user has enabled "Delete all data on uninstall" option.
 *
 * @package Carticy\AiCheckout
 * @since 1.0.0
 */

// Exit if not called during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if user wants to delete data on uninstall.
$carticy_ai_checkout_delete_data = get_option( 'carticy_ai_checkout_delete_data_on_uninstall', 'no' );

if ( 'yes' !== $carticy_ai_checkout_delete_data ) {
	// User wants to preserve data - skip cleanup.
	return;
}

// User opted to delete all data - proceed with cleanup.

/**
 * Delete all plugin options from the database.
 */
function carticy_ai_checkout_uninstall_delete_options(): void {
	global $wpdb;

	// List of all plugin options.
	$options = array(
		'carticy_ai_checkout_api_key',
		'carticy_ai_checkout_test_mode',
		'carticy_ai_checkout_webhook_secret',
		'carticy_ai_checkout_enabled',
		'carticy_ai_checkout_webhook_url',
		'carticy_ai_checkout_enable_ip_allowlist',
		'carticy_ai_checkout_test_bypass_ip',
		'carticy_ai_checkout_admin_notices',
		'carticy_ai_checkout_application_data',
		'carticy_ai_checkout_openai_ip_ranges',
		'carticy_ai_checkout_openai_ip_ranges_last_updated',
		'carticy_ai_checkout_openai_ip_ranges_backup',
		'carticy_ai_checkout_feed_last_updated',
		'carticy_ai_checkout_webhook_retry_queue',
		'carticy_ai_checkout_test_webhook_url',
		'carticy_ai_checkout_enable_openai_robots',
		'carticy_ai_checkout_delete_data_on_uninstall',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete any remaining options that start with carticy_ai_checkout_.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'carticy_ai_checkout_' ) . '%'
		)
	);
}

/**
 * Delete all plugin transients from the database.
 */
function carticy_ai_checkout_uninstall_delete_transients(): void {
	global $wpdb;

	// Delete specific transients.
	delete_transient( 'carticy_ai_checkout_conformance_test_results' );
	delete_transient( 'carticy_ai_checkout_mock_scenario_results' );
	delete_transient( 'carticy_ai_checkout_testing_redirect' );

	// Delete all transients that start with carticy_ai_checkout_.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_carticy_ai_checkout_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_carticy_ai_checkout_' ) . '%'
		)
	);

	// Delete SharedPaymentToken transients (chatgpt_spt_{order_id}).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_chatgpt_spt_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_chatgpt_spt_' ) . '%'
		)
	);

	// Delete session transients (chatgpt_session_{session_id}).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_chatgpt_session_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_chatgpt_session_' ) . '%'
		)
	);

	// Delete webhook status transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_carticy_webhook_status_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_carticy_webhook_status_' ) . '%'
		)
	);
}

/**
 * Delete all plugin post meta from products.
 */
function carticy_ai_checkout_uninstall_delete_post_meta(): void {
	global $wpdb;

	// Delete product meta for ChatGPT enablement and quality checks.
	$meta_keys = array(
		'_carticy_chatgpt_enabled',
		'_carticy_chatgpt_quality_score',
		'_carticy_chatgpt_quality_issues',
		'_carticy_ai_checkout_enabled',
		'_chatgpt_checkout',
		'_chatgpt_session_id',
	);

	foreach ( $meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->postmeta,
			array( 'meta_key' => $meta_key ),
			array( '%s' )
		);
	}

	// Delete any post meta that starts with _carticy_ that we might have missed.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_carticy_' ) . '%'
		)
	);
}

/**
 * Clear all scheduled cron events.
 */
function carticy_ai_checkout_uninstall_clear_cron_events(): void {
	$events = array(
		'carticy_ai_checkout_update_openai_ips',
		'carticy_ai_checkout_refresh_product_feed',
		'carticy_ai_checkout_cleanup_sessions',
	);

	foreach ( $events as $event ) {
		$timestamp = wp_next_scheduled( $event );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $event );
		}
		// Also clear all scheduled events with this hook (in case of duplicates).
		wp_clear_scheduled_hook( $event );
	}
}

/**
 * Delete log files if they exist.
 */
function carticy_ai_checkout_uninstall_delete_log_files(): void {
	$upload_dir = wp_upload_dir();
	$log_dir    = $upload_dir['basedir'] . '/carticy-ai-checkout-logs';

	if ( is_dir( $log_dir ) ) {
		// Remove all files in the log directory.
		$files = glob( $log_dir . '/*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}

		// Remove the directory itself using WP_Filesystem.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( $wp_filesystem ) {
			$wp_filesystem->rmdir( $log_dir );
		}
	}
}

// Execute cleanup functions.
carticy_ai_checkout_uninstall_delete_options();
carticy_ai_checkout_uninstall_delete_transients();
carticy_ai_checkout_uninstall_delete_post_meta();
carticy_ai_checkout_uninstall_clear_cron_events();
carticy_ai_checkout_uninstall_delete_log_files();
