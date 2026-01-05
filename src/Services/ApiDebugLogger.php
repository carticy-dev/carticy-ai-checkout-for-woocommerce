<?php
/**
 * API Debug Logger Service
 *
 * Provides detailed logging for API requests and responses during testing.
 *
 * @package Carticy\AiCheckout\Services
 */

namespace Carticy\AiCheckout\Services;

/**
 * API Debug Logger class
 *
 * Handles detailed request/response logging for debugging purposes.
 *
 * @since 1.0.0
 */
final class ApiDebugLogger {

	/**
	 * Logging service
	 *
	 * @var LoggingService
	 */
	private LoggingService $logging_service;

	/**
	 * Test mode service
	 *
	 * @var TestModeService
	 */
	private TestModeService $test_mode_service;

	/**
	 * Constructor
	 *
	 * @param LoggingService  $logging_service   Logging service instance.
	 * @param TestModeService $test_mode_service Test mode service instance.
	 */
	public function __construct( LoggingService $logging_service, TestModeService $test_mode_service ) {
		$this->logging_service   = $logging_service;
		$this->test_mode_service = $test_mode_service;
	}

	/**
	 * Log API request
	 *
	 * @param string $endpoint    Endpoint path.
	 * @param string $method      HTTP method.
	 * @param array  $headers     Request headers.
	 * @param mixed  $body        Request body.
	 * @param string $request_id  Unique request ID.
	 * @return void
	 */
	public function log_request( string $endpoint, string $method, array $headers, $body, string $request_id ): void {
		$sanitized_headers = $this->sanitize_headers( $headers );
		$sanitized_body    = $this->sanitize_body( $body );

		$log_data = array(
			'request_id' => $request_id,
			'endpoint'   => $endpoint,
			'method'     => $method,
			'headers'    => $sanitized_headers,
			'body'       => $sanitized_body,
			'timestamp'  => current_time( 'mysql' ),
			'mode'       => $this->test_mode_service->get_mode(),
		);

		$this->logging_service->info(
			sprintf( 'API Request: %s %s', $method, $endpoint ),
			$log_data,
			'carticy-api-debug'
		);

		// Store in transient for recent requests display.
		$this->store_debug_request( $request_id, $log_data );
	}

	/**
	 * Log API response
	 *
	 * @param string $request_id     Unique request ID.
	 * @param int    $status_code    HTTP status code.
	 * @param array  $headers        Response headers.
	 * @param mixed  $body           Response body.
	 * @param float  $execution_time Execution time in seconds.
	 * @return void
	 */
	public function log_response( string $request_id, int $status_code, array $headers, $body, float $execution_time ): void {
		$sanitized_headers = $this->sanitize_headers( $headers );
		$sanitized_body    = $this->sanitize_body( $body );

		$log_data = array(
			'request_id'     => $request_id,
			'status_code'    => $status_code,
			'headers'        => $sanitized_headers,
			'body'           => $sanitized_body,
			'execution_time' => $execution_time,
			'timestamp'      => current_time( 'mysql' ),
			'mode'           => $this->test_mode_service->get_mode(),
		);

		$level = $status_code >= 400 ? 'error' : 'info';

		$this->logging_service->log(
			$level,
			sprintf( 'API Response: %d (%.3fs)', $status_code, $execution_time ),
			$log_data,
			'carticy-api-debug'
		);

		// Update transient with response data.
		$this->update_debug_response( $request_id, $log_data );
	}

	/**
	 * Log API error
	 *
	 * @param string $request_id Unique request ID.
	 * @param string $error_code Error code.
	 * @param string $message    Error message.
	 * @param array  $context    Additional context.
	 * @return void
	 */
	public function log_error( string $request_id, string $error_code, string $message, array $context = array() ): void {
		$log_data = array_merge(
			array(
				'request_id' => $request_id,
				'error_code' => $error_code,
				'message'    => $message,
				'timestamp'  => current_time( 'mysql' ),
				'mode'       => $this->test_mode_service->get_mode(),
			),
			$context
		);

		$this->logging_service->error(
			sprintf( 'API Error: %s - %s', $error_code, $message ),
			$log_data,
			'carticy-api-debug'
		);

		// Update transient with error data.
		$this->update_debug_error( $request_id, $log_data );
	}

	/**
	 * Get recent debug logs
	 *
	 * @param int $limit Number of recent logs to retrieve.
	 * @return array Array of debug log entries.
	 */
	public function get_recent_logs( int $limit = 50 ): array {
		$logs_key = $this->test_mode_service->get_transient_key( 'carticy_ai_checkout_debug_logs' );
		$logs     = get_transient( $logs_key );

		if ( ! is_array( $logs ) ) {
			return array();
		}

		// Sort by timestamp descending.
		usort(
			$logs,
			function ( $a, $b ) {
				return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
			}
		);

		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Get debug log by request ID
	 *
	 * @param string $request_id Request ID.
	 * @return array|null Debug log entry or null if not found.
	 */
	public function get_log_by_id( string $request_id ): ?array {
		$logs = $this->get_recent_logs( 1000 );

		foreach ( $logs as $log ) {
			if ( isset( $log['request_id'] ) && $log['request_id'] === $request_id ) {
				return $log;
			}
		}

		return null;
	}

	/**
	 * Clear debug logs
	 *
	 * @return bool True on success.
	 */
	public function clear_logs(): bool {
		$logs_key = $this->test_mode_service->get_transient_key( 'carticy_ai_checkout_debug_logs' );
		return delete_transient( $logs_key );
	}

	/**
	 * Sanitize headers for logging
	 *
	 * @param array $headers Headers array.
	 * @return array Sanitized headers.
	 */
	private function sanitize_headers( array $headers ): array {
		$sanitized = array();

		foreach ( $headers as $key => $value ) {
			$lower_key = strtolower( $key );

			// Redact sensitive headers.
			if ( in_array( $lower_key, array( 'authorization', 'x-api-key', 'api-key' ), true ) ) {
				$sanitized[ $key ] = $this->redact_sensitive_value( $value );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize body for logging
	 *
	 * @param mixed $body Request/response body.
	 * @return mixed Sanitized body.
	 */
	private function sanitize_body( $body ) {
		if ( is_string( $body ) ) {
			$decoded = json_decode( $body, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $this->sanitize_array( $decoded );
			}
			return $body;
		}

		if ( is_array( $body ) ) {
			return $this->sanitize_array( $body );
		}

		return $body;
	}

	/**
	 * Sanitize array recursively
	 *
	 * @param array $data Data array.
	 * @return array Sanitized array.
	 */
	private function sanitize_array( array $data ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$lower_key = strtolower( $key );

			// Redact sensitive fields.
			if ( in_array( $lower_key, array( 'token', 'password', 'api_key', 'secret', 'shared_payment_token' ), true ) ) {
				$sanitized[ $key ] = $this->redact_sensitive_value( $value );
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_array( $value );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Redact sensitive value
	 *
	 * @param mixed $value Value to redact.
	 * @return string Redacted value.
	 */
	private function redact_sensitive_value( $value ): string {
		if ( ! is_string( $value ) || strlen( $value ) < 8 ) {
			return '[REDACTED]';
		}

		$first = substr( $value, 0, 4 );
		$last  = substr( $value, -4 );

		return $first . '...' . $last;
	}

	/**
	 * Store debug request in transient
	 *
	 * @param string $request_id Request ID.
	 * @param array  $log_data   Log data.
	 * @return void
	 */
	private function store_debug_request( string $request_id, array $log_data ): void {
		$logs_key = $this->test_mode_service->get_transient_key( 'carticy_ai_checkout_debug_logs' );
		$logs     = get_transient( $logs_key );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$log_data['type'] = 'request';
		$logs[]           = $log_data;

		// Keep only last 100 logs.
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		set_transient( $logs_key, $logs, DAY_IN_SECONDS );
	}

	/**
	 * Update debug log with response data
	 *
	 * @param string $request_id Request ID.
	 * @param array  $log_data   Response log data.
	 * @return void
	 */
	private function update_debug_response( string $request_id, array $log_data ): void {
		$logs_key = $this->test_mode_service->get_transient_key( 'carticy_ai_checkout_debug_logs' );
		$logs     = get_transient( $logs_key );

		if ( ! is_array( $logs ) ) {
			return;
		}

		// Find matching request and add response data.
		foreach ( $logs as &$log ) {
			if ( isset( $log['request_id'] ) && $log['request_id'] === $request_id ) {
				$log['response']        = $log_data;
				$log['type']            = 'complete';
				$log['execution_time']  = $log_data['execution_time'];
				$log['response_status'] = $log_data['status_code'];
				break;
			}
		}

		set_transient( $logs_key, $logs, DAY_IN_SECONDS );
	}

	/**
	 * Update debug log with error data
	 *
	 * @param string $request_id Request ID.
	 * @param array  $log_data   Error log data.
	 * @return void
	 */
	private function update_debug_error( string $request_id, array $log_data ): void {
		$logs_key = $this->test_mode_service->get_transient_key( 'carticy_ai_checkout_debug_logs' );
		$logs     = get_transient( $logs_key );

		if ( ! is_array( $logs ) ) {
			return;
		}

		// Find matching request and add error data.
		foreach ( $logs as &$log ) {
			if ( isset( $log['request_id'] ) && $log['request_id'] === $request_id ) {
				$log['error'] = $log_data;
				$log['type']  = 'error';
				break;
			}
		}

		set_transient( $logs_key, $logs, DAY_IN_SECONDS );
	}
}
