<?php
/**
 * Webhook Service for OpenAI Order Notifications
 *
 * Sends outgoing webhooks to OpenAI when ChatGPT orders change status.
 * Implements HMAC-SHA256 signature authentication and retry logic.
 *
 * @package Carticy\AiCheckout\Services
 */

namespace Carticy\AiCheckout\Services;

/**
 * Webhook service implementation
 */
final class WebhookService {

	/**
	 * Maximum retry attempts for failed webhooks
	 *
	 * @var int
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Base retry delay in seconds (exponential backoff)
	 *
	 * @var int
	 */
	private const RETRY_DELAY_BASE = 5;

	/**
	 * Webhook logger
	 *
	 * @var WebhookLogger
	 */
	private WebhookLogger $webhook_logger;

	/**
	 * Constructor
	 *
	 * @param WebhookLogger $webhook_logger Webhook logger instance.
	 */
	public function __construct( WebhookLogger $webhook_logger ) {
		$this->webhook_logger = $webhook_logger;
	}

	/**
	 * Send webhook to OpenAI
	 *
	 * ACP Webhook Specification:
	 *
	 * @see https://github.com/agentic-commerce-protocol/agentic-commerce-protocol/blob/main/examples/examples.agentic_checkout.json
	 *
	 * @param string               $event_type Event type (order_created, order_updated).
	 * @param array<string, mixed> $order_data Order data to send.
	 * @return bool True if webhook sent successfully, false otherwise.
	 */
	public function send( string $event_type, array $order_data ): bool {
		$order_id = $order_data['order_id'] ?? 0;
		$status   = $order_data['status'] ?? '';

		// Check webhook attempt status for proper idempotency.
		// This prevents duplicate sends and manages retry logic for failed attempts.
		$attempt_status = $this->get_webhook_attempt_status( $order_id, $event_type, $status );

		if ( 'sent' === $attempt_status ) {
			return true; // Already sent successfully - no need to log.
		}

		if ( 'failed' === $attempt_status ) {
			// Check if we should retry failed webhook (within 1 hour retry window).
			if ( ! $this->should_retry_failed_webhook( $order_id, $event_type, $status ) ) {
				$this->webhook_logger->log_failure(
					$event_type,
					'',
					$order_data,
					0,
					0,
					'Webhook retry window expired'
				);

				return false;
			}
		}

		// Mark as attempting to prevent concurrent sends during race conditions.
		$this->mark_webhook_attempting( $order_id, $event_type, $status );

		// Get webhook configuration (check test mode for correct URL).
		$is_test_mode = get_option( 'carticy_ai_checkout_test_mode', false );
		if ( $is_test_mode ) {
			$webhook_url = get_option( 'carticy_ai_checkout_test_webhook_url', '' );
		} else {
			$webhook_url = get_option( 'carticy_ai_checkout_webhook_url', '' );
		}
		$webhook_secret = get_option( 'carticy_ai_checkout_webhook_secret', '' );

		// Webhook not configured - mark as failed with reason.
		if ( empty( $webhook_url ) || empty( $webhook_secret ) ) {
			$this->mark_webhook_failed( $order_id, $event_type, $status, 'not_configured' );

			$this->webhook_logger->log_failure(
				$event_type,
				$webhook_url ? $webhook_url : 'not_configured',
				$order_data,
				0,
				0,
				'Webhook not configured - missing URL or secret'
			);

			return false;
		}

		// Build payload.
		$payload = $this->build_payload( $event_type, $order_data );

		// Generate HMAC signature.
		$signature = $this->generate_signature( $payload, $webhook_secret );

		// Send webhook with retry logic.
		$result = $this->send_with_retry( $webhook_url, $payload, $signature );

		// Mark final status based on send result.
		if ( $result ) {
			$this->mark_webhook_sent( $order_id, $event_type, $status );
		} else {
			$this->mark_webhook_failed( $order_id, $event_type, $status, 'send_failed' );
		}

		return $result;
	}

	/**
	 * Build webhook payload (ACP v1.0 format)
	 *
	 * Official ACP Webhook Format:
	 *
	 * @see https://github.com/agentic-commerce-protocol/agentic-commerce-protocol/blob/main/examples/examples.agentic_checkout.json
	 *
	 * Example payload:
	 * {
	 *   "type": "order_created",
	 *   "data": {
	 *     "type": "order",
	 *     "checkout_session_id": "checkout_session_123",
	 *     "permalink_url": "https://example.com/order/123?key=abc",
	 *     "status": "created",
	 *     "refunds": []
	 *   }
	 * }
	 *
	 * @param string               $event_type Event type (order_created, order_updated).
	 * @param array<string, mixed> $order_data Order data with session_id, permalink_url, status, refunds.
	 * @return string JSON-encoded payload.
	 */
	private function build_payload( string $event_type, array $order_data ): string {
		$payload_data = array(
			'type' => $event_type,
			'data' => array(
				'type'                => 'order',
				'checkout_session_id' => $order_data['session_id'] ?? '',
				'permalink_url'       => $order_data['permalink_url'] ?? '',
				'status'              => $order_data['status'] ?? 'created',
				'refunds'             => $order_data['refunds'] ?? array(),
			),
		);

		return wp_json_encode( $payload_data );
	}

	/**
	 * Generate HMAC-SHA256 signature for webhook payload
	 *
	 * @param string $payload        JSON payload.
	 * @param string $webhook_secret Webhook secret key.
	 * @return string HMAC signature.
	 */
	private function generate_signature( string $payload, string $webhook_secret ): string {
		return hash_hmac( 'sha256', $payload, $webhook_secret );
	}

	/**
	 * Send webhook with retry logic
	 *
	 * @param string $webhook_url Webhook URL.
	 * @param string $payload     JSON payload.
	 * @param string $signature   HMAC signature.
	 * @return bool True if webhook sent successfully.
	 */
	private function send_with_retry( string $webhook_url, string $payload, string $signature ): bool {
		$attempts = 0;

		// Generate unique Webhook-ID for this webhook event (persists across retries).
		// This allows the receiver to deduplicate webhook events on their side.
		$webhook_id = wp_generate_uuid4();

		while ( $attempts < self::MAX_RETRIES ) {
			++$attempts;

			// Send webhook request
			$response = wp_remote_post(
				$webhook_url,
				array(
					'headers' => array(
						'Merchant-Signature' => $signature,
						'Content-Type'       => 'application/json',
						'Webhook-ID'         => $webhook_id,
					),
					'body'    => $payload,
					'timeout' => 15,
				)
			);

			// Check if request was successful
			if ( ! is_wp_error( $response ) ) {
				$status_code = wp_remote_retrieve_response_code( $response );

				// Success if 2xx status code
				if ( $status_code >= 200 && $status_code < 300 ) {
					// Log successful webhook delivery
					$payload_data = json_decode( $payload, true );
					$event_type   = $payload_data['type'] ?? 'unknown';

					$this->webhook_logger->log_success(
						$event_type,
						$webhook_url,
						$payload_data,
						$status_code,
						$attempts,
						0.0
					);

					return true;
				}

				// HTTP 429 (Too Many Requests) - stop retrying immediately.
				// Retrying makes rate limiting worse.
				if ( 429 === $status_code ) {
					$payload_data = json_decode( $payload, true );
					$event_type   = $payload_data['type'] ?? 'unknown';

					$this->webhook_logger->log_failure(
						$event_type,
						$webhook_url,
						$payload_data,
						$status_code,
						$attempts,
						'Rate limited (HTTP 429)'
					);

					return false; // Failed - webhook not delivered.
				}

				// Log non-2xx response
				$payload_data = json_decode( $payload, true );
				$event_type   = $payload_data['type'] ?? 'unknown';

				$this->webhook_logger->log_failure(
					$event_type,
					$webhook_url,
					$payload_data,
					$status_code,
					$attempts,
					"HTTP $status_code response"
				);
			} else {
				// Log WP_Error
				$payload_data = json_decode( $payload, true );
				$event_type   = $payload_data['type'] ?? 'unknown';

				$this->webhook_logger->log_failure(
					$event_type,
					$webhook_url,
					$payload_data,
					0,
					$attempts,
					$response->get_error_message()
				);
			}

			// Exponential backoff delay before retry (except on last attempt)
			if ( $attempts < self::MAX_RETRIES ) {
				$delay = self::RETRY_DELAY_BASE * ( 2 ** ( $attempts - 1 ) );
				sleep( $delay );
			}
		}

		// All retries failed - log final failure
		$payload_data = json_decode( $payload, true );
		$event_type   = $payload_data['type'] ?? 'unknown';

		$this->webhook_logger->log_failure(
			$event_type,
			$webhook_url,
			$payload_data,
			0,
			self::MAX_RETRIES,
			'Failed after maximum retry attempts'
		);

		return false;
	}

	/**
	 * Send order_created webhook (ACP v1.0)
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function send_order_created( int $order_id ): void {
		$order = wc_get_order( $order_id );

		// Verify order exists and is a ChatGPT order.
		if ( ! $order || 'yes' !== $order->get_meta( '_chatgpt_checkout' ) ) {
			return;
		}

		$session_id = $order->get_meta( '_chatgpt_session_id' );

		$this->send(
			'order_created',
			array(
				'order_id'      => $order_id,
				'session_id'    => $session_id ? $session_id : 'session_' . $order->get_id(),
				'permalink_url' => $this->get_public_order_url( $order ),
				'status'        => 'created',
				'refunds'       => array(),
			)
		);
	}

	/**
	 * Send order_updated webhook (ACP v1.0)
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function send_order_updated( int $order_id ): void {
		$order = wc_get_order( $order_id );

		// Verify order exists and is a ChatGPT order.
		if ( ! $order || 'yes' !== $order->get_meta( '_chatgpt_checkout' ) ) {
			return;
		}

		$session_id = $order->get_meta( '_chatgpt_session_id' );
		$refunds    = $this->format_refunds( $order );

		$this->send(
			'order_updated',
			array(
				'order_id'      => $order_id,
				'session_id'    => $session_id ? $session_id : 'session_' . $order->get_id(),
				'permalink_url' => $this->get_public_order_url( $order ),
				'status'        => $this->map_order_status( $order->get_status() ),
				'refunds'       => $refunds,
			)
		);
	}

	/**
	 * Send order_updated webhook when order is completed (ACP v1.0)
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function send_order_completed( int $order_id ): void {
		$this->send_order_updated( $order_id );
	}

	/**
	 * Send order_updated webhook when order is cancelled (ACP v1.0)
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function send_order_cancelled( int $order_id ): void {
		$this->send_order_updated( $order_id );
	}

	/**
	 * Send order_updated webhook when order is refunded (ACP v1.0)
	 *
	 * @param int $order_id  WooCommerce order ID.
	 * @param int $refund_id WooCommerce refund ID (unused, required by hook).
	 * @return void
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WooCommerce hook signature.
	 */
	public function send_order_refunded( int $order_id, int $refund_id ): void {
		$this->send_order_updated( $order_id );
	}

	/**
	 * Format refunds array (ACP v1.0 format)
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return array<int, array<string, mixed>> Formatted refunds.
	 */
	private function format_refunds( \WC_Order $order ): array {
		$refunds           = $order->get_refunds();
		$formatted_refunds = array();

		foreach ( $refunds as $refund ) {
			$formatted_refunds[] = array(
				'type'   => 'original_payment',
				'amount' => (int) abs( $refund->get_amount() * 100 ), // Convert to cents.
			);
		}

		return $formatted_refunds;
	}

	/**
	 * Map WooCommerce order status to ACP status (ACP v1.0)
	 *
	 * @param string $wc_status WooCommerce order status.
	 * @return string ACP status.
	 */
	private function map_order_status( string $wc_status ): string {
		$status_map = array(
			'pending'    => 'created',
			'processing' => 'confirmed',
			'on-hold'    => 'manual_review',
			'completed'  => 'fulfilled',
			'cancelled'  => 'canceled',
			'refunded'   => 'canceled',
			'failed'     => 'canceled',
		);

		return $status_map[ $wc_status ] ?? 'created';
	}

	/**
	 * Get webhook attempt status for order/event/status combination
	 *
	 * Returns the current status of webhook delivery attempt.
	 * Possible statuses: 'never_attempted', 'attempting', 'sent', 'failed'.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $event_type Event type (order_created, order_updated).
	 * @param string $status     Order status (created, confirmed, fulfilled, etc.).
	 * @return string Webhook attempt status.
	 */
	private function get_webhook_attempt_status( int $order_id, string $event_type, string $status ): string {
		if ( ! $order_id || ! $event_type || ! $status ) {
			return 'never_attempted';
		}

		$cache_key   = sprintf( 'carticy_webhook_status_%d_%s_%s', $order_id, $event_type, $status );
		$status_data = get_transient( $cache_key );

		return is_array( $status_data ) && isset( $status_data['status'] ) ? $status_data['status'] : 'never_attempted';
	}

	/**
	 * Mark webhook as attempting
	 *
	 * Prevents concurrent webhook sends during race conditions.
	 * Status expires after 1 hour if send never completes.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $event_type Event type (order_created, order_updated).
	 * @param string $status     Order status (created, confirmed, fulfilled, etc.).
	 * @return void
	 */
	private function mark_webhook_attempting( int $order_id, string $event_type, string $status ): void {
		if ( ! $order_id || ! $event_type || ! $status ) {
			return;
		}

		$cache_key = sprintf( 'carticy_webhook_status_%d_%s_%s', $order_id, $event_type, $status );
		set_transient(
			$cache_key,
			array(
				'status'    => 'attempting',
				'timestamp' => time(),
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Mark webhook as sent for this order/event/status combination
	 *
	 * Stores a transient flag to prevent duplicate webhooks. The flag expires after 1 hour,
	 * which is sufficient to prevent duplicates while allowing manual webhook re-sends if needed.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $event_type Event type (order_created, order_updated).
	 * @param string $status     Order status (created, confirmed, fulfilled, etc.).
	 * @return void
	 */
	private function mark_webhook_sent( int $order_id, string $event_type, string $status ): void {
		if ( ! $order_id || ! $event_type || ! $status ) {
			return;
		}

		$cache_key = sprintf( 'carticy_webhook_status_%d_%s_%s', $order_id, $event_type, $status );
		set_transient(
			$cache_key,
			array(
				'status'    => 'sent',
				'timestamp' => time(),
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Mark webhook as failed with reason
	 *
	 * Stores failure status with reason. Failed webhooks can be retried within 1 hour window.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $event_type Event type (order_created, order_updated).
	 * @param string $status     Order status (created, confirmed, fulfilled, etc.).
	 * @param string $reason     Failure reason (not_configured, send_failed, etc.).
	 * @return void
	 */
	private function mark_webhook_failed( int $order_id, string $event_type, string $status, string $reason ): void {
		if ( ! $order_id || ! $event_type || ! $status ) {
			return;
		}

		$cache_key = sprintf( 'carticy_webhook_status_%d_%s_%s', $order_id, $event_type, $status );
		set_transient(
			$cache_key,
			array(
				'status'    => 'failed',
				'reason'    => $reason,
				'timestamp' => time(),
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Check if failed webhook should be retried
	 *
	 * Allows retries within 1 hour window after failure.
	 * After window expires, webhook is considered permanently failed for this status.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $event_type Event type (order_created, order_updated).
	 * @param string $status     Order status (created, confirmed, fulfilled, etc.).
	 * @return bool True if should retry, false otherwise.
	 */
	private function should_retry_failed_webhook( int $order_id, string $event_type, string $status ): bool {
		if ( ! $order_id || ! $event_type || ! $status ) {
			return false;
		}

		$cache_key   = sprintf( 'carticy_webhook_status_%d_%s_%s', $order_id, $event_type, $status );
		$status_data = get_transient( $cache_key );

		// No status data or no timestamp - allow retry.
		if ( ! is_array( $status_data ) || ! isset( $status_data['timestamp'] ) ) {
			return true;
		}

		// Retry if failed less than 1 hour ago.
		return ( time() - $status_data['timestamp'] ) < HOUR_IN_SECONDS;
	}

	/**
	 * Get public order URL for OpenAI webhooks
	 *
	 * Per ACP spec, permalink_url should allow customers to access order details
	 * with "at most their email address" - must be publicly accessible.
	 *
	 * WooCommerce provides two URL types:
	 * - get_view_order_url(): Requires full account login (NOT spec-compliant)
	 * - get_checkout_order_received_url(): Publicly accessible with order key (CORRECT)
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return string Public order URL.
	 */
	private function get_public_order_url( \WC_Order $order ): string {
		// Use order received URL which is publicly accessible with order key.
		// Format: /checkout/order-received/{order_id}/?key={order_key}
		// This allows customers to view order without requiring account login.
		return $order->get_checkout_order_received_url();
	}
}
