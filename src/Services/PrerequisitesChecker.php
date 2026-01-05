<?php
/**
 * Prerequisites Checker Service
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

/**
 * Checks plugin prerequisites and system requirements
 */
final class PrerequisitesChecker {
	/**
	 * Check all prerequisites
	 *
	 * @return array<string, array{passed: bool, message: string, action?: string}> Prerequisites status.
	 */
	public function check_all(): array {
		return array(
			'wordpress_version' => $this->check_wordpress_version(),
			'php_version'       => $this->check_php_version(),
			'woocommerce'       => $this->check_woocommerce(),
			'stripe_gateway'    => $this->check_stripe_gateway(),
			'stripe_keys'       => $this->check_stripe_keys(),
			'ssl'               => $this->check_ssl(),
			'permalinks'        => $this->check_permalinks(),
			'stripe_api'        => $this->check_stripe_connectivity(),
		);
	}

	/**
	 * Check if all critical prerequisites are met
	 *
	 * @return bool True if all critical checks pass.
	 */
	public function all_met(): bool {
		$checks   = $this->check_all();
		$critical = array( 'woocommerce', 'stripe_gateway', 'stripe_keys', 'ssl', 'permalinks' );

		foreach ( $critical as $check ) {
			if ( ! $checks[ $check ]['passed'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check all prerequisites with admin notice format
	 *
	 * Returns failed checks in format expected by AdminNotices class.
	 *
	 * @return array<int, array{severity: string, requirement: string, message: string, fix_url: string}> Failed prerequisites.
	 */
	public function check_all_prerequisites(): array {
		$checks = $this->check_all();
		$issues = array();

		// Define critical vs warning checks.
		$critical_checks = array( 'woocommerce', 'stripe_gateway', 'ssl', 'permalinks' );

		foreach ( $checks as $key => $check ) {
			if ( ! $check['passed'] ) {
				$issues[] = array(
					'severity'    => in_array( $key, $critical_checks, true ) ? 'error' : 'warning',
					'requirement' => $this->get_requirement_label( $key ),
					'message'     => $check['message'],
					'fix_url'     => $check['action'] ?? '',
				);
			}
		}

		return $issues;
	}

	/**
	 * Get human-readable label for requirement key
	 *
	 * @param string $key Requirement key.
	 * @return string Human-readable label.
	 */
	private function get_requirement_label( string $key ): string {
		$labels = array(
			'wordpress_version' => 'WordPress Version',
			'php_version'       => 'PHP Version',
			'woocommerce'       => 'WooCommerce',
			'stripe_gateway'    => 'WooCommerce Stripe Gateway',
			'stripe_keys'       => 'Stripe API Keys',
			'ssl'               => 'SSL Certificate',
			'permalinks'        => 'Permalink Structure',
			'stripe_api'        => 'Stripe API Connection',
		);

		return $labels[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) );
	}

	/**
	 * Check WordPress version
	 *
	 * @return array{passed: bool, message: string}
	 */
	private function check_wordpress_version(): array {
		global $wp_version;

		$required = '5.8';
		$passed   = version_compare( $wp_version, $required, '>=' );

		return array(
			'passed'  => $passed,
			'message' => $passed
				? sprintf( 'WordPress %s installed', $wp_version )
				: sprintf( 'WordPress %s or higher required (currently %s)', $required, $wp_version ),
		);
	}

	/**
	 * Check PHP version
	 *
	 * @return array{passed: bool, message: string}
	 */
	private function check_php_version(): array {
		$required = '8.0';
		$current  = phpversion();
		$passed   = version_compare( $current, $required, '>=' );

		return array(
			'passed'  => $passed,
			'message' => $passed
				? sprintf( 'PHP %s installed', $current )
				: sprintf( 'PHP %s or higher required (currently %s)', $required, $current ),
		);
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return array{passed: bool, message: string, action?: string}
	 */
	private function check_woocommerce(): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'passed'  => false,
				'message' => 'WooCommerce plugin is not active',
				'action'  => admin_url( 'plugins.php' ),
			);
		}

		$required = '7.6';
		$current  = defined( 'WC_VERSION' ) ? WC_VERSION : '0';
		$passed   = version_compare( $current, $required, '>=' );

		return array(
			'passed'  => $passed,
			'message' => $passed
				? sprintf( 'WooCommerce %s active', $current )
				: sprintf( 'WooCommerce %s or higher required (currently %s)', $required, $current ),
			'action'  => admin_url( 'plugins.php' ),
		);
	}

	/**
	 * Check if WooCommerce Stripe Gateway is active
	 *
	 * @return array{passed: bool, message: string, action?: string}
	 */
	private function check_stripe_gateway(): array {
		$plugin_file = 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php';
		$is_active   = is_plugin_active( $plugin_file );

		if ( ! $is_active ) {
			// Use get_plugins() to check installation (WordPress native function).
			$all_plugins  = get_plugins();
			$is_installed = isset( $all_plugins[ $plugin_file ] );

			return array(
				'passed'  => false,
				'message' => $is_installed
					? 'WooCommerce Stripe Gateway is installed but not active'
					: 'WooCommerce Stripe Gateway plugin is not installed',
				'action'  => $is_installed
					? admin_url( 'plugins.php' )
					: 'https://wordpress.org/plugins/woocommerce-gateway-stripe/',
			);
		}

		return array(
			'passed'  => true,
			'message' => 'WooCommerce Stripe Gateway active',
		);
	}

	/**
	 * Check if Stripe API keys are configured
	 *
	 * @return array{passed: bool, message: string, action?: string}
	 */
	private function check_stripe_keys(): array {
		$stripe_settings = get_option( 'woocommerce_stripe_settings', array() );

		$test_publishable = $stripe_settings['test_publishable_key'] ?? '';
		$test_secret      = $stripe_settings['test_secret_key'] ?? '';
		$live_publishable = $stripe_settings['publishable_key'] ?? '';
		$live_secret      = $stripe_settings['secret_key'] ?? '';

		$test_mode = ( $stripe_settings['testmode'] ?? 'yes' ) === 'yes';

		if ( $test_mode ) {
			$passed  = ! empty( $test_publishable ) && ! empty( $test_secret );
			$message = $passed
				? 'Stripe test keys configured'
				: 'Stripe test keys not configured';
		} else {
			$passed  = ! empty( $live_publishable ) && ! empty( $live_secret );
			$message = $passed
				? 'Stripe live keys configured'
				: 'Stripe live keys not configured';
		}

		return array(
			'passed'  => $passed,
			'message' => $message,
			'action'  => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ),
		);
	}

	/**
	 * Check if SSL is enabled
	 *
	 * @return array{passed: bool, message: string}
	 */
	private function check_ssl(): array {
		$is_localhost = $this->is_localhost();
		$passed       = is_ssl() || $is_localhost;

		if ( $is_localhost ) {
			return array(
				'passed'  => true,
				'message' => 'Development environment detected (SSL not required)',
			);
		}

		return array(
			'passed'  => $passed,
			'message' => $passed
				? 'SSL certificate active (HTTPS enabled)'
				: 'SSL certificate required. ChatGPT integration requires HTTPS.',
		);
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

	/**
	 * Check if pretty permalinks are enabled
	 *
	 * @return array{passed: bool, message: string, action?: string}
	 */
	private function check_permalinks(): array {
		$permalink_structure = get_option( 'permalink_structure' );
		$passed              = ! empty( $permalink_structure );

		return array(
			'passed'  => $passed,
			'message' => $passed
				? 'Pretty permalinks enabled'
				: 'Pretty permalinks required for REST API',
			'action'  => admin_url( 'options-permalink.php' ),
		);
	}

	/**
	 * Check Stripe API connectivity
	 *
	 * @return array{passed: bool, message: string}
	 */
	private function check_stripe_connectivity(): array {
		if ( ! class_exists( 'WC_Stripe_API' ) ) {
			return array(
				'passed'  => false,
				'message' => 'Stripe API class not available',
			);
		}

		try {
			// Test API connectivity by retrieving account info.
			$account = \WC_Stripe_API::retrieve( 'account' );

			if ( is_wp_error( $account ) ) {
				return array(
					'passed'  => false,
					'message' => 'Stripe API connection failed: ' . $account->get_error_message(),
				);
			}

			if ( ! empty( $account->error ) ) {
				return array(
					'passed'  => false,
					'message' => 'Stripe API error: ' . $account->error->message,
				);
			}

			$account_id = $account->id ?? 'unknown';

			return array(
				'passed'  => true,
				'message' => sprintf( 'Connected to Stripe account: %s', $account_id ),
			);
		} catch ( \Exception $e ) {
			return array(
				'passed'  => false,
				'message' => 'Stripe API exception: ' . $e->getMessage(),
			);
		}
	}
}
