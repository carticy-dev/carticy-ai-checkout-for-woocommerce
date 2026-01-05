<?php
/**
 * Error Handler
 *
 * Provides centralized error handling with user-friendly messages
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ErrorHandler
 *
 * Handles errors with user-friendly messages and admin notifications
 */
final class ErrorHandler {

	/**
	 * Error log service instance.
	 *
	 * @var ErrorLogService
	 */
	private ErrorLogService $error_logger;

	/**
	 * User-friendly error messages mapped to technical error codes
	 *
	 * @var array<string, string>
	 */
	private const ERROR_MESSAGES = array(
		// Payment errors.
		'payment_failed'                 => 'Payment processing failed. Please try again or contact support.',
		'invalid_payment_token'          => 'Invalid payment information. Please verify your payment details.',
		'stripe_connection_failed'       => 'Unable to connect to payment processor. Please try again later.',
		'payment_declined'               => 'Your payment was declined. Please use a different payment method.',
		'insufficient_funds'             => 'Insufficient funds. Please use a different payment method.',
		'card_expired'                   => 'Your card has expired. Please use a different payment method.',
		'invalid_card'                   => 'Invalid card details. Please check your payment information.',

		// API errors.
		'api_authentication_failed'      => 'Authentication failed. Please check your API credentials.',
		'api_rate_limit_exceeded'        => 'Too many requests. Please try again in a few moments.',
		'api_invalid_request'            => 'Invalid request. Please check your input and try again.',
		'api_connection_failed'          => 'Connection failed. Please check your internet connection.',
		'api_timeout'                    => 'Request timeout. Please try again.',

		// Webhook errors.
		'webhook_signature_invalid'      => 'Invalid webhook signature. Security verification failed.',
		'webhook_processing_failed'      => 'Webhook processing failed. The event will be retried.',
		'webhook_delivery_failed'        => 'Failed to deliver webhook. Please check your endpoint.',

		// Validation errors.
		'invalid_session'                => 'Invalid or expired session. Please start a new checkout.',
		'invalid_product'                => 'Product not found or unavailable.',
		'invalid_quantity'               => 'Invalid quantity. Please check your order.',
		'invalid_address'                => 'Invalid shipping address. Please verify your details.',
		'invalid_email'                  => 'Invalid email address. Please check and try again.',

		// System errors.
		'prerequisites_not_met'          => 'System requirements not met. Please configure required plugins.',
		'stripe_gateway_not_enabled'     => 'Stripe payment gateway is not enabled. Please configure Stripe.',
		'product_feed_generation_failed' => 'Failed to generate product feed. Please try again.',
		'session_expired'                => 'Your session has expired. Please start a new checkout.',
		'database_error'                 => 'Database error occurred. Please try again or contact support.',
		'file_system_error'              => 'File system error. Please check permissions.',
		'configuration_error'            => 'Configuration error. Please check your settings.',

		// Generic errors.
		'unknown_error'                  => 'An unexpected error occurred. Please try again or contact support.',
	);

	/**
	 * Constructor
	 *
	 * @param ErrorLogService $error_logger Error logging service.
	 */
	public function __construct( ErrorLogService $error_logger ) {
		$this->error_logger = $error_logger;
	}

	/**
	 * Handle an error with logging and user notification
	 *
	 * @param string          $error_code Error code.
	 * @param string          $technical_message Technical error message for logging.
	 * @param array           $context Additional context for logging.
	 * @param \Throwable|null $exception Exception if available.
	 * @return \WP_Error
	 */
	public function handle_error(
		string $error_code,
		string $technical_message,
		array $context = array(),
		?\Throwable $exception = null
	): \WP_Error {
		// Log the technical error.
		$this->log_error( $error_code, $technical_message, $context, $exception );

		// Get user-friendly message.
		$user_message = $this->get_user_friendly_message( $error_code );

		// Create WP_Error for return.
		$error = new \WP_Error(
			$error_code,
			$user_message,
			array(
				'status' => $this->get_http_status_code( $error_code ),
			)
		);

		// Schedule admin notice if this is a critical error.
		if ( $this->is_critical_error( $error_code ) ) {
			$this->schedule_admin_notice( $error_code, $user_message, $technical_message );
		}

		return $error;
	}

	/**
	 * Handle payment error
	 *
	 * @param string          $error_code Payment error code.
	 * @param string          $technical_message Technical error message.
	 * @param array           $context Additional context.
	 * @param \Throwable|null $exception Exception if available.
	 * @return \WP_Error
	 */
	public function handle_payment_error(
		string $error_code,
		string $technical_message,
		array $context = array(),
		?\Throwable $exception = null
	): \WP_Error {
		$this->error_logger->log_payment_error( $technical_message, $context, $exception );
		return $this->create_user_error( $error_code );
	}

	/**
	 * Handle API error
	 *
	 * @param string          $error_code API error code.
	 * @param string          $endpoint API endpoint.
	 * @param string          $technical_message Technical error message.
	 * @param array           $context Additional context.
	 * @param \Throwable|null $exception Exception if available.
	 * @return \WP_Error
	 */
	public function handle_api_error(
		string $error_code,
		string $endpoint,
		string $technical_message,
		array $context = array(),
		?\Throwable $exception = null
	): \WP_Error {
		$this->error_logger->log_api_error( $technical_message, $endpoint, $context, $exception );
		return $this->create_user_error( $error_code );
	}

	/**
	 * Handle webhook error
	 *
	 * @param string          $error_code Webhook error code.
	 * @param string          $event_type Webhook event type.
	 * @param string          $technical_message Technical error message.
	 * @param array           $context Additional context.
	 * @param \Throwable|null $exception Exception if available.
	 * @return \WP_Error
	 */
	public function handle_webhook_error(
		string $error_code,
		string $event_type,
		string $technical_message,
		array $context = array(),
		?\Throwable $exception = null
	): \WP_Error {
		$this->error_logger->log_webhook_error( $technical_message, $event_type, $context, $exception );
		return $this->create_user_error( $error_code );
	}

	/**
	 * Handle validation error
	 *
	 * @param string $error_code Validation error code.
	 * @param array  $failed_fields Failed validation fields.
	 * @param array  $context Additional context.
	 * @return \WP_Error
	 */
	public function handle_validation_error(
		string $error_code,
		array $failed_fields = array(),
		array $context = array()
	): \WP_Error {
		$technical_message = sprintf(
			'Validation failed: %s',
			implode( ', ', $failed_fields )
		);
		$this->error_logger->log_validation_error( $technical_message, $failed_fields, $context );
		return $this->create_user_error( $error_code );
	}

	/**
	 * Get user-friendly error message
	 *
	 * @param string $error_code Error code.
	 * @return string User-friendly message.
	 */
	private function get_user_friendly_message( string $error_code ): string {
		return self::ERROR_MESSAGES[ $error_code ] ?? self::ERROR_MESSAGES['unknown_error'];
	}

	/**
	 * Create WP_Error with user-friendly message
	 *
	 * @param string $error_code Error code.
	 * @return \WP_Error
	 */
	private function create_user_error( string $error_code ): \WP_Error {
		return new \WP_Error(
			$error_code,
			$this->get_user_friendly_message( $error_code ),
			array(
				'status' => $this->get_http_status_code( $error_code ),
			)
		);
	}

	/**
	 * Get HTTP status code for error
	 *
	 * @param string $error_code Error code.
	 * @return int HTTP status code.
	 */
	private function get_http_status_code( string $error_code ): int {
		$status_map = array(
			'api_authentication_failed' => 401,
			'webhook_signature_invalid' => 401,
			'api_invalid_request'       => 400,
			'invalid_session'           => 400,
			'invalid_product'           => 404,
			'api_rate_limit_exceeded'   => 429,
			'api_timeout'               => 408,
			'payment_failed'            => 402,
			'payment_declined'          => 402,
		);

		return $status_map[ $error_code ] ?? 500;
	}

	/**
	 * Log error with appropriate category
	 *
	 * @param string          $error_code Error code.
	 * @param string          $message Technical message.
	 * @param array           $context Context.
	 * @param \Throwable|null $exception Exception.
	 */
	private function log_error(
		string $error_code,
		string $message,
		array $context,
		?\Throwable $exception
	): void {
		// Determine category from error code prefix.
		if ( strpos( $error_code, 'payment_' ) === 0 ) {
			$this->error_logger->log_payment_error( $message, $context, $exception );
		} elseif ( strpos( $error_code, 'api_' ) === 0 ) {
			$this->error_logger->log_api_error( $message, '', $context, $exception );
		} elseif ( strpos( $error_code, 'webhook_' ) === 0 ) {
			$this->error_logger->log_webhook_error( $message, '', $context, $exception );
		} else {
			$this->error_logger->log_system_error( $message, $context, $exception );
		}
	}

	/**
	 * Check if error is critical
	 *
	 * @param string $error_code Error code.
	 * @return bool True if critical.
	 */
	private function is_critical_error( string $error_code ): bool {
		$critical_errors = array(
			'prerequisites_not_met',
			'stripe_gateway_not_enabled',
			'configuration_error',
			'database_error',
		);

		return in_array( $error_code, $critical_errors, true );
	}

	/**
	 * Schedule admin notice for critical errors
	 *
	 * @param string $error_code Error code.
	 * @param string $user_message User-friendly message.
	 * @param string $technical_message Technical message.
	 */
	private function schedule_admin_notice(
		string $error_code,
		string $user_message,
		string $technical_message
	): void {
		$notices = get_option( 'carticy_ai_checkout_admin_notices', array() );

		$notices[] = array(
			'code'      => $error_code,
			'message'   => $user_message,
			'technical' => $technical_message,
			'timestamp' => time(),
			'dismissed' => false,
		);

		// Keep only last 50 notices.
		if ( count( $notices ) > 50 ) {
			$notices = array_slice( $notices, -50 );
		}

		update_option( 'carticy_ai_checkout_admin_notices', $notices );
	}

	/**
	 * Get admin notices
	 *
	 * @param bool $include_dismissed Include dismissed notices.
	 * @return array Admin notices.
	 */
	public function get_admin_notices( bool $include_dismissed = false ): array {
		$notices = get_option( 'carticy_ai_checkout_admin_notices', array() );

		if ( ! $include_dismissed ) {
			$notices = array_filter(
				$notices,
				function ( $notice ) {
					return ! $notice['dismissed'];
				}
			);
		}

		return $notices;
	}

	/**
	 * Dismiss admin notice
	 *
	 * @param int $notice_index Notice index.
	 */
	public function dismiss_notice( int $notice_index ): void {
		$notices = get_option( 'carticy_ai_checkout_admin_notices', array() );

		if ( isset( $notices[ $notice_index ] ) ) {
			$notices[ $notice_index ]['dismissed'] = true;
			update_option( 'carticy_ai_checkout_admin_notices', $notices );
		}
	}

	/**
	 * Clear all dismissed notices
	 */
	public function clear_dismissed_notices(): void {
		$notices = get_option( 'carticy_ai_checkout_admin_notices', array() );

		$notices = array_filter(
			$notices,
			function ( $notice ) {
				return ! $notice['dismissed'];
			}
		);

		update_option( 'carticy_ai_checkout_admin_notices', array_values( $notices ) );
	}
}
