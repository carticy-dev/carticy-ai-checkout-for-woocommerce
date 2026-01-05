<?php
/**
 * Payment Processor Adapter Interface
 *
 * Protocol-agnostic payment processor interface allowing clean support
 * for multiple payment protocols (ACP/Stripe, AP2/Google, future PSPs).
 *
 * @package Carticy\AiCheckout\Interfaces
 */

namespace Carticy\AiCheckout\Interfaces;

use WP_Error;

/**
 * Payment processor adapter interface
 */
interface PaymentProcessorAdapter {
	/**
	 * Process payment for checkout session
	 *
	 * @param array<string, mixed> $checkout_session Session data from SessionService.
	 * @param array<string, mixed> $payment_data Payment data from complete request.
	 * @return array<string, mixed>|WP_Error Payment result with order_id and status, or error.
	 */
	public function process_payment( array $checkout_session, array $payment_data ): array|WP_Error;
}
