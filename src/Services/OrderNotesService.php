<?php
/**
 * Order Notes Service
 *
 * Manages order notes for ChatGPT orders
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OrderNotesService
 *
 * Adds detailed order notes for ChatGPT checkout orders
 */
final class OrderNotesService {

	/**
	 * Add order created note
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $session_id Checkout session ID.
	 * @param array     $metadata Additional metadata.
	 */
	public function add_order_created_note( \WC_Order $order, string $session_id, array $metadata = array() ): void {
		$note = sprintf(
			'🤖 Order created via ChatGPT Instant Checkout%s',
			PHP_EOL
		);

		if ( $session_id ) {
			$note .= sprintf( 'Session ID: %s%s', $session_id, PHP_EOL );
		}

		if ( ! empty( $metadata['user_id'] ) ) {
			$note .= sprintf( 'ChatGPT User ID: %s%s', $metadata['user_id'], PHP_EOL );
		}

		if ( ! empty( $metadata['conversation_id'] ) ) {
			$note .= sprintf( 'Conversation ID: %s%s', $metadata['conversation_id'], PHP_EOL );
		}

		$order->add_order_note( $note );
	}

	/**
	 * Add payment processing note
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $payment_method Payment method used.
	 * @param array     $payment_details Payment details.
	 */
	public function add_payment_processing_note(
		\WC_Order $order,
		string $payment_method,
		array $payment_details = array()
	): void {
		$note = sprintf(
			'💳 Payment processing via %s%s',
			$payment_method,
			PHP_EOL
		);

		if ( ! empty( $payment_details['transaction_id'] ) ) {
			$note .= sprintf( 'Transaction ID: %s%s', $payment_details['transaction_id'], PHP_EOL );
		}

		if ( ! empty( $payment_details['payment_token'] ) ) {
			// Show only last 4 chars of token for security.
			$token_display = '****' . substr( $payment_details['payment_token'], -4 );
			$note         .= sprintf( 'Payment Token: %s%s', $token_display, PHP_EOL );
		}

		if ( ! empty( $payment_details['amount'] ) ) {
			$note .= sprintf(
				'Amount: %s %s%s',
				$payment_details['amount'],
				$payment_details['currency'] ?? $order->get_currency(),
				PHP_EOL
			);
		}

		$order->add_order_note( $note );
	}

	/**
	 * Add payment success note
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $payment_result Payment result data.
	 */
	public function add_payment_success_note( \WC_Order $order, array $payment_result = array() ): void {
		$note = sprintf(
			'✅ Payment successful via ChatGPT Instant Checkout%s',
			PHP_EOL
		);

		if ( ! empty( $payment_result['charge_id'] ) ) {
			$note .= sprintf( 'Charge ID: %s%s', $payment_result['charge_id'], PHP_EOL );
		}

		if ( ! empty( $payment_result['receipt_url'] ) ) {
			$note .= sprintf( 'Receipt: %s%s', $payment_result['receipt_url'], PHP_EOL );
		}

		$order->add_order_note( $note );
	}

	/**
	 * Add payment failure note
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $error_message Error message.
	 * @param string    $error_code Error code.
	 */
	public function add_payment_failure_note(
		\WC_Order $order,
		string $error_message,
		string $error_code = ''
	): void {
		$note = sprintf(
			'❌ Payment failed via ChatGPT Instant Checkout%s',
			PHP_EOL
		);

		if ( $error_code ) {
			$note .= sprintf( 'Error Code: %s%s', $error_code, PHP_EOL );
		}

		$note .= sprintf( 'Error Message: %s', $error_message );

		$order->add_order_note( $note );
	}

	/**
	 * Add shipping calculated note
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $shipping_method Selected shipping method.
	 */
	public function add_shipping_calculated_note( \WC_Order $order, array $shipping_method ): void {
		$note = sprintf(
			'📦 Shipping calculated via ChatGPT Checkout%s',
			PHP_EOL
		);

		if ( ! empty( $shipping_method['label'] ) ) {
			$note .= sprintf( 'Method: %s%s', $shipping_method['label'], PHP_EOL );
		}

		if ( ! empty( $shipping_method['amount']['value'] ) ) {
			$note .= sprintf(
				'Cost: %s %s%s',
				$shipping_method['amount']['value'],
				$shipping_method['amount']['currency'] ?? $order->get_currency(),
				PHP_EOL
			);
		}

		if ( ! empty( $shipping_method['estimated_delivery'] ) ) {
			$note .= sprintf( 'Estimated Delivery: %s', $shipping_method['estimated_delivery'] );
		}

		$order->add_order_note( $note );
	}

	/**
	 * Add webhook received note
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $event_type Webhook event type.
	 * @param array     $webhook_data Webhook data.
	 */
	public function add_webhook_received_note(
		\WC_Order $order,
		string $event_type,
		array $webhook_data = array()
	): void {
		$note = sprintf(
			'🔔 Webhook received from ChatGPT: %s%s',
			$event_type,
			PHP_EOL
		);

		if ( ! empty( $webhook_data['timestamp'] ) ) {
			$note .= sprintf(
				'Timestamp: %s%s',
				gmdate( 'Y-m-d H:i:s', $webhook_data['timestamp'] ),
				PHP_EOL
			);
		}

		if ( ! empty( $webhook_data['event_id'] ) ) {
			$note .= sprintf( 'Event ID: %s', $webhook_data['event_id'] );
		}

		$order->add_order_note( $note );
	}

	/**
	 * Add refund initiated note
	 *
	 * @param \WC_Order $order Order object.
	 * @param float     $refund_amount Refund amount.
	 * @param string    $reason Refund reason.
	 */
	public function add_refund_initiated_note(
		\WC_Order $order,
		float $refund_amount,
		string $reason = ''
	): void {
		$note = sprintf(
			'💰 Refund initiated via ChatGPT Checkout%s',
			PHP_EOL
		);

		$note .= sprintf(
			'Amount: %s %s%s',
			wc_price( $refund_amount ),
			$order->get_currency(),
			PHP_EOL
		);

		if ( $reason ) {
			$note .= sprintf( 'Reason: %s', $reason );
		}

		$order->add_order_note( $note );
	}

	/**
	 * Add order status change note
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $old_status Old status.
	 * @param string    $new_status New status.
	 * @param string    $source Source of status change.
	 */
	public function add_status_change_note(
		\WC_Order $order,
		string $old_status,
		string $new_status,
		string $source = 'ChatGPT'
	): void {
		$note = sprintf(
			'🔄 Order status changed via %s: %s → %s',
			$source,
			ucfirst( $old_status ),
			ucfirst( $new_status )
		);

		$order->add_order_note( $note );
	}

	/**
	 * Add 3D Secure authentication note
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $redirect_url 3DS redirect URL.
	 */
	public function add_3ds_authentication_note( \WC_Order $order, string $redirect_url ): void {
		$note = sprintf(
			'🔒 3D Secure authentication required%s',
			PHP_EOL
		);

		$note .= sprintf( 'Redirect URL: %s', $redirect_url );

		$order->add_order_note( $note );
	}

	/**
	 * Add session validation note
	 *
	 * @param \WC_Order $order Order object.
	 * @param bool      $is_valid Whether session is valid.
	 * @param array     $validation_details Validation details.
	 */
	public function add_session_validation_note(
		\WC_Order $order,
		bool $is_valid,
		array $validation_details = array()
	): void {
		$status = $is_valid ? '✅ Valid' : '❌ Invalid';
		$note   = sprintf(
			'Session validation: %s%s',
			$status,
			PHP_EOL
		);

		if ( ! empty( $validation_details['session_id'] ) ) {
			$note .= sprintf( 'Session ID: %s%s', $validation_details['session_id'], PHP_EOL );
		}

		if ( ! empty( $validation_details['expiry'] ) ) {
			$note .= sprintf(
				'Expires: %s%s',
				gmdate( 'Y-m-d H:i:s', $validation_details['expiry'] ),
				PHP_EOL
			);
		}

		if ( ! empty( $validation_details['error'] ) ) {
			$note .= sprintf( 'Error: %s', $validation_details['error'] );
		}

		$order->add_order_note( $note );
	}

	/**
	 * Add inventory update note
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $inventory_changes Inventory changes.
	 */
	public function add_inventory_update_note( \WC_Order $order, array $inventory_changes ): void {
		$note = sprintf(
			'📊 Inventory updated via ChatGPT Checkout%s',
			PHP_EOL
		);

		foreach ( $inventory_changes as $product_id => $change ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$note .= sprintf(
					'- %s: %d units reserved%s',
					$product->get_name(),
					$change['quantity'] ?? 0,
					PHP_EOL
				);
			}
		}

		$order->add_order_note( $note );
	}

	/**
	 * Add test mode note
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function add_test_mode_note( \WC_Order $order ): void {
		$note = '🧪 This order was created in TEST MODE via ChatGPT Instant Checkout';
		$order->add_order_note( $note );
	}

	/**
	 * Add customer note from ChatGPT
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $customer_note Customer note.
	 */
	public function add_customer_note( \WC_Order $order, string $customer_note ): void {
		$note = sprintf(
			'💬 Customer note from ChatGPT:%s%s',
			PHP_EOL,
			$customer_note
		);

		$order->add_order_note( $note, 1 ); // 1 = customer note.
	}

	/**
	 * Check if order is from ChatGPT
	 *
	 * @param \WC_Order $order Order object.
	 * @return bool True if order is from ChatGPT.
	 */
	public function is_chatgpt_order( \WC_Order $order ): bool {
		return 'yes' === $order->get_meta( '_chatgpt_checkout' );
	}

	/**
	 * Get ChatGPT session ID from order
	 *
	 * @param \WC_Order $order Order object.
	 * @return string Session ID or empty string.
	 */
	public function get_session_id( \WC_Order $order ): string {
		$session_id = $order->get_meta( '_chatgpt_session_id' );
		return $session_id ? $session_id : '';
	}

	/**
	 * Add order note with icon
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $icon Icon emoji.
	 * @param string    $message Note message.
	 * @param bool      $is_customer_note Whether this is a customer note.
	 */
	public function add_note_with_icon(
		\WC_Order $order,
		string $icon,
		string $message,
		bool $is_customer_note = false
	): void {
		$note = sprintf( '%s %s', $icon, $message );
		$order->add_order_note( $note, $is_customer_note ? 1 : 0 );
	}
}
