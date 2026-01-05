<?php
/**
 * Mock Simulator Service
 *
 * Simulates OpenAI API requests for testing purposes.
 *
 * @package Carticy\AiCheckout\Services
 */

namespace Carticy\AiCheckout\Services;

/**
 * Mock Simulator class
 *
 * Generates test checkout sessions and simulates OpenAI API interactions.
 *
 * @since 1.0.0
 */
final class MockSimulator {

	/**
	 * Test scenarios
	 */
	private const SCENARIOS = array(
		'valid_order'      => 'Valid Order',
		'invalid_sku'      => 'Invalid SKU',
		'out_of_stock'     => 'Out of Stock',
		'payment_failed'   => 'Payment Failed',
		'3ds_required'     => '3D Secure Required',
		'missing_address'  => 'Missing Shipping Address',
		'invalid_quantity' => 'Invalid Quantity',
	);

	/**
	 * Get available test scenarios
	 *
	 * @return array<string, string> Scenario ID => Label.
	 */
	public function get_scenarios(): array {
		return self::SCENARIOS;
	}

	/**
	 * Generate mock create session request
	 *
	 * @param string $scenario Test scenario.
	 * @return array Mock request data.
	 */
	public function generate_create_session_request( string $scenario = 'valid_order' ): array {
		$base_request = array(
			'items' => $this->get_test_items( $scenario ),
		);

		if ( 'missing_address' !== $scenario ) {
			$base_request['shipping_address'] = $this->get_test_shipping_address();
		}

		return $base_request;
	}

	/**
	 * Generate mock update session request
	 *
	 * @param string $scenario Test scenario.
	 * @return array Mock request data.
	 */
	public function generate_update_session_request( string $scenario = 'valid_order' ): array {
		return array(
			'items'            => $this->get_test_items( $scenario ),
			'shipping_address' => $this->get_test_shipping_address(),
			'billing_address'  => $this->get_test_billing_address(),
		);
	}

	/**
	 * Generate mock complete session request
	 *
	 * @param string $scenario Test scenario.
	 * @return array Mock request data.
	 */
	public function generate_complete_session_request( string $scenario = 'valid_order' ): array {
		return array(
			'payment' => array(
				'token'    => $this->generate_mock_shared_payment_token( $scenario ),
				'provider' => 'stripe',
			),
			'buyer'   => $this->get_test_buyer_info(),
		);
	}

	/**
	 * Generate mock SharedPaymentToken
	 *
	 * Generates fake SharedPaymentToken for testing purposes.
	 * In production, OpenAI sends real tokens - this is only for plugin testing.
	 *
	 * @param string $scenario Test scenario (unused, kept for API compatibility).
	 * @return string Fake SharedPaymentToken with spt_test_ prefix.
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Kept for API compatibility.
	 */
	public function generate_mock_shared_payment_token( string $scenario = 'valid_order' ): string {
		// Generate fake token for testing.
		// Format: spt_test_[24 random characters]
		// This allows testing the complete checkout flow without real Stripe tokens.
		// OpenAI will provide real spt_xxxxx tokens in production.
		return 'spt_test_' . wp_generate_password( 24, false );
	}

	/**
	 * Get test items based on scenario
	 *
	 * @param string $scenario Test scenario.
	 * @return array Test items.
	 */
	private function get_test_items( string $scenario ): array {
		// Get a valid test product SKU from the store.
		$test_sku = $this->get_valid_test_product_sku();

		$items = array(
			array(
				'sku'      => $test_sku,
				'quantity' => 2,
			),
		);

		switch ( $scenario ) {
			case 'invalid_sku':
				$items[0]['sku'] = 'INVALID-SKU-999';
				break;

			case 'invalid_quantity':
				$items[0]['quantity'] = -5;
				break;
		}

		return $items;
	}

	/**
	 * Get a valid test product SKU
	 *
	 * Retrieves the first available product from the ChatGPT feed (enabled products only).
	 * Only selects products that match product feed criteria:
	 * - ChatGPT enabled (_carticy_ai_checkout_enabled = 'yes')
	 * - Price > 0 (ACP requirement)
	 * - In stock and purchasable
	 *
	 * Uses caching to avoid repeated queries.
	 *
	 * @return string Product SKU or fallback SKU.
	 */
	private function get_valid_test_product_sku(): string {
		// Check cache first.
		$cached_sku = get_transient( 'carticy_ai_checkout_test_product_sku' );
		if ( $cached_sku ) {
			// Verify product still exists and meets all criteria.
			$product_id = wc_get_product_id_by_sku( $cached_sku );
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				$enabled = get_post_meta( $product_id, '_carticy_ai_checkout_enabled', true );
				if ( $product && $product->is_in_stock() && $product->is_purchasable() && 'yes' === $enabled && (float) $product->get_price() > 0 ) {
					return $cached_sku;
				}
			}
			// Cache invalid - delete it.
			delete_transient( 'carticy_ai_checkout_test_product_sku' );
		}

		// Query for valid products from the feed.
		// Use WP_Query with meta_query to match ProductsListTable criteria.
		$query_args = array(
			'post_type'      => 'product',
			'posts_per_page' => 5,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_carticy_ai_checkout_enabled',
					'value'   => 'yes',
					'compare' => '=',
				),
				array(
					'key'     => '_price',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		);

		$query       = new \WP_Query( $query_args );
		$product_ids = $query->posts;

		if ( empty( $product_ids ) ) {
			// No products available in feed - return fallback that will trigger proper error.
			return 'NO-PRODUCTS-AVAILABLE';
		}

		// Find first product with stock and SKU.
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_in_stock() || ! $product->is_purchasable() ) {
				continue;
			}

			$sku = $product->get_sku();
			if ( empty( $sku ) ) {
				$sku = 'PRODUCT-' . $product_id;
			}

			// Cache for 1 hour.
			set_transient( 'carticy_ai_checkout_test_product_sku', $sku, HOUR_IN_SECONDS );

			return $sku;
		}

		// Fallback if no suitable product found.
		return 'NO-PRODUCTS-AVAILABLE';
	}

	/**
	 * Get an out-of-stock product SKU
	 *
	 * @return string|null Product SKU or null if none found.
	 */
	private function get_out_of_stock_product_sku(): ?string {
		$products = wc_get_products(
			array(
				'limit'        => 1,
				'status'       => 'publish',
				'stock_status' => 'outofstock',
				'return'       => 'ids',
			)
		);

		if ( empty( $products ) ) {
			return null;
		}

		$product = wc_get_product( $products[0] );
		if ( ! $product ) {
			return null;
		}

		$sku = $product->get_sku();
		return $sku ? $sku : 'PRODUCT-' . $product->get_id();
	}

	/**
	 * Get test shipping address
	 *
	 * @return array Shipping address data.
	 */
	private function get_test_shipping_address(): array {
		return array(
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'company'    => 'Test Company',
			'address_1'  => '123 Test Street',
			'address_2'  => 'Apt 4B',
			'city'       => 'San Francisco',
			'state'      => 'CA',
			'postcode'   => '94102',
			'country'    => 'US',
		);
	}

	/**
	 * Get test billing address
	 *
	 * @return array Billing address data.
	 */
	private function get_test_billing_address(): array {
		return array(
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'company'    => 'Test Company',
			'address_1'  => '123 Test Street',
			'address_2'  => 'Apt 4B',
			'city'       => 'San Francisco',
			'state'      => 'CA',
			'postcode'   => '94102',
			'country'    => 'US',
			'email'      => 'test@example.com',
			'phone'      => '+1-415-555-0123',
		);
	}

	/**
	 * Get test buyer info
	 *
	 * @return array Buyer information.
	 */
	private function get_test_buyer_info(): array {
		return array(
			'email'      => 'test@example.com',
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'phone'      => '+1-415-555-0123',
		);
	}

	/**
	 * Simulate API request
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 * @param array  $headers  Request headers.
	 * @return array Response data.
	 */
	public function simulate_request( string $endpoint, string $method, array $data, array $headers = array() ): array {
		$base_url = rest_url( 'carticy-ai-checkout/v1/' );
		$url      = $base_url . ltrim( $endpoint, '/' );

		// Add default headers.
		$default_headers = array(
			'Authorization' => 'Bearer ' . get_option( 'carticy_ai_checkout_api_key', 'test_api_key' ),
			'Content-Type'  => 'application/json',
			'API-Version'   => '2024-01-01',
		);

		$headers = array_merge( $default_headers, $headers );

		// Make request.
		// Increased timeout to 60 seconds for slow Stripe processing and order creation.
		// Frontend AJAX also has 60-second timeout to match.
		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => wp_json_encode( $data ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		return array(
			'success'     => $status_code >= 200 && $status_code < 300,
			'status_code' => $status_code,
			'body'        => $decoded ? $decoded : $body,
			'headers'     => wp_remote_retrieve_headers( $response )->getAll(),
		);
	}

	/**
	 * Run test scenario
	 *
	 * @param string $scenario Test scenario.
	 * @return array Test results.
	 */
	public function run_test_scenario( string $scenario ): array {
		$results = array();

		// Step 1: Create session.
		$create_request = $this->generate_create_session_request( $scenario );
		$create_result  = $this->simulate_request( 'checkout_sessions', 'POST', $create_request );

		$results['create_session'] = array(
			'request'  => $create_request,
			'response' => $create_result,
		);

		if ( ! $create_result['success'] ) {
			return $results;
		}

		$session_id = $create_result['body']['id'] ?? null;
		if ( ! $session_id ) {
			return $results;
		}

		// Step 2: Update session (optional).
		if ( 'missing_address' !== $scenario ) {
			$update_request = $this->generate_update_session_request( $scenario );
			$update_result  = $this->simulate_request(
				"checkout_sessions/{$session_id}",
				'POST',
				$update_request
			);

			$results['update_session'] = array(
				'request'  => $update_request,
				'response' => $update_result,
			);
		}

		// Step 3: Complete session (if payment scenario).
		if ( ! in_array( $scenario, array( 'invalid_sku', 'out_of_stock', 'missing_address', 'invalid_quantity' ), true ) ) {
			$complete_request = $this->generate_complete_session_request( $scenario );
			$complete_result  = $this->simulate_request(
				"checkout_sessions/{$session_id}/complete",
				'POST',
				$complete_request
			);

			$results['complete_session'] = array(
				'request'  => $complete_request,
				'response' => $complete_result,
			);
		}

		return $results;
	}

	/**
	 * Get test products from WooCommerce
	 *
	 * Returns only products that are enabled for ChatGPT and have stock.
	 *
	 * @param int $limit Number of products to retrieve.
	 * @return array Product data for testing.
	 */
	public function get_test_products( int $limit = 5 ): array {
		$all_products = wc_get_products(
			array(
				'limit'        => -1,
				'status'       => 'publish',
				'stock_status' => 'instock',
			)
		);

		$test_products = array();
		$count         = 0;

		foreach ( $all_products as $product ) {
			// Only include products enabled for ChatGPT.
			$enabled = get_post_meta( $product->get_id(), '_carticy_ai_checkout_enabled', true );
			if ( 'yes' !== $enabled ) {
				continue;
			}

			// Skip if not purchasable.
			if ( ! $product->is_purchasable() ) {
				continue;
			}

			$product_sku     = $product->get_sku();
			$test_products[] = array(
				'sku'   => $product_sku ? $product_sku : 'PRODUCT-' . $product->get_id(),
				'name'  => $product->get_name(),
				'price' => $product->get_price(),
				'stock' => $product->get_stock_status(),
			);

			++$count;
			if ( $count >= $limit ) {
				break;
			}
		}

		return $test_products;
	}

	/**
	 * Validate mock data format
	 *
	 * @param array  $data Mock data.
	 * @param string $type Data type (create_session, update_session, complete_session).
	 * @return array{valid: bool, errors: array}
	 */
	public function validate_mock_data( array $data, string $type ): array {
		$errors = array();

		switch ( $type ) {
			case 'create_session':
				if ( empty( $data['items'] ) ) {
					$errors[] = 'Missing required field: items';
				}
				break;

			case 'update_session':
				if ( empty( $data['items'] ) && empty( $data['shipping_address'] ) && empty( $data['billing_address'] ) ) {
					$errors[] = 'At least one update field required';
				}
				break;

			case 'complete_session':
				if ( empty( $data['payment']['token'] ) ) {
					$errors[] = 'Missing required field: payment.token';
				}
				break;
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}
}
