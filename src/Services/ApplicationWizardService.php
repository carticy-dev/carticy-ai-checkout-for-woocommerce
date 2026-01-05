<?php
/**
 * Application Wizard Service
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

/**
 * Handles wizard state management and business logic
 */
final class ApplicationWizardService {
	/**
	 * Total number of wizard steps
	 */
	private const TOTAL_STEPS = 9;

	/**
	 * Wizard data transient key prefix
	 */
	private const TRANSIENT_PREFIX = 'carticy_ai_checkout_wizard_data_';

	/**
	 * Wizard data transient expiration (7 days)
	 */
	private const TRANSIENT_EXPIRATION = 7 * DAY_IN_SECONDS;

	/**
	 * Get current wizard step
	 *
	 * @return int Current step number (1-6).
	 */
	public function get_current_step(): int {
		$wizard_data = $this->get_wizard_data();
		return isset( $wizard_data['current_step'] ) ? (int) $wizard_data['current_step'] : 1;
	}

	/**
	 * Set current wizard step
	 *
	 * @param int $step Step number.
	 * @return bool True on success.
	 */
	public function set_current_step( int $step ): bool {
		if ( $step < 1 || $step > self::TOTAL_STEPS ) {
			return false;
		}

		$wizard_data                 = $this->get_wizard_data();
		$wizard_data['current_step'] = $step;
		$wizard_data['last_updated'] = time();

		return $this->save_wizard_data( $wizard_data );
	}

	/**
	 * Get wizard data from transient
	 *
	 * @return array Wizard data.
	 */
	public function get_wizard_data(): array {
		$user_id = get_current_user_id();
		$data    = get_transient( self::TRANSIENT_PREFIX . $user_id );

		return is_array( $data ) ? $data : array(
			'current_step'    => 1,
			'completed_steps' => array(),
			'business_info'   => array(),
			'product_config'  => array(),
			'policies'        => array(),
			'quality_review'  => array(),
			'prerequisites'   => array(),
			'test_setup'      => array(),
			'integration'     => array(),
			'conformance'     => array(),
			'submit'          => array(),
		);
	}

	/**
	 * Save wizard data to transient
	 *
	 * @param array $data Wizard data.
	 * @return bool True on success.
	 */
	public function save_wizard_data( array $data ): bool {
		$user_id = get_current_user_id();
		return set_transient( self::TRANSIENT_PREFIX . $user_id, $data, self::TRANSIENT_EXPIRATION );
	}

	/**
	 * Save step data
	 *
	 * @param int   $step Step number.
	 * @param array $data Step data.
	 * @return bool True on success.
	 */
	public function save_step_data( int $step, array $data ): bool {
		$wizard_data = $this->get_wizard_data();

		// Determine step key.
		$step_key = match ( $step ) {
			1 => 'business_info',
			2 => 'product_config',
			3 => 'policies',
			4 => 'quality_review',
			5 => 'prerequisites',
			6 => 'test_setup',
			7 => 'integration',
			8 => 'conformance',
			9 => 'submit',
			default => '',
		};

		if ( empty( $step_key ) ) {
			return false;
		}

		$wizard_data[ $step_key ] = $data;

		// Mark step as completed.
		if ( ! in_array( $step, $wizard_data['completed_steps'], true ) ) {
			$wizard_data['completed_steps'][] = $step;
		}

		return $this->save_wizard_data( $wizard_data );
	}

	/**
	 * Validate business information
	 *
	 * @param array $data Business info data.
	 * @return array|bool Validation errors or true if valid.
	 */
	public function validate_business_info( array $data ): array|bool {
		$errors = array();

		if ( empty( $data['business_name'] ) ) {
			$errors['business_name'] = __( 'Business name is required.', 'carticy-ai-checkout-for-woocommerce' );
		}

		if ( empty( $data['business_entity'] ) ) {
			$errors['business_entity'] = __( 'Business entity type is required.', 'carticy-ai-checkout-for-woocommerce' );
		}

		if ( empty( $data['contact_name'] ) ) {
			$errors['contact_name'] = __( 'Contact name is required.', 'carticy-ai-checkout-for-woocommerce' );
		}

		if ( empty( $data['contact_email'] ) || ! is_email( $data['contact_email'] ) ) {
			$errors['contact_email'] = __( 'Valid contact email is required.', 'carticy-ai-checkout-for-woocommerce' );
		}

		if ( empty( $data['store_url'] ) || ! filter_var( $data['store_url'], FILTER_VALIDATE_URL ) ) {
			$errors['store_url'] = __( 'Valid store URL is required.', 'carticy-ai-checkout-for-woocommerce' );
		}

		return empty( $errors ) ? true : $errors;
	}

	/**
	 * Validate policy URLs
	 *
	 * @param array $data Policy URLs data.
	 * @return array|bool Validation errors or true if valid.
	 */
	public function validate_policies( array $data ): array|bool {
		$errors = array();

		// Required policy URLs (Terms and Privacy are required, Return/Refund is optional).
		$required_policies = array(
			'terms_of_service' => __( 'Terms of Service URL', 'carticy-ai-checkout-for-woocommerce' ),
			'privacy_policy'   => __( 'Privacy Policy URL', 'carticy-ai-checkout-for-woocommerce' ),
		);

		// Optional policies (for businesses that don't have them, e.g., digital-only stores).
		$optional_policies = array(
			'return_policy' => __( 'Return/Refund Policy URL', 'carticy-ai-checkout-for-woocommerce' ),
		);

		// Validate required policies.
		foreach ( $required_policies as $key => $label ) {
			if ( empty( $data[ $key ] ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: policy name */
					__( '%s is required.', 'carticy-ai-checkout-for-woocommerce' ),
					$label
				);
				continue;
			}

			if ( ! filter_var( $data[ $key ], FILTER_VALIDATE_URL ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: policy name */
					__( '%s must be a valid URL.', 'carticy-ai-checkout-for-woocommerce' ),
					$label
				);
				continue;
			}

			// Check if URL is reachable (allow 2xx status codes, not just 200).
			$response = wp_remote_head( $data[ $key ], array( 'timeout' => 10 ) );
			if ( is_wp_error( $response ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: policy name */
					__( '%s could not be reached. Please check the URL.', 'carticy-ai-checkout-for-woocommerce' ),
					$label
				);
			} else {
				$status_code = wp_remote_retrieve_response_code( $response );
				// Accept any 2xx success code (200, 201, 204, etc.).
				if ( $status_code < 200 || $status_code >= 300 ) {
					$errors[ $key ] = sprintf(
						/* translators: 1: policy name, 2: HTTP status code */
						__( '%1$s returned HTTP %2$d. Please ensure the page is publicly accessible.', 'carticy-ai-checkout-for-woocommerce' ),
						$label,
						$status_code
					);
				}
			}
		}

		// Validate optional policies (only if provided).
		foreach ( $optional_policies as $key => $label ) {
			// Skip if not provided - this is allowed.
			if ( empty( $data[ $key ] ) ) {
				continue;
			}

			if ( ! filter_var( $data[ $key ], FILTER_VALIDATE_URL ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: policy name */
					__( '%s must be a valid URL.', 'carticy-ai-checkout-for-woocommerce' ),
					$label
				);
				continue;
			}

			// Check if URL is reachable.
			$response = wp_remote_head( $data[ $key ], array( 'timeout' => 10 ) );
			if ( is_wp_error( $response ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: policy name */
					__( '%s could not be reached. Please check the URL.', 'carticy-ai-checkout-for-woocommerce' ),
					$label
				);
			} else {
				$status_code = wp_remote_retrieve_response_code( $response );
				if ( $status_code < 200 || $status_code >= 300 ) {
					$errors[ $key ] = sprintf(
						/* translators: 1: policy name, 2: HTTP status code */
						__( '%1$s returned HTTP %2$d. Please ensure the page is publicly accessible.', 'carticy-ai-checkout-for-woocommerce' ),
						$label,
						$status_code
					);
				}
			}
		}

		// Note: It's acceptable for policies to share the same URL.
		// Many businesses use a single page for all policies.

		return empty( $errors ) ? true : $errors;
	}

	/**
	 * Generate application data for OpenAI submission
	 *
	 * @return array Application data.
	 */
	public function generate_application_data(): array {
		$wizard_data   = $this->get_wizard_data();
		$business_info = $wizard_data['business_info'] ?? array();
		$policies      = $wizard_data['policies'] ?? array();

		$application_data = array(
			'version'      => '1.0',
			'generated_at' => current_time( 'c' ),
			'business'     => array(
				'name'        => $business_info['business_name'] ?? '',
				'entity_type' => $business_info['business_entity'] ?? '',
				'website'     => $business_info['store_url'] ?? site_url(),
				'contact'     => array(
					'name'  => $business_info['contact_name'] ?? '',
					'email' => $business_info['contact_email'] ?? '',
				),
			),
			'policies'     => array(
				'terms_of_service' => $policies['terms_of_service'] ?? '',
				'privacy_policy'   => $policies['privacy_policy'] ?? '',
				'return_policy'    => $policies['return_policy'] ?? '',
			),
			'technical'    => array(
				'product_feed_url'  => rest_url( 'carticy-ai-checkout/v1/products' ),
				'checkout_api_base' => rest_url( 'carticy-ai-checkout/v1/' ),
				'ssl_enabled'       => is_ssl(),
				'platform'          => 'WooCommerce',
				'platform_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
			),
			'integration'  => array(
				'payment_processor' => 'Stripe',
				'stripe_connected'  => $this->check_stripe_connection(),
			),
		);

		// Save to permanent option.
		update_option( 'carticy_ai_checkout_application_data', $application_data );

		return $application_data;
	}

	/**
	 * Check if Stripe is properly connected
	 *
	 * @return bool True if Stripe is connected.
	 */
	private function check_stripe_connection(): bool {
		// Check if WooCommerce Stripe Gateway is active.
		if ( ! class_exists( 'WC_Gateway_Stripe' ) ) {
			return false;
		}

		// Check if API keys are configured.
		$test_mode = get_option( 'woocommerce_stripe_settings', array() );
		$live_mode = get_option( 'woocommerce_stripe_settings', array() );

		$has_test_keys = ! empty( $test_mode['testmode'] ) && ! empty( $test_mode['test_secret_key'] );
		$has_live_keys = ! empty( $live_mode['secret_key'] );

		return $has_test_keys || $has_live_keys;
	}

	/**
	 * Clear wizard data
	 *
	 * @return bool True on success.
	 */
	public function clear_wizard_data(): bool {
		$user_id = get_current_user_id();
		return delete_transient( self::TRANSIENT_PREFIX . $user_id );
	}

	/**
	 * Get total steps count
	 *
	 * @return int Total steps.
	 */
	public function get_total_steps(): int {
		return self::TOTAL_STEPS;
	}

	/**
	 * Check if step is completed
	 *
	 * @param int $step Step number.
	 * @return bool True if completed.
	 */
	public function is_step_completed( int $step ): bool {
		$wizard_data = $this->get_wizard_data();
		return in_array( $step, $wizard_data['completed_steps'] ?? array(), true );
	}

	/**
	 * Get completion percentage
	 *
	 * @return int Percentage (0-100).
	 */
	public function get_completion_percentage(): int {
		$wizard_data = $this->get_wizard_data();
		$completed   = count( $wizard_data['completed_steps'] ?? array() );
		return (int) ( ( $completed / self::TOTAL_STEPS ) * 100 );
	}
}
