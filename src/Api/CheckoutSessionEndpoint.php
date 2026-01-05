<?php
/**
 * Checkout Session REST API Endpoint
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Api;

use Carticy\AiCheckout\Services\SessionService;
use Carticy\AiCheckout\Services\AuthenticationService;
use Carticy\AiCheckout\Services\StripePaymentAdapter;
use Carticy\AiCheckout\Services\IdempotencyService;
use Carticy\AiCheckout\Services\LoggingService;
use Carticy\AiCheckout\Services\ErrorLogService;
use Carticy\AiCheckout\Services\PerformanceMetrics;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WC_Shipping_Zones;
use WC_Tax;

/**
 * Handles checkout session REST API endpoints
 */
final class CheckoutSessionEndpoint {

	/**
	 * API namespace
	 */
	private const NAMESPACE = 'carticy-ai-checkout/v1';

	/**
	 * Session service
	 *
	 * @var SessionService
	 */
	private SessionService $session_service;

	/**
	 * Authentication service
	 *
	 * @var AuthenticationService
	 */
	private AuthenticationService $auth_service;

	/**
	 * Payment adapter
	 *
	 * @var StripePaymentAdapter
	 */
	private StripePaymentAdapter $payment_adapter;

	/**
	 * Idempotency service
	 *
	 * @var IdempotencyService
	 */
	private IdempotencyService $idempotency_service;

	/**
	 * Logging service
	 *
	 * @var LoggingService
	 */
	private LoggingService $logging_service;

	/**
	 * Error log service
	 *
	 * @var ErrorLogService
	 */
	private ErrorLogService $error_log_service;

	/**
	 * Performance metrics
	 *
	 * @var PerformanceMetrics
	 */
	private PerformanceMetrics $performance_metrics;

	/**
	 * Constructor
	 *
	 * @param SessionService        $session_service Session service instance.
	 * @param AuthenticationService $auth_service Authentication service instance.
	 * @param StripePaymentAdapter  $payment_adapter Payment adapter instance.
	 * @param IdempotencyService    $idempotency_service Idempotency service instance.
	 * @param LoggingService        $logging_service Logging service instance.
	 * @param ErrorLogService       $error_log_service Error log service instance.
	 * @param PerformanceMetrics    $performance_metrics Performance metrics instance.
	 */
	public function __construct( SessionService $session_service, AuthenticationService $auth_service, StripePaymentAdapter $payment_adapter, IdempotencyService $idempotency_service, LoggingService $logging_service, ErrorLogService $error_log_service, PerformanceMetrics $performance_metrics ) {
		$this->session_service     = $session_service;
		$this->auth_service        = $auth_service;
		$this->payment_adapter     = $payment_adapter;
		$this->idempotency_service = $idempotency_service;
		$this->logging_service     = $logging_service;
		$this->error_log_service   = $error_log_service;
		$this->performance_metrics = $performance_metrics;
		$this->register_routes();
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Create checkout session.
		register_rest_route(
			self::NAMESPACE,
			'/checkout_sessions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_session' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_create_session_args(),
			)
		);

		// Get checkout session.
		register_rest_route(
			self::NAMESPACE,
			'/checkout_sessions/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_session' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Update checkout session.
		register_rest_route(
			self::NAMESPACE,
			'/checkout_sessions/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_session' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_update_session_args(),
			)
		);

		// Cancel checkout session.
		register_rest_route(
			self::NAMESPACE,
			'/checkout_sessions/(?P<id>[a-zA-Z0-9_-]+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_session' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Complete checkout session.
		register_rest_route(
			self::NAMESPACE,
			'/checkout_sessions/(?P<id>[a-zA-Z0-9_-]+)/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete_session' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_complete_session_args(),
			)
		);
	}

	/**
	 * Get arguments for create session endpoint
	 *
	 * @return array<string, array<string, mixed>> Arguments schema.
	 */
	private function get_create_session_args(): array {
		return array(
			'items'            => array(
				'required' => true,
				'type'     => 'array',
			),
			'shipping_address' => array(
				'required' => false,
				'type'     => 'object',
			),
			'billing_address'  => array(
				'required' => false,
				'type'     => 'object',
			),
		);
	}

	/**
	 * Permission callback for protected endpoints
	 *
	 * Performs comprehensive security validation including:
	 * - License validation
	 * - SSL/HTTPS enforcement
	 * - IP allowlisting
	 * - Bearer token authentication
	 * - Rate limiting
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error if not.
	 */
	public function check_permission( WP_REST_Request $request ): bool|WP_Error {
		// Determine endpoint identifier for rate limiting.
		$endpoint = $this->get_endpoint_identifier( $request );

		// Perform comprehensive security validation.
		return $this->auth_service->validate_request_security( $request, $endpoint );
	}

	/**
	 * Get endpoint identifier for rate limiting
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return string Endpoint identifier.
	 */
	private function get_endpoint_identifier( WP_REST_Request $request ): string {
		$route  = $request->get_route();
		$method = $request->get_method();

		// Map routes to endpoint identifiers.
		if ( str_contains( $route, '/complete' ) ) {
			return 'complete_session';
		} elseif ( str_contains( $route, '/cancel' ) ) {
			return 'cancel_session';
		} elseif ( str_ends_with( $route, '/checkout_sessions' ) && 'POST' === $method ) {
			return 'create_session';
		} elseif ( preg_match( '/\/checkout_sessions\/[^\/]+$/', $route ) && 'POST' === $method ) {
			return 'update_session';
		} elseif ( preg_match( '/\/checkout_sessions\/[^\/]+$/', $route ) && 'GET' === $method ) {
			return 'get_session';
		}

		return 'unknown';
	}

	/**
	 * Add rate limit headers to response
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param WP_REST_Request  $request Request object.
	 * @param string           $endpoint Endpoint identifier.
	 * @return WP_REST_Response Modified response with headers.
	 */
	private function add_rate_limit_headers( WP_REST_Response $response, WP_REST_Request $request, string $endpoint ): WP_REST_Response {
		$headers = $this->auth_service->get_rate_limit_headers( $endpoint );

		foreach ( $headers as $key => $value ) {
			$response->header( $key, $value );
		}

		return $response;
	}

	/**
	 * Create checkout session
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$start_time   = microtime( true );
		$start_memory = memory_get_peak_usage();
		global $wpdb;
		$start_queries = $wpdb->num_queries;

		// Check idempotency.
		$idempotency_result = $this->idempotency_service->check_idempotency( $request, 'create_session' );

		if ( null !== $idempotency_result ) {
			if ( isset( $idempotency_result['error'] ) ) {
				// Same key, different params - return 409 conflict.
				return new WP_Error(
					$idempotency_result['error'],
					'Idempotency key reused with different parameters',
					array( 'status' => $idempotency_result['status'] )
				);
			}

			// Same key, same params - return cached response.
			return $this->add_rate_limit_headers(
				rest_ensure_response( $idempotency_result['cached_response'] ),
				$request,
				'create_session'
			);
		}

		$items            = $request->get_param( 'items' ) ? $request->get_param( 'items' ) : array();
		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		// Validate items.
		$validation_error = $this->validate_items( $items );
		if ( is_wp_error( $validation_error ) ) {
			return $validation_error;
		}

		// Parse and validate products.
		$line_items = $this->parse_line_items( $items );
		if ( is_wp_error( $line_items ) ) {
			return $line_items;
		}

		// Calculate totals.
		$subtotal = $this->calculate_subtotal( $line_items );

		// Calculate shipping options.
		$shipping_options = array();
		if ( ! empty( $shipping_address ) ) {
			$shipping_options = $this->calculate_shipping( $line_items, $shipping_address );
		}

		// Calculate taxes.
		$tax_total = 0;
		if ( ! empty( $shipping_address ) || ! empty( $billing_address ) ) {
			$tax_address = ! empty( $shipping_address ) ? $shipping_address : $billing_address;
			$tax_total   = $this->calculate_taxes( $line_items, $tax_address );
		}

		// Calculate total (subtotal + shipping + tax).
		$shipping_total = ! empty( $shipping_options ) ? $shipping_options[0]['amount']['value'] : 0;
		$total          = $subtotal + $shipping_total + $tax_total;

		// Generate session ID.
		$session_id = $this->generate_session_id();

		// Prepare session data.
		$session_data = array(
			'items'            => $line_items,
			'subtotal'         => $subtotal,
			'shipping_options' => $shipping_options,
			'tax_total'        => $tax_total,
			'total'            => $total,
			'currency'         => get_woocommerce_currency(),
			'shipping_address' => $shipping_address,
			'billing_address'  => $billing_address,
		);

		// Store session.
		$stored = $this->session_service->create( $session_id, $session_data );

		if ( ! $stored ) {
			return new WP_Error(
				'session_creation_failed',
				'Failed to create checkout session',
				array( 'status' => 500 )
			);
		}

		// Return ACP-compliant response.
		$response = $this->format_session_response( $session_id, $session_data );

		// Store for idempotency.
		$this->idempotency_service->store_idempotent_response( $request, 'create_session', $response );

		// Log request performance and details.
		$duration    = microtime( true ) - $start_time;
		$memory_peak = memory_get_peak_usage() - $start_memory;
		$db_queries  = $wpdb->num_queries - $start_queries;

		$this->logging_service->log_api_request(
			'/checkout_sessions',
			'POST',
			$request->get_json_params(),
			$response,
			201,
			$duration
		);

		$this->performance_metrics->track_api_request(
			'/checkout_sessions',
			$duration,
			$memory_peak,
			$db_queries
		);

		// Add rate limit headers and return.
		return $this->add_rate_limit_headers(
			rest_ensure_response( $response ),
			$request,
			'create_session'
		);
	}

	/**
	 * Get checkout session
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session_id = $request->get_param( 'id' );

		// Retrieve session.
		$session_data = $this->session_service->get( $session_id );

		if ( null === $session_data ) {
			return new WP_Error(
				'session_not_found',
				'Checkout session not found',
				array( 'status' => 404 )
			);
		}

		// Return ACP-compliant response.
		$response = $this->format_session_response( $session_id, $session_data );

		// Add rate limit headers and return.
		return $this->add_rate_limit_headers(
			rest_ensure_response( $response ),
			$request,
			'get_session'
		);
	}

	/**
	 * Update checkout session
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session_id = $request->get_param( 'id' );

		// Retrieve existing session.
		$session_data = $this->session_service->get( $session_id );

		if ( null === $session_data ) {
			return new WP_Error(
				'session_not_found',
				'Checkout session not found',
				array( 'status' => 404 )
			);
		}

		// Check if session is still active.
		if ( isset( $session_data['status'] ) && 'active' !== $session_data['status'] ) {
			return new WP_Error(
				'session_not_active',
				'Checkout session is not active',
				array( 'status' => 400 )
			);
		}

		$needs_recalculation = false;

		// Handle items update (quantity changes or item removal).
		if ( $request->has_param( 'items' ) ) {
			$new_items = $request->get_param( 'items' );

			// Validate and parse new items.
			$validation_error = $this->validate_items( $new_items );
			if ( is_wp_error( $validation_error ) ) {
				return $validation_error;
			}

			$line_items = $this->parse_line_items( $new_items );
			if ( is_wp_error( $line_items ) ) {
				return $line_items;
			}

			$session_data['items']    = $line_items;
			$session_data['subtotal'] = $this->calculate_subtotal( $line_items );
			$needs_recalculation      = true;
		}

		// Handle shipping address update.
		if ( $request->has_param( 'shipping_address' ) ) {
			$session_data['shipping_address'] = $request->get_param( 'shipping_address' );
			$needs_recalculation              = true;
		}

		// Handle billing address update.
		if ( $request->has_param( 'billing_address' ) ) {
			$session_data['billing_address'] = $request->get_param( 'billing_address' );
			$needs_recalculation             = true;
		}

		// Handle shipping method change.
		if ( $request->has_param( 'shipping_method' ) ) {
			$shipping_method_id = sanitize_text_field( $request->get_param( 'shipping_method' ) );

			// Validate shipping method exists in available options.
			$shipping_options = $session_data['shipping_options'] ?? array();
			$method_found     = false;

			foreach ( $shipping_options as $option ) {
				if ( $option['id'] === $shipping_method_id ) {
					$session_data['selected_shipping_method'] = $shipping_method_id;
					$method_found                             = true;
					$needs_recalculation                      = true;
					break;
				}
			}

			if ( ! $method_found ) {
				return new WP_Error(
					'invalid_shipping_method',
					'Invalid shipping method selected',
					array( 'status' => 400 )
				);
			}
		}

		// Handle coupon application.
		if ( $request->has_param( 'coupon_code' ) ) {
			$coupon_code = sanitize_text_field( $request->get_param( 'coupon_code' ) );

			// Apply coupon.
			$coupon_result = $this->apply_coupon( $coupon_code, $session_data );

			if ( is_wp_error( $coupon_result ) ) {
				return $coupon_result;
			}

			$session_data        = $coupon_result;
			$needs_recalculation = true;
		}

		// Handle coupon removal.
		if ( $request->has_param( 'remove_coupon' ) ) {
			unset( $session_data['coupon_code'] );
			unset( $session_data['coupon_discount'] );
			$needs_recalculation = true;
		}

		// Recalculate if needed.
		if ( $needs_recalculation ) {
			$session_data = $this->recalculate_session( $session_data );
		}

		// Update session in storage.
		$updated = $this->session_service->update( $session_id, $session_data );

		if ( ! $updated ) {
			return new WP_Error(
				'session_update_failed',
				'Failed to update checkout session',
				array( 'status' => 500 )
			);
		}

		// Return updated session.
		$response = $this->format_session_response( $session_id, $session_data );

		return rest_ensure_response( $response );
	}

	/**
	 * Cancel checkout session
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function cancel_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session_id = $request->get_param( 'id' );

		// Retrieve session.
		$session_data = $this->session_service->get( $session_id );

		if ( null === $session_data ) {
			return new WP_Error(
				'session_not_found',
				'Checkout session not found',
				array( 'status' => 404 )
			);
		}

		// Update session status to cancelled.
		$session_data['status'] = 'cancelled';
		$this->session_service->update( $session_id, $session_data );

		// Delete session from storage.
		$deleted = $this->session_service->delete( $session_id );

		if ( ! $deleted ) {
			return new WP_Error(
				'session_cancellation_failed',
				'Failed to cancel checkout session',
				array( 'status' => 500 )
			);
		}

		// Return success response.
		return rest_ensure_response(
			array(
				'id'      => $session_id,
				'status'  => 'cancelled',
				'message' => 'Checkout session cancelled successfully',
			)
		);
	}

	/**
	 * Complete checkout session and process payment
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function complete_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session_id = $request->get_param( 'id' );

		// Retrieve session.
		$session_data = $this->session_service->get( $session_id );

		if ( null === $session_data ) {
			return new WP_Error(
				'session_not_found',
				'Checkout session not found',
				array( 'status' => 404 )
			);
		}

		// Check if session is still active.
		if ( isset( $session_data['status'] ) && 'active' !== $session_data['status'] ) {
			return new WP_Error(
				'session_not_active',
				'Checkout session is not active',
				array( 'status' => 400 )
			);
		}

		// Extract payment data from request.
		// Per OpenAI ACP spec, buyer information (email, phone, name) is sent in separate 'buyer' object.
		$buyer    = $request->get_param( 'buyer' ) ?? array();
		$billing  = $request->get_param( 'billing_address' ) ?? $session_data['billing_address'] ?? array();
		$shipping = $request->get_param( 'shipping_address' ) ?? $session_data['shipping_address'] ?? array();

		// Merge buyer info into billing address for WooCommerce/Stripe Gateway.
		// WC Stripe Gateway requires billing_email and billing_phone for payment processing.
		// Per OpenAI ACP spec: buyer contains first_name, email, phone_number (no last_name).
		if ( ! empty( $buyer ) ) {
			$billing['email']      = $buyer['email'] ?? $billing['email'] ?? '';
			$billing['phone']      = $buyer['phone_number'] ?? $buyer['phone'] ?? $billing['phone'] ?? '';
			$billing['first_name'] = $buyer['first_name'] ?? $billing['first_name'] ?? '';
			// Note: ACP buyer object does NOT include last_name - only first_name.
		}

		$payment_data = array(
			'payment'          => $request->get_param( 'payment' ) ?? array(),
			'billing_address'  => $billing,
			'shipping_address' => $shipping,
			'buyer'            => $buyer,
		);

		// Validate payment data.
		if ( empty( $payment_data['payment']['token'] ) ) {
			return new WP_Error(
				'missing_payment_token',
				'Payment token is required',
				array( 'status' => 400 )
			);
		}

		// Re-validate product availability before processing payment.
		// This prevents race conditions where stock becomes unavailable between session creation and completion.
		$stock_validation = $this->validate_session_stock( $session_data );
		if ( is_wp_error( $stock_validation ) ) {
			return $stock_validation;
		}

		// Add session ID to checkout session data for adapter.
		$session_data['session_id'] = $session_id;

		// Process payment through adapter.
		$payment_result = $this->payment_adapter->process_payment( $session_data, $payment_data );

		// Check for errors.
		if ( is_wp_error( $payment_result ) ) {
			// Update session status to failed for audit trail (removed after 7 days by cleanup cron).
			$this->session_service->update(
				$session_id,
				array(
					'status' => 'failed',
					'error'  => $payment_result->get_error_message(),
				)
			);

			// Map payment errors to user-friendly messages.
			return $this->map_payment_error( $payment_result );
		}

		// Check if 3D Secure authentication is required.
		if ( isset( $payment_result['status'] ) && 'requires_action' === $payment_result['status'] ) {
			return rest_ensure_response(
				array(
					'id'           => $session_id,
					'status'       => 'requires_action',
					'redirect_url' => $payment_result['redirect_url'],
					'order_id'     => $payment_result['order_id'],
				)
			);
		}

		// Store order_id and mark session as completed (per ACP spec, sessions remain accessible post-completion).
		$this->session_service->update(
			$session_id,
			array(
				'order_id' => $payment_result['order_id'],
				'status'   => 'completed',
			)
		);

		// Get order details.
		$order = wc_get_order( $payment_result['order_id'] );

		if ( ! $order ) {
			return new WP_Error(
				'order_not_found',
				'Order was created but could not be retrieved',
				array( 'status' => 500 )
			);
		}

		// Return success response with order details.
		return rest_ensure_response(
			array(
				'id'       => $session_id,
				'status'   => 'completed',
				'order_id' => $order->get_id(),
				'order'    => array(
					'id'           => $order->get_id(),
					'order_number' => $order->get_order_number(),
					'status'       => $order->get_status(),
					'total'        => array(
						'value'    => (float) $order->get_total(),
						'currency' => $order->get_currency(),
					),
					'created_at'   => $order->get_date_created()->date( 'c' ),
				),
			)
		);
	}

	/**
	 * Get arguments for update session endpoint
	 *
	 * @return array<string, array<string, mixed>> Arguments schema.
	 */
	private function get_update_session_args(): array {
		return array(
			'id'               => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'items'            => array(
				'required' => false,
				'type'     => 'array',
			),
			'shipping_address' => array(
				'required' => false,
				'type'     => 'object',
			),
			'billing_address'  => array(
				'required' => false,
				'type'     => 'object',
			),
			'shipping_method'  => array(
				'required' => false,
				'type'     => 'string',
			),
			'coupon_code'      => array(
				'required' => false,
				'type'     => 'string',
			),
			'remove_coupon'    => array(
				'required' => false,
				'type'     => 'boolean',
			),
		);
	}

	/**
	 * Recalculate session totals
	 *
	 * @param array<string, mixed> $session_data Session data.
	 * @return array<string, mixed> Recalculated session data.
	 */
	private function recalculate_session( array $session_data ): array {
		// Recalculate shipping if address is set.
		if ( ! empty( $session_data['shipping_address'] ) ) {
			$session_data['shipping_options'] = $this->calculate_shipping(
				$session_data['items'],
				$session_data['shipping_address']
			);
		}

		// Recalculate taxes.
		$tax_total = 0;
		if ( ! empty( $session_data['shipping_address'] ) || ! empty( $session_data['billing_address'] ) ) {
			$tax_address = ! empty( $session_data['shipping_address'] ) ? $session_data['shipping_address'] : $session_data['billing_address'];
			$tax_total   = $this->calculate_taxes( $session_data['items'], $tax_address );
		}
		$session_data['tax_total'] = $tax_total;

		// Calculate shipping total.
		$shipping_total = 0;
		if ( ! empty( $session_data['selected_shipping_method'] ) && ! empty( $session_data['shipping_options'] ) ) {
			foreach ( $session_data['shipping_options'] as $option ) {
				if ( $option['id'] === $session_data['selected_shipping_method'] ) {
					$shipping_total = $option['amount']['value'];
					break;
				}
			}
		} elseif ( ! empty( $session_data['shipping_options'] ) ) {
			// Use first shipping option if no method selected.
			$shipping_total = $session_data['shipping_options'][0]['amount']['value'];
		}

		// Calculate discount.
		$discount = $session_data['coupon_discount'] ?? 0;

		// Calculate total.
		$session_data['total'] = max( 0, $session_data['subtotal'] + $shipping_total + $tax_total - $discount );

		return $session_data;
	}

	/**
	 * Apply coupon to session
	 *
	 * @param string               $coupon_code Coupon code.
	 * @param array<string, mixed> $session_data Session data.
	 * @return array<string, mixed>|WP_Error Updated session data or error.
	 */
	private function apply_coupon( string $coupon_code, array $session_data ): array|WP_Error {
		// Get coupon.
		$coupon = new \WC_Coupon( $coupon_code );

		if ( ! $coupon->is_valid() ) {
			return new WP_Error(
				'invalid_coupon',
				'Invalid or expired coupon code',
				array( 'status' => 400 )
			);
		}

		// Calculate discount.
		$subtotal = $session_data['subtotal'];
		$discount = 0;

		if ( 'percent' === $coupon->get_discount_type() ) {
			$discount = ( $subtotal * $coupon->get_amount() ) / 100;
		} elseif ( 'fixed_cart' === $coupon->get_discount_type() ) {
			$discount = min( $coupon->get_amount(), $subtotal );
		}

		// Store coupon data.
		$session_data['coupon_code']     = $coupon_code;
		$session_data['coupon_discount'] = $discount;

		return $session_data;
	}

	/**
	 * Validate items array
	 *
	 * @param array<int, array<string, mixed>> $items Items array.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_items( array $items ): bool|WP_Error {
		if ( empty( $items ) ) {
			return new WP_Error(
				'missing_items',
				'Items are required',
				array( 'status' => 400 )
			);
		}

		foreach ( $items as $item ) {
			if ( empty( $item['sku'] ) ) {
				return new WP_Error(
					'missing_sku',
					'SKU is required for all items',
					array( 'status' => 400 )
				);
			}

			if ( empty( $item['quantity'] ) || ! is_numeric( $item['quantity'] ) || $item['quantity'] < 1 ) {
				return new WP_Error(
					'invalid_quantity',
					'Valid quantity is required for all items',
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Parse line items from request
	 *
	 * @param array<int, array<string, mixed>> $items Items array.
	 * @return array<int, array<string, mixed>>|WP_Error Line items or error.
	 */
	private function parse_line_items( array $items ): array|WP_Error {
		$line_items = array();

		foreach ( $items as $item ) {
			$sku      = sanitize_text_field( $item['sku'] );
			$quantity = absint( $item['quantity'] );

			// Get product by SKU.
			$product_id = wc_get_product_id_by_sku( $sku );

			// If not found and SKU matches fallback pattern (PRODUCT-123), try extracting ID.
			if ( ! $product_id && preg_match( '/^PRODUCT-(\d+)$/', $sku, $matches ) ) {
				$potential_id = absint( $matches[1] );
				$product      = wc_get_product( $potential_id );

				// Verify this product actually has no SKU (to prevent false matches).
				if ( $product && empty( $product->get_sku() ) ) {
					$product_id = $potential_id;
				}
			}

			if ( ! $product_id ) {
				return new WP_Error(
					'product_not_found',
					sprintf( 'Product with SKU "%s" not found', $sku ),
					array( 'status' => 404 )
				);
			}

			// Get product object.
			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->is_purchasable() ) {
				return new WP_Error(
					'product_not_purchasable',
					sprintf( 'Product with SKU "%s" is not purchasable', $sku ),
					array( 'status' => 400 )
				);
			}

			// Check stock.
			if ( ! $product->is_in_stock() ) {
				return new WP_Error(
					'out_of_stock',
					sprintf( 'Product with SKU "%s" is out of stock', $sku ),
					array( 'status' => 400 )
				);
			}

			// Check stock quantity.
			if ( $product->managing_stock() && $product->get_stock_quantity() < $quantity ) {
				return new WP_Error(
					'insufficient_stock',
					sprintf( 'Insufficient stock for product with SKU "%s"', $sku ),
					array( 'status' => 400 )
				);
			}

			// Add to line items.
			$line_items[] = array(
				'sku'        => $sku,
				'product_id' => $product_id,
				'name'       => $product->get_name(),
				'quantity'   => $quantity,
				'price'      => (float) $product->get_price(),
				'subtotal'   => (float) $product->get_price() * $quantity,
			);
		}

		return $line_items;
	}

	/**
	 * Calculate subtotal
	 *
	 * @param array<int, array<string, mixed>> $line_items Line items.
	 * @return float Subtotal amount.
	 */
	private function calculate_subtotal( array $line_items ): float {
		$subtotal = 0;

		foreach ( $line_items as $item ) {
			$subtotal += $item['subtotal'];
		}

		return $subtotal;
	}

	/**
	 * Calculate shipping options
	 *
	 * @param array<int, array<string, mixed>> $line_items Line items.
	 * @param array<string, mixed>             $shipping_address Shipping address.
	 * @return array<int, array<string, mixed>> Shipping options.
	 */
	private function calculate_shipping( array $line_items, array $shipping_address ): array {
		// Prepare package for shipping calculation.
		$package = $this->prepare_shipping_package( $line_items, $shipping_address );

		// Get the shipping zone that matches this package.
		// WC_Shipping_Zones::get_zone_matching_package() returns a single WC_Shipping_Zone object.
		$zone = WC_Shipping_Zones::get_zone_matching_package( $package );

		$shipping_options = array();

		// Get enabled shipping methods for this zone.
		$shipping_methods = $zone->get_shipping_methods( true ); // true = enabled only.

		foreach ( $shipping_methods as $method ) {
			// Skip if method is not enabled.
			if ( 'yes' !== $method->enabled ) {
				continue;
			}

			// Calculate shipping cost for this method.
			// We need to set the package temporarily for the calculation.
			$method->calculate_shipping( $package );

			// Get the rates from the method.
			// Some methods store rates in $method->rates, others return them.
			$rates = array();
			if ( isset( $method->rates ) && is_array( $method->rates ) ) {
				$rates = $method->rates;
			}

			foreach ( $rates as $rate ) {
				$shipping_options[] = array(
					'id'     => $rate->get_id(),
					'label'  => $rate->get_label(),
					'amount' => array(
						'value'    => (float) $rate->get_cost(),
						'currency' => get_woocommerce_currency(),
					),
				);
			}
		}

		return $shipping_options;
	}

	/**
	 * Prepare shipping package
	 *
	 * @param array<int, array<string, mixed>> $line_items Line items.
	 * @param array<string, mixed>             $shipping_address Shipping address.
	 * @return array<string, mixed> Shipping package.
	 */
	private function prepare_shipping_package( array $line_items, array $shipping_address ): array {
		$contents      = array();
		$contents_cost = 0;

		foreach ( $line_items as $item ) {
			$product = wc_get_product( $item['product_id'] );

			$contents[] = array(
				'product_id' => $item['product_id'],
				'variation'  => array(),
				'quantity'   => $item['quantity'],
				'data'       => $product,
				'line_total' => $item['subtotal'],
			);

			$contents_cost += $item['subtotal'];
		}

		return array(
			'contents'      => $contents,
			'contents_cost' => $contents_cost,
			'destination'   => array(
				'country'   => $shipping_address['country'] ?? '',
				'state'     => $shipping_address['state'] ?? '',
				'postcode'  => $shipping_address['postcode'] ?? '',
				'city'      => $shipping_address['city'] ?? '',
				'address'   => $shipping_address['address_1'] ?? '',
				'address_2' => $shipping_address['address_2'] ?? '',
			),
		);
	}

	/**
	 * Calculate taxes
	 *
	 * Calculates taxes for both products and shipping if applicable.
	 * Many jurisdictions tax shipping costs, so this must be included.
	 *
	 * @param array<int, array<string, mixed>> $line_items Line items.
	 * @param array<string, mixed>             $tax_address Tax address.
	 * @param float                            $shipping_total Shipping cost to calculate tax on.
	 * @return float Tax total.
	 */
	private function calculate_taxes( array $line_items, array $tax_address, float $shipping_total = 0 ): float {
		// Check if taxes are enabled.
		if ( ! wc_tax_enabled() ) {
			return 0;
		}

		$tax_total = 0;

		foreach ( $line_items as $item ) {
			$product = wc_get_product( $item['product_id'] );

			// Get tax class.
			$tax_class = $product->get_tax_class();

			// Get tax rates.
			$tax_rates = WC_Tax::find_rates(
				array(
					'country'   => $tax_address['country'] ?? '',
					'state'     => $tax_address['state'] ?? '',
					'postcode'  => $tax_address['postcode'] ?? '',
					'city'      => $tax_address['city'] ?? '',
					'tax_class' => $tax_class,
				)
			);

			// Calculate tax for this item.
			$taxes      = WC_Tax::calc_tax( $item['subtotal'], $tax_rates, false );
			$tax_total += array_sum( $taxes );
		}

		// Calculate shipping tax if shipping is taxable.
		if ( $shipping_total > 0 ) {
			// Get shipping tax class from WooCommerce settings.
			// Empty string means use standard rate, "inherit" means inherit from cart.
			$shipping_tax_class = get_option( 'woocommerce_shipping_tax_class' );

			// If set to "inherit", use standard rate for REST API context.
			if ( 'inherit' === $shipping_tax_class ) {
				$shipping_tax_class = '';
			}

			$shipping_tax_rates = WC_Tax::find_rates(
				array(
					'country'   => $tax_address['country'] ?? '',
					'state'     => $tax_address['state'] ?? '',
					'postcode'  => $tax_address['postcode'] ?? '',
					'city'      => $tax_address['city'] ?? '',
					'tax_class' => $shipping_tax_class,
				)
			);

			$shipping_taxes = WC_Tax::calc_tax( $shipping_total, $shipping_tax_rates, false );
			$tax_total     += array_sum( $shipping_taxes );
		}

		return $tax_total;
	}

	/**
	 * Generate unique session ID
	 *
	 * @return string Session ID.
	 */
	private function generate_session_id(): string {
		return wp_generate_password( 32, false );
	}

	/**
	 * Format session response per ACP specification
	 *
	 * @param string               $session_id Session ID.
	 * @param array<string, mixed> $session_data Session data.
	 * @return array<string, mixed> Formatted response.
	 */
	private function format_session_response( string $session_id, array $session_data ): array {
		$response = array(
			'id'       => $session_id,
			'items'    => array_map(
				function ( $item ) {
					return array(
						'sku'      => $item['sku'],
						'name'     => $item['name'],
						'quantity' => $item['quantity'],
						'price'    => array(
							'value'    => $item['price'],
							'currency' => get_woocommerce_currency(),
						),
						'subtotal' => array(
							'value'    => $item['subtotal'],
							'currency' => get_woocommerce_currency(),
						),
					);
				},
				$session_data['items']
			),
			'subtotal' => array(
				'value'    => $session_data['subtotal'],
				'currency' => $session_data['currency'],
			),
		);

		// Only include shipping_options if not empty.
		// Per ACP specification, when no shipping address provided, the key should be absent entirely.
		if ( ! empty( $session_data['shipping_options'] ) ) {
			$response['shipping_options'] = $session_data['shipping_options'];
		}

		$response['tax']        = array(
			'value'    => $session_data['tax_total'],
			'currency' => $session_data['currency'],
		);
		$response['total']      = array(
			'value'    => $session_data['total'],
			'currency' => $session_data['currency'],
		);
		$response['status']     = $session_data['status'] ?? 'active';
		$response['created_at'] = $session_data['created_at'] ?? time();
		$response['updated_at'] = $session_data['updated_at'] ?? time();
		$response['expires_at'] = $session_data['expires_at'] ?? time() + DAY_IN_SECONDS;

		return $response;
	}

	/**
	 * Get arguments for complete session endpoint
	 *
	 * @return array<string, array<string, mixed>> Arguments schema.
	 */
	private function get_complete_session_args(): array {
		return array(
			'id'               => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'payment'          => array(
				'required' => true,
				'type'     => 'object',
			),
			'billing_address'  => array(
				'required' => false,
				'type'     => 'object',
			),
			'shipping_address' => array(
				'required' => false,
				'type'     => 'object',
			),
		);
	}

	/**
	 * Map payment errors to user-friendly messages
	 *
	 * @param WP_Error $error Payment error.
	 * @return WP_Error Mapped error.
	 */
	private function map_payment_error( WP_Error $error ): WP_Error {
		$error_code = $error->get_error_code();
		$error_data = $error->get_error_data();

		// Map common Stripe SharedPaymentToken errors.
		$error_messages = array(
			'shared_payment_token_used'    => 'Payment token has already been used',
			'shared_payment_token_expired' => 'Payment token has expired',
			'amount_too_large'             => 'Amount exceeds token limit',
			'currency_mismatch'            => 'Currency does not match token',
			'payment_failed'               => 'Payment processing failed. Please try again.',
			'payment_declined'             => 'Payment was declined. Please use a different payment method.',
			'missing_payment_token'        => 'Payment token is required',
			'invalid_token_format'         => 'Invalid payment token format',
		);

		$message = $error_messages[ $error_code ] ?? $error->get_error_message();

		return new WP_Error(
			$error_code,
			$message,
			$error_data
		);
	}

	/**
	 * Validate product stock availability for session items
	 *
	 * Re-checks that all products in the session are still in stock and purchasable.
	 * This prevents race conditions where products become unavailable between
	 * session creation and payment completion.
	 *
	 * Optimized to batch-load all products in single query instead of individual queries.
	 *
	 * @param array<string, mixed> $session_data Session data containing items.
	 * @return bool|WP_Error True if all products are available, WP_Error otherwise.
	 */
	private function validate_session_stock( array $session_data ): bool|WP_Error {
		if ( empty( $session_data['items'] ) || ! is_array( $session_data['items'] ) ) {
			return true;
		}

		// Extract all product IDs for batch loading.
		$product_ids = wp_list_pluck( $session_data['items'], 'product_id' );

		// Batch load all products in single database query.
		// This is significantly faster than calling wc_get_product() for each item.
		$products = wc_get_products(
			array(
				'include' => $product_ids,
				'limit'   => -1, // No limit - get all requested products.
				'status'  => 'publish', // Only published products.
			)
		);

		// Create lookup map indexed by product ID for O(1) access.
		$product_map = array();
		foreach ( $products as $product ) {
			$product_map[ $product->get_id() ] = $product;
		}

		// Validate each item using pre-loaded products.
		foreach ( $session_data['items'] as $item ) {
			$product_id = $item['product_id'] ?? 0;
			$quantity   = $item['quantity'] ?? 1;
			$name       = $item['name'] ?? 'Unknown product';

			// Check if product was loaded (exists and is published).
			$product = $product_map[ $product_id ] ?? null;

			// Check if product exists and is purchasable.
			if ( ! $product || ! $product->is_purchasable() ) {
				return new WP_Error(
					'product_not_available',
					sprintf( 'Product "%s" is no longer available for purchase', $name ),
					array(
						'status' => 400,
						'code'   => 'product_not_available',
					)
				);
			}

			// Check if product is in stock.
			if ( ! $product->is_in_stock() ) {
				return new WP_Error(
					'out_of_stock',
					sprintf( 'Product "%s" is out of stock', $name ),
					array(
						'status' => 400,
						'code'   => 'out_of_stock',
					)
				);
			}

			// Check stock quantity if product manages stock.
			if ( $product->managing_stock() && $product->get_stock_quantity() < $quantity ) {
				$available = $product->get_stock_quantity();
				return new WP_Error(
					'insufficient_stock',
					sprintf(
						'Product "%s" has insufficient stock. Available: %d, Requested: %d',
						$name,
						$available,
						$quantity
					),
					array(
						'status'    => 400,
						'code'      => 'insufficient_stock',
						'available' => $available,
						'requested' => $quantity,
					)
				);
			}
		}

		return true;
	}
}
