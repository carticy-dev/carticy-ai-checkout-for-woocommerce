<?php
/**
 * Plugin Initialization
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout;

use Carticy\AiCheckout\Core\Container;
use Carticy\AiCheckout\Admin\AdminHandler;
use Carticy\AiCheckout\Admin\ProductFeedManager;
use Carticy\AiCheckout\Admin\ApplicationWizard;
use Carticy\AiCheckout\Services\PrerequisitesChecker;
use Carticy\AiCheckout\Services\ProductFeedService;
use Carticy\AiCheckout\Services\ProductQualityChecker;
use Carticy\AiCheckout\Services\SessionService;
use Carticy\AiCheckout\Services\AuthenticationService;
use Carticy\AiCheckout\Services\ApiKeyService;
use Carticy\AiCheckout\Services\StripePaymentAdapter;
use Carticy\AiCheckout\Services\WebhookService;
use Carticy\AiCheckout\Services\IdempotencyService;
use Carticy\AiCheckout\Services\IpAllowlistService;
use Carticy\AiCheckout\Services\RateLimitService;
use Carticy\AiCheckout\Services\ApplicationWizardService;
use Carticy\AiCheckout\Services\AnalyticsService;
use Carticy\AiCheckout\Services\LoggingService;
use Carticy\AiCheckout\Services\ErrorLogService;
use Carticy\AiCheckout\Services\WebhookLogger;
use Carticy\AiCheckout\Services\PerformanceMetrics;
use Carticy\AiCheckout\Services\TestModeService;
use Carticy\AiCheckout\Services\ApiDebugLogger;
use Carticy\AiCheckout\Services\MockSimulator;
use Carticy\AiCheckout\Services\ConformanceTestService;
use Carticy\AiCheckout\Admin\AnalyticsDashboard;
use Carticy\AiCheckout\Admin\LogsViewer;
use Carticy\AiCheckout\Api\ProductFeedEndpoint;
use Carticy\AiCheckout\Api\CheckoutSessionEndpoint;

/**
 * Main plugin initialization class
 */
final class Init {

	/**
	 * Singleton instance
	 *
	 * @var Init|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Private constructor for singleton
	 */
	private function __construct() {
		$this->container = new Container();
		$this->register_services();
		$this->init_hooks();
	}

	/**
	 * Get singleton instance
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register services in container
	 *
	 * @return void
	 */
	private function register_services(): void {
		// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Container parameter required by interface.
		// Register Prerequisites Checker.
		$this->container->register(
			'prerequisites',
			function ( Container $c ): PrerequisitesChecker {
				return new PrerequisitesChecker();
			}
		);

		// Register API Key Service.
		$this->container->register(
			'api_key',
			function ( Container $c ): ApiKeyService {
				return new ApiKeyService();
			}
		);

		// Register Product Feed Service.
		$this->container->register(
			'product_feed',
			function ( Container $c ): ProductFeedService {
				return new ProductFeedService();
			}
		);

		// Register Product Quality Checker.
		$this->container->register(
			'product_quality',
			function ( Container $c ): ProductQualityChecker {
				return new ProductQualityChecker();
			}
		);

		// Register Session Service.
		$this->container->register(
			'session',
			function ( Container $c ): SessionService {
				return new SessionService();
			}
		);

		// Register Logging Service (before other services that depend on it).
		$this->container->register(
			'logging',
			function ( Container $c ): LoggingService {
				return new LoggingService();
			}
		);

		// Register Idempotency Service.
		$this->container->register(
			'idempotency',
			function ( Container $c ): IdempotencyService {
				return new IdempotencyService();
			}
		);

		// Register IP Allowlist Service.
		$this->container->register(
			'ip_allowlist',
			function ( Container $c ): IpAllowlistService {
				return new IpAllowlistService( $c->get( 'logging' ) );
			}
		);

		// Register Rate Limit Service.
		$this->container->register(
			'rate_limit',
			function ( Container $c ): RateLimitService {
				return new RateLimitService();
			}
		);

		// Register Authentication Service.
		$this->container->register(
			'auth',
			function ( Container $c ): AuthenticationService {
				$auth_service = new AuthenticationService();
				$auth_service->set_ip_allowlist_service( $c->get( 'ip_allowlist' ) );
				$auth_service->set_rate_limit_service( $c->get( 'rate_limit' ) );
				return $auth_service;
			}
		);

		// Register Stripe Payment Adapter.
		$this->container->register(
			'stripe_payment',
			function ( Container $c ): StripePaymentAdapter {
				return new StripePaymentAdapter( $c->get( 'logging' ) );
			}
		);

		// Register Webhook Service.
		$this->container->register(
			'webhook',
			function ( Container $c ): WebhookService {
				return new WebhookService( $c->get( 'webhook_logger' ) );
			}
		);

		// Register Error Log Service.
		$this->container->register(
			'error_log',
			function ( Container $c ): ErrorLogService {
				return new ErrorLogService( $c->get( 'logging' ) );
			}
		);

		// Register Webhook Logger.
		$this->container->register(
			'webhook_logger',
			function ( Container $c ): WebhookLogger {
				return new WebhookLogger( $c->get( 'logging' ) );
			}
		);

		// Register Performance Metrics.
		$this->container->register(
			'performance_metrics',
			function ( Container $c ): PerformanceMetrics {
				return new PerformanceMetrics( $c->get( 'logging' ) );
			}
		);

		// Register Product Feed Endpoint.
		$this->container->register(
			'product_feed_endpoint',
			function ( Container $c ): ProductFeedEndpoint {
				return new ProductFeedEndpoint( $c->get( 'product_feed' ) );
			}
		);

		// Register Checkout Session Endpoint.
		$this->container->register(
			'checkout_session_endpoint',
			function ( Container $c ): CheckoutSessionEndpoint {
				return new CheckoutSessionEndpoint(
					$c->get( 'session' ),
					$c->get( 'auth' ),
					$c->get( 'stripe_payment' ),
					$c->get( 'idempotency' ),
					$c->get( 'logging' ),
					$c->get( 'error_log' ),
					$c->get( 'performance_metrics' )
				);
			}
		);

		// Register Admin Handler.
		$this->container->register(
			'admin',
			function ( Container $c ): AdminHandler {
				return new AdminHandler( $c->get( 'prerequisites' ) );
			}
		);

		// Register Product Feed Manager.
		$this->container->register(
			'product_feed_manager',
			function ( Container $c ): ProductFeedManager {
				return new ProductFeedManager( $c->get( 'product_feed' ), $c->get( 'product_quality' ) );
			}
		);

		// Register Application Wizard Service.
		$this->container->register(
			'application_wizard_service',
			function ( Container $c ): ApplicationWizardService {
				return new ApplicationWizardService();
			}
		);

		// Register Application Wizard.
		$this->container->register(
			'application_wizard',
			function ( Container $c ): ApplicationWizard {
				return new ApplicationWizard(
					$c->get( 'application_wizard_service' ),
					$c->get( 'product_quality' ),
					$c->get( 'prerequisites' )
				);
			}
		);

		// Register Analytics Service.
		$this->container->register(
			'analytics_service',
			function ( Container $c ): AnalyticsService {
				return new AnalyticsService();
			}
		);

		// Register Analytics Dashboard.
		$this->container->register(
			'analytics_dashboard',
			function ( Container $c ): AnalyticsDashboard {
				return new AnalyticsDashboard( $c->get( 'analytics_service' ) );
			}
		);

		// Register Logs Viewer.
		$this->container->register(
			'logs_viewer',
			function ( Container $c ): LogsViewer {
				return new LogsViewer(
					$c->get( 'logging' ),
					$c->get( 'error_log' ),
					$c->get( 'webhook_logger' ),
					$c->get( 'performance_metrics' ),
					$c->get( 'session' )
				);
			}
		);

		// Register Test Mode Service.
		$this->container->register(
			'test_mode',
			function ( Container $c ): TestModeService {
				return new TestModeService();
			}
		);

		// Register API Debug Logger.
		$this->container->register(
			'api_debug_logger',
			function ( Container $c ): ApiDebugLogger {
				return new ApiDebugLogger( $c->get( 'logging' ), $c->get( 'test_mode' ) );
			}
		);

		// Register Mock Simulator.
		$this->container->register(
			'mock_simulator',
			function ( Container $c ): MockSimulator {
				return new MockSimulator();
			}
		);

		// Register Conformance Test Service.
		$this->container->register(
			'conformance_test',
			function ( Container $c ): ConformanceTestService {
				return new ConformanceTestService( $c->get( 'mock_simulator' ) );
			}
		);
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.Found
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Initialize admin area.
		if ( is_admin() ) {
			$this->container->get( 'admin' );
			$this->container->get( 'product_feed_manager' );
			$this->container->get( 'application_wizard' );
			$this->container->get( 'analytics_dashboard' );
			$this->container->get( 'logs_viewer' );
		}

		// Register REST API endpoints.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Add custom cron schedules.
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Add cache invalidation hooks.
		add_action( 'save_post_product', array( $this, 'invalidate_product_feed_cache' ) );
		add_action( 'woocommerce_update_product', array( $this, 'invalidate_product_feed_cache' ) );
		add_action( 'woocommerce_delete_product', array( $this, 'invalidate_product_feed_cache' ) );

		// Add product feed refresh cron hook.
		add_action( 'carticy_ai_checkout_refresh_product_feed', array( $this, 'refresh_product_feed_cache' ) );

		// Add session cleanup cron hook.
		add_action( 'carticy_ai_checkout_cleanup_sessions', array( $this, 'cleanup_expired_sessions' ) );

		// Add IP allowlist auto-update cron hook.
		add_action( 'carticy_ai_checkout_update_openai_ips', array( $this, 'update_openai_ip_ranges' ) );

		// Add Stripe SharedPaymentToken injection filter.
		add_filter( 'wc_stripe_generate_create_intent_request', array( $this, 'inject_shared_payment_token' ), 10, 3 );

		// Add robots.txt filter for OpenAI crawler access.
		add_filter( 'robots_txt', array( $this, 'add_openai_robots_rules' ), 10, 2 );

		// Add WooCommerce order lifecycle webhook hooks.
		// Note: woocommerce_new_order fires too early (before _chatgpt_checkout meta is set).
		// We use a custom action 'carticy_ai_checkout_order_created' triggered manually after order setup.
		add_action( 'carticy_ai_checkout_order_created', array( $this, 'send_order_created_webhook' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'send_order_updated_webhook' ), 10, 3 );

		// Add Stripe webhook listener for Dashboard-initiated events.
		add_action( 'woocommerce_stripe_process_webhook', array( $this, 'handle_stripe_webhook' ), 10, 1 );
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$this->container->get( 'product_feed_endpoint' );
		$this->container->get( 'checkout_session_endpoint' );
	}

	/**
	 * Invalidate product feed cache
	 *
	 * @return void
	 */
	public function invalidate_product_feed_cache(): void {
		$this->container->get( 'product_feed' )->invalidate_cache();
	}

	/**
	 * Refresh product feed cache
	 *
	 * Scheduled to run every 15 minutes to ensure OpenAI has fresh product data.
	 * This is triggered by the WP-Cron event 'carticy_ai_checkout_refresh_product_feed'.
	 *
	 * @return void
	 */
	public function refresh_product_feed_cache(): void {
		$this->container->get( 'product_feed' )->invalidate_cache();
	}

	/**
	 * Clean up expired checkout sessions
	 *
	 * @return void
	 */
	public function cleanup_expired_sessions(): void {
		$session_service = $this->container->get( 'session' );

		// Run all cleanup methods to maintain database health (each limited to 100 sessions per run).

		// 1. Clean expired sessions (transient TTL expired - 24 hours).
		$expired_count = $session_service->cleanup_expired();

		// 2. Clean orphaned sessions (orders completed/failed but session still exists).
		$orphaned_count = $session_service->cleanup_orphaned_sessions();

		// 3. Clean old completed/failed sessions (served audit purpose - 7+ days old).
		$completed_count = $session_service->cleanup_completed_sessions();

		// 4. Clean abandoned active sessions (no activity for 2+ hours, no order created).
		$abandoned_count = $session_service->cleanup_abandoned_sessions();

		$total_count = $expired_count + $orphaned_count + $completed_count + $abandoned_count;

		if ( $total_count > 0 ) {
			$this->container->get( 'logging' )->debug(
				sprintf(
					'Session cleanup completed: %d expired, %d orphaned, %d completed, %d abandoned, %d total',
					$expired_count,
					$orphaned_count,
					$completed_count,
					$abandoned_count,
					$total_count
				)
			);
		}
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Existing schedules.
	 * @return array<string, array{interval: int, display: string}> Modified schedules.
	 */
	public function add_cron_schedules( array $schedules ): array {
		$schedules['every_15_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'carticy-ai-checkout-for-woocommerce' ),
		);

		return $schedules;
	}

	/**
	 * Get a service from the container
	 *
	 * @param string $id Service identifier.
	 * @return mixed Service instance.
	 */
	public function get_service( string $id ): mixed {
		return $this->container->get( $id );
	}

	/**
	 * Inject SharedPaymentToken into WC Stripe Gateway PaymentIntent creation
	 *
	 * This filter hooks into WooCommerce Stripe Gateway RIGHT BEFORE it sends
	 * the PaymentIntent creation request to Stripe API. We inject the
	 * SharedPaymentToken parameter which WC Stripe Gateway doesn't natively support.
	 *
	 * @param array<string, mixed> $request         PaymentIntent request data.
	 * @param \WC_Order            $order           WooCommerce order object.
	 * @param mixed                $prepared_source Prepared payment source (unused, required by filter).
	 * @return array<string, mixed> Modified request data.
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WC Stripe filter signature.
	 */
	public function inject_shared_payment_token( array $request, \WC_Order $order, mixed $prepared_source ): array {
		// Only modify requests for ChatGPT checkout orders.
		if ( 'yes' !== $order->get_meta( '_chatgpt_checkout' ) ) {
			return $request;
		}

		// Retrieve SharedPaymentToken from transient.
		$shared_payment_token = get_transient( 'carticy_ai_checkout_spt_' . $order->get_id() );

		if ( $shared_payment_token ) {
			// Inject SharedPaymentToken into Stripe API request.
			$request['shared_payment_token'] = $shared_payment_token;

			// Remove payment_method parameter - SharedPaymentToken replaces it.
			unset( $request['payment_method'] );

			// Clean up transient after use.
			delete_transient( 'carticy_ai_checkout_spt_' . $order->get_id() );

			// Log for debugging.
			$order->add_order_note( 'SharedPaymentToken injected into PaymentIntent' );
		}

		return $request;
	}

	/**
	 * Add OpenAI crawler rules to robots.txt
	 *
	 * Uses WordPress robots.txt filter to add OpenAI crawler access rules.
	 * This only works when no physical robots.txt file exists in WordPress root.
	 *
	 * @param string $output    Existing robots.txt output.
	 * @param string $is_public Whether the site is public (1) or private (0).
	 * @return string Modified robots.txt output.
	 */
	public function add_openai_robots_rules( string $output, string $is_public ): string {
		// Don't add rules if site is not public.
		if ( '1' !== $is_public ) {
			return $output;
		}

		// Check if enabled via option.
		if ( 'yes' !== get_option( 'carticy_ai_checkout_enable_openai_robots', 'yes' ) ) {
			return $output;
		}

		// Add OpenAI crawler rules.
		$output .= "\n# OpenAI Agentic Commerce Protocol - ChatGPT Product Discovery\n";
		$output .= "User-agent: OAI-SearchBot\n";
		$output .= "Allow: /\n\n";
		$output .= "User-agent: ChatGPT-User\n";
		$output .= "Allow: /\n";

		return $output;
	}

	/**
	 * Send order_created webhook to OpenAI
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function send_order_created_webhook( int $order_id ): void {
		$this->container->get( 'webhook' )->send_order_created( $order_id );
	}

	/**
	 * Send order_updated webhook to OpenAI
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $old_status Old order status (unused, required by hook).
	 * @param string $new_status New order status (unused, required by hook).
	 * @return void
	 *
	 * @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WooCommerce hook signature.
	 */
	public function send_order_updated_webhook( int $order_id, string $old_status, string $new_status ): void {
		$this->container->get( 'webhook' )->send_order_updated( $order_id );
	}


	/**
	 * Update OpenAI IP ranges (WP-Cron callback)
	 *
	 * @return void
	 */
	public function update_openai_ip_ranges(): void {
		$result = $this->container->get( 'ip_allowlist' )->refresh_ip_ranges();

		if ( is_wp_error( $result ) ) {
			$this->container->get( 'logging' )->log_error(
				sprintf( 'Failed to update OpenAI IP ranges - %s', $result->get_error_message() ),
				'system'
			);
		}
	}

	/**
	 * Handle Stripe webhook events
	 *
	 * Listens for Stripe webhook events processed by WC Stripe Gateway
	 * to catch refunds and disputes initiated from Stripe Dashboard.
	 *
	 * @param object $event Stripe event object.
	 * @return void
	 */
	public function handle_stripe_webhook( object $event ): void {
		// Only process refund and dispute events.
		if ( ! in_array( $event->type, array( 'charge.refunded', 'charge.dispute.created', 'charge.dispute.closed' ), true ) ) {
			return;
		}

		// Get the charge object from the event.
		$charge = $event->data->object;

		// Find the order by Stripe PaymentIntent ID.
		$order = $this->find_order_by_payment_intent( $charge->payment_intent ?? '' );

		if ( ! $order || 'yes' !== $order->get_meta( '_chatgpt_checkout' ) ) {
			return;
		}

		// Handle different event types.
		switch ( $event->type ) {
			case 'charge.refunded':
				// Notify OpenAI about refund from Stripe Dashboard.
				$this->send_stripe_refund_webhook( $order, $charge );
				break;

			case 'charge.dispute.created':
				// Notify OpenAI about new dispute/chargeback.
				$this->send_dispute_created_webhook( $order, $charge );
				break;

			case 'charge.dispute.closed':
				// Notify OpenAI about dispute resolution.
				$this->send_dispute_closed_webhook( $order, $charge );
				break;
		}
	}

	/**
	 * Find WooCommerce order by Stripe PaymentIntent ID
	 *
	 * @param string $payment_intent_id Stripe PaymentIntent ID.
	 * @return \WC_Order|null Order object or null.
	 */
	private function find_order_by_payment_intent( string $payment_intent_id ): ?\WC_Order {
		if ( empty( $payment_intent_id ) ) {
			return null;
		}

		// Search for order by Stripe PaymentIntent meta.
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_query' => array(
					array(
						'key'     => '_stripe_intent_id',
						'value'   => $payment_intent_id,
						'compare' => '=',
					),
				),
			)
		);

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Send webhook notification for Stripe Dashboard refund
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param object    $charge Stripe charge object.
	 * @return void
	 */
	private function send_stripe_refund_webhook( \WC_Order $order, object $charge ): void {
		$webhook = $this->container->get( 'webhook' );

		$payload = array(
			'order_id'      => $order->get_id(),
			'status'        => $order->get_status(),
			'refund_amount' => $charge->amount_refunded / 100, // Convert from cents to dollars.
			'refund_reason' => 'Refunded via Stripe Dashboard',
			'refund_source' => 'stripe_dashboard',
		);

		$webhook->send( 'order_updated', $payload );
	}

	/**
	 * Send webhook notification for dispute/chargeback created
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param object    $charge Stripe charge object.
	 * @return void
	 */
	private function send_dispute_created_webhook( \WC_Order $order, object $charge ): void {
		$webhook = $this->container->get( 'webhook' );

		$dispute = $charge->dispute ?? null;

		$payload = array(
			'order_id'       => $order->get_id(),
			'status'         => $order->get_status(),
			'dispute_amount' => ( $dispute->amount ?? 0 ) / 100,
			'dispute_reason' => $dispute->reason ?? 'unknown',
			'dispute_status' => $dispute->status ?? 'unknown',
		);

		$webhook->send( 'order_updated', $payload );
	}

	/**
	 * Send webhook notification for dispute closed/resolved
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param object    $charge Stripe charge object.
	 * @return void
	 */
	private function send_dispute_closed_webhook( \WC_Order $order, object $charge ): void {
		$webhook = $this->container->get( 'webhook' );

		$dispute = $charge->dispute ?? null;

		$payload = array(
			'order_id'       => $order->get_id(),
			'status'         => $order->get_status(),
			'dispute_status' => $dispute->status ?? 'unknown',
		);

		$webhook->send( 'order_updated', $payload );
	}
}
