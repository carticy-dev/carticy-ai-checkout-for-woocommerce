<?php
/**
 * Logging Service
 *
 * Wrapper around WooCommerce Logger for structured logging
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

use WC_Logger_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LoggingService
 *
 * Provides structured logging using WooCommerce's logger
 */
final class LoggingService {

	/**
	 * WooCommerce logger instance.
	 *
	 * @var WC_Logger_Interface
	 */
	private WC_Logger_Interface $logger;

	/**
	 * Whether debug mode is enabled.
	 *
	 * @var bool
	 */
	private bool $debug_enabled;

	/**
	 * Log contexts for different plugin areas.
	 */
	private const CONTEXT_API         = 'carticy-api';
	private const CONTEXT_ERROR       = 'carticy-errors';
	private const CONTEXT_WEBHOOK     = 'carticy-webhooks';
	private const CONTEXT_PERFORMANCE = 'carticy-performance';
	private const CONTEXT_DEBUG       = 'carticy-debug';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger        = wc_get_logger();
		$this->debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Log API request/response.
	 *
	 * @param string $endpoint      Endpoint path.
	 * @param string $method        HTTP method.
	 * @param array  $request_data  Request data.
	 * @param array  $response_data Response data.
	 * @param int    $status_code   HTTP status code.
	 * @param float  $duration      Request duration in seconds.
	 */
	public function log_api_request(
		string $endpoint,
		string $method,
		array $request_data,
		array $response_data,
		int $status_code,
		float $duration
	): void {
		$message = sprintf(
			'[%s] %s %s - Status: %d - Duration: %.3fs',
			strtoupper( $method ),
			$endpoint,
			$status_code >= 400 ? 'FAILED' : 'SUCCESS',
			$status_code,
			$duration
		);

		$context = array(
			'endpoint'    => $endpoint,
			'method'      => $method,
			'status_code' => $status_code,
			'duration'    => $duration,
			'timestamp'   => current_time( 'mysql' ),
		);

		// Add request/response data in debug mode
		if ( $this->debug_enabled ) {
			$context['request']  = $this->sanitize_sensitive_data( $request_data );
			$context['response'] = $this->sanitize_sensitive_data( $response_data );
		}

		// Log level based on status code
		$level = $status_code >= 500 ? 'error' :
				( $status_code >= 400 ? 'warning' : 'info' );

		$this->logger->log(
			$level,
			$message,
			array(
				'source'  => self::CONTEXT_API,
				'context' => $context,
			)
		);
	}

	/**
	 * Log error with context.
	 *
	 * @param string          $message   Error message.
	 * @param string          $category  Error category (payment, api, webhook, validation, system).
	 * @param array           $context   Additional context.
	 * @param \Throwable|null $exception Exception if available.
	 */
	public function log_error(
		string $message,
		string $category,
		array $context = array(),
		?\Throwable $exception = null
	): void {
		$log_message = sprintf( '[%s] %s', strtoupper( $category ), $message );

		$log_context = array(
			'category'  => $category,
			'timestamp' => current_time( 'mysql' ),
		);

		if ( ! empty( $context ) ) {
			$log_context['context'] = $this->sanitize_sensitive_data( $context );
		}

		if ( null !== $exception ) {
			$log_context['exception'] = array(
				'message' => $exception->getMessage(),
				'code'    => $exception->getCode(),
				'file'    => $exception->getFile(),
				'line'    => $exception->getLine(),
			);

			// Add stack trace in debug mode
			if ( $this->debug_enabled ) {
				$log_context['exception']['trace'] = $exception->getTraceAsString();
			}
		}

		$this->logger->error(
			$log_message,
			array(
				'source'  => self::CONTEXT_ERROR,
				'context' => $log_context,
			)
		);
	}

	/**
	 * Log webhook activity.
	 *
	 * @param string $event_type     Webhook event type.
	 * @param string $url            Webhook URL.
	 * @param array  $payload        Webhook payload.
	 * @param int    $status_code    Response status code.
	 * @param int    $attempt_number Retry attempt number.
	 * @param bool   $success        Whether webhook delivery succeeded.
	 */
	public function log_webhook(
		string $event_type,
		string $url,
		array $payload,
		int $status_code,
		int $attempt_number,
		bool $success
	): void {
		$message = sprintf(
			'[%s] %s - Attempt %d - %s',
			$event_type,
			$success ? 'SUCCESS' : 'FAILED',
			$attempt_number,
			$url
		);

		$context = array(
			'event_type'  => $event_type,
			'url'         => $url,
			'status_code' => $status_code,
			'attempt'     => $attempt_number,
			'success'     => $success,
			'timestamp'   => current_time( 'mysql' ),
		);

		// Add payload in debug mode
		if ( $this->debug_enabled ) {
			$context['payload'] = $this->sanitize_sensitive_data( $payload );
		}

		$level = $success ? 'info' : ( $attempt_number >= 3 ? 'error' : 'warning' );

		$this->logger->log(
			$level,
			$message,
			array(
				'source'  => self::CONTEXT_WEBHOOK,
				'context' => $context,
			)
		);
	}

	/**
	 * Log performance metrics.
	 *
	 * @param string $operation Operation name.
	 * @param float  $duration  Duration in seconds.
	 * @param array  $metrics   Additional metrics.
	 */
	public function log_performance(
		string $operation,
		float $duration,
		array $metrics = array()
	): void {
		$message = sprintf(
			'[PERFORMANCE] %s - Duration: %.3fs',
			$operation,
			$duration
		);

		$context = array(
			'operation' => $operation,
			'duration'  => $duration,
			'timestamp' => current_time( 'mysql' ),
		);

		if ( ! empty( $metrics ) ) {
			$context['metrics'] = $metrics;
		}

		$this->logger->debug(
			$message,
			array(
				'source'  => self::CONTEXT_PERFORMANCE,
				'context' => $context,
			)
		);
	}

	/**
	 * Log debug information.
	 *
	 * @param string $message Debug message.
	 * @param array  $context Context data.
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( ! $this->debug_enabled ) {
			return;
		}

		$this->logger->debug(
			$message,
			array(
				'source'  => self::CONTEXT_DEBUG,
				'context' => array_merge(
					$context,
					array(
						'timestamp' => current_time( 'mysql' ),
					)
				),
			)
		);
	}

	/**
	 * Get log files for a specific context.
	 *
	 * @param string $context Log context.
	 * @return array Array of log file paths.
	 */
	public function get_log_files( string $context ): array {
		$log_dir = WC_LOG_DIR;

		if ( ! is_dir( $log_dir ) ) {
			return array();
		}

		$files = glob( $log_dir . '/' . $context . '-*.log' );

		if ( false === $files ) {
			return array();
		}

		// Sort by modification time (newest first)
		usort(
			$files,
			function ( $a, $b ) {
				return filemtime( $b ) - filemtime( $a );
			}
		);

		return $files;
	}

	/**
	 * Parse log file and return entries.
	 *
	 * @param string $file_path Path to log file.
	 * @param int    $limit     Maximum number of entries to return.
	 * @param int    $offset    Offset for pagination.
	 * @return array Array of log entries.
	 */
	public function parse_log_file( string $file_path, int $limit = 100, int $offset = 0 ): array {
		global $wp_filesystem;

		// Initialize WP_Filesystem.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			return array();
		}

		if ( ! $wp_filesystem->exists( $file_path ) || ! $wp_filesystem->is_readable( $file_path ) ) {
			return array();
		}

		$contents = $wp_filesystem->get_contents( $file_path );

		if ( false === $contents ) {
			return array();
		}

		$lines         = explode( "\n", $contents );
		$entries       = array();
		$current_entry = null;
		$line_number   = 0;

		foreach ( $lines as $line ) {
			// Check if this is a new log entry (starts with timestamp).
			if ( preg_match( '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2})\s+(\w+)\s+(.+)$/', $line, $matches ) ) {
				// Save previous entry if exists.
				if ( null !== $current_entry && $line_number >= $offset && count( $entries ) < $limit ) {
					$entries[] = $current_entry;
				}

				// Start new entry.
				$current_entry = array(
					'timestamp' => $matches[1],
					'level'     => $matches[2],
					'message'   => $matches[3],
					'context'   => null,
				);

				// Parse JSON context (single-line format - WooCommerce logger).
				if ( preg_match( '/CONTEXT:\s*(\{.+\})/', $current_entry['message'], $context_matches ) ) {
					$context_json = json_decode( $context_matches[1], true );
					if ( null !== $context_json ) {
						$current_entry['context'] = $context_json;
						// Clean message by removing CONTEXT JSON.
						$current_entry['message'] = trim( preg_replace( '/\s*CONTEXT:\s*.+/', '', $current_entry['message'] ) );
					}
				}

				++$line_number;
			}
		}

		// Add last entry.
		if ( null !== $current_entry && $line_number >= $offset && count( $entries ) < $limit ) {
			$entries[] = $current_entry;
		}

		return $entries;
	}

	/**
	 * Sanitize sensitive data before logging.
	 *
	 * @param array $data Data to sanitize.
	 * @return array Sanitized data.
	 */
	private function sanitize_sensitive_data( array $data ): array {
		$sensitive_keys = array(
			'password',
			'api_key',
			'secret',
			'token',
			'authorization',
			'credit_card',
			'card_number',
			'cvv',
			'ssn',
		);

		foreach ( $data as $key => $value ) {
			$key_lower = strtolower( $key );

			// Check if key contains sensitive information
			foreach ( $sensitive_keys as $sensitive ) {
				if ( false !== strpos( $key_lower, $sensitive ) ) {
					$data[ $key ] = '[REDACTED]';
					continue 2;
				}
			}

			// Recursively sanitize arrays
			if ( is_array( $value ) ) {
				$data[ $key ] = $this->sanitize_sensitive_data( $value );
			}
		}

		return $data;
	}

	/**
	 * Get available log contexts.
	 *
	 * @return array Array of log contexts.
	 */
	public function get_log_contexts(): array {
		return array(
			self::CONTEXT_API         => 'API Requests',
			self::CONTEXT_ERROR       => 'Errors',
			self::CONTEXT_WEBHOOK     => 'Webhooks',
			self::CONTEXT_PERFORMANCE => 'Performance',
			self::CONTEXT_DEBUG       => 'Debug',
		);
	}

	/**
	 * Clear old log files.
	 *
	 * @param int $days Number of days to retain logs.
	 */
	public function cleanup_old_logs( int $days = 30 ): void {
		$log_dir = WC_LOG_DIR;

		if ( ! is_dir( $log_dir ) ) {
			return;
		}

		$contexts    = array_keys( $this->get_log_contexts() );
		$cutoff_time = time() - ( $days * DAY_IN_SECONDS );

		foreach ( $contexts as $context ) {
			$files = glob( $log_dir . '/' . $context . '-*.log' );

			if ( false === $files ) {
				continue;
			}

			foreach ( $files as $file ) {
				if ( filemtime( $file ) < $cutoff_time ) {
					wp_delete_file( $file );
				}
			}
		}
	}
}
