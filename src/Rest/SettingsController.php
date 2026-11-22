<?php
/**
 * Settings screen REST controller.
 *
 * Wraps the existing settings services (PrerequisitesChecker, ApiKeyService,
 * IpAllowlistService, WebhookService) so the React Settings screen can load the
 * settings payload, persist the four stored options, and trigger the same
 * key/secret/IP/webhook actions the legacy AJAX + admin-post handlers exposed.
 *
 * @package Carticy\AiCheckout\Rest
 */

declare(strict_types=1);

namespace Carticy\AiCheckout\Rest;

use Carticy\AiCheckout\Services\PrerequisitesChecker;
use Carticy\AiCheckout\Services\ApiKeyService;
use Carticy\AiCheckout\Services\IpAllowlistService;
use Carticy\AiCheckout\Services\WebhookService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backs the React Settings screen.
 */
final class SettingsController extends AbstractAdminController {

	/**
	 * Prerequisites checker service.
	 *
	 * @var PrerequisitesChecker
	 */
	private PrerequisitesChecker $prerequisites;

	/**
	 * API key + webhook secret service.
	 *
	 * @var ApiKeyService
	 */
	private ApiKeyService $api_key;

	/**
	 * OpenAI IP allowlist service.
	 *
	 * @var IpAllowlistService
	 */
	private IpAllowlistService $ip_allowlist;

	/**
	 * Outgoing webhook service.
	 *
	 * @var WebhookService
	 */
	private WebhookService $webhook;

	/**
	 * Human-readable labels for the prerequisite check keys.
	 *
	 * @var array<string, string>
	 */
	private const PREREQUISITE_LABELS = array(
		'wordpress_version' => 'WordPress Version',
		'php_version'       => 'PHP Version',
		'woocommerce'       => 'WooCommerce',
		'stripe_gateway'    => 'WooCommerce Stripe Gateway',
		'stripe_keys'       => 'Stripe API Keys',
		'ssl'               => 'SSL Certificate',
		'permalinks'        => 'Permalink Structure',
		'stripe_api'        => 'Stripe API Connection',
	);

	/**
	 * Constructor.
	 *
	 * @param PrerequisitesChecker $prerequisites Prerequisites checker service.
	 * @param ApiKeyService        $api_key       API key + webhook secret service.
	 * @param IpAllowlistService   $ip_allowlist  OpenAI IP allowlist service.
	 * @param WebhookService       $webhook       Outgoing webhook service.
	 */
	public function __construct(
		PrerequisitesChecker $prerequisites,
		ApiKeyService $api_key,
		IpAllowlistService $ip_allowlist,
		WebhookService $webhook
	) {
		$this->prerequisites = $prerequisites;
		$this->api_key       = $api_key;
		$this->ip_allowlist  = $ip_allowlist;
		$this->webhook       = $webhook;
	}

	/**
	 * Route definitions for this controller.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function routes(): array {
		return array(
			array(
				'methods'  => 'GET',
				'route'    => '/settings',
				'callback' => array( $this, 'get_settings' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/settings',
				'callback' => array( $this, 'save_settings' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/settings/regenerate-api-key',
				'callback' => array( $this, 'regenerate_api_key' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/settings/regenerate-webhook-secret',
				'callback' => array( $this, 'regenerate_webhook_secret' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/settings/test-webhook',
				'callback' => array( $this, 'test_webhook' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/settings/refresh-ip-ranges',
				'callback' => array( $this, 'refresh_ip_ranges' ),
			),
		);
	}

	/**
	 * Return the full settings payload the React screen renders from.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings(): \WP_REST_Response {
		return $this->ok( $this->build_payload() );
	}

	/**
	 * Persist the four stored options, then return the refreshed payload.
	 *
	 * @param \WP_REST_Request $request The REST request (reads the option values from the body).
	 * @return \WP_REST_Response
	 */
	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $this->body( $request );

		update_option( 'carticy_ai_checkout_test_mode', $this->yes_no( $body['test_mode'] ?? null ) );
		update_option( 'carticy_ai_checkout_delete_data_on_uninstall', $this->yes_no( $body['delete_data_on_uninstall'] ?? null ) );
		update_option( 'carticy_ai_checkout_enable_ip_allowlist', $this->yes_no( $body['enable_ip_allowlist'] ?? null ) );
		update_option( 'carticy_ai_checkout_webhook_url', esc_url_raw( (string) ( $body['webhook_url'] ?? '' ) ) );

		return $this->ok( $this->build_payload() );
	}

	/**
	 * Regenerate the API bearer token and return the new value.
	 *
	 * @return \WP_REST_Response
	 */
	public function regenerate_api_key(): \WP_REST_Response {
		return $this->ok(
			array(
				'api_key' => $this->api_key->regenerate_api_key(),
				'message' => __( 'API key regenerated successfully.', 'carticy-ai-checkout-for-woocommerce' ),
			)
		);
	}

	/**
	 * Regenerate the webhook HMAC secret and return the new value.
	 *
	 * @return \WP_REST_Response
	 */
	public function regenerate_webhook_secret(): \WP_REST_Response {
		return $this->ok(
			array(
				'webhook_secret' => $this->api_key->regenerate_webhook_secret(),
				'message'        => __( 'Webhook secret regenerated successfully.', 'carticy-ai-checkout-for-woocommerce' ),
			)
		);
	}

	/**
	 * Send a sample order_created webhook so the merchant can verify delivery.
	 *
	 * Mirrors the legacy ajax_test_webhook handler's sample payload.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_webhook(): \WP_REST_Response|\WP_Error {
		$test_data = array(
			'session_id'    => 'test_session_' . time(),
			'permalink_url' => home_url( '/test-order' ),
			'status'        => 'test',
			'refunds'       => array(),
		);

		$sent = $this->webhook->send( 'order_created', $test_data );

		if ( ! $sent ) {
			return $this->fail(
				'webhook_failed',
				__( 'Failed to send test webhook. Verify your webhook URL and secret are configured.', 'carticy-ai-checkout-for-woocommerce' ),
				400
			);
		}

		return $this->ok(
			array(
				'message' => __( 'Test webhook sent. Check that it arrived at your webhook URL.', 'carticy-ai-checkout-for-woocommerce' ),
			)
		);
	}

	/**
	 * Force a fresh fetch of the OpenAI IP ranges and report the new count.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function refresh_ip_ranges(): \WP_REST_Response|\WP_Error {
		$result = $this->ip_allowlist->refresh_ip_ranges();

		if ( is_wp_error( $result ) ) {
			return $this->fail(
				'ip_refresh_failed',
				__( 'Failed to refresh IP ranges. Unable to fetch fresh ranges from OpenAI; using cached ranges.', 'carticy-ai-checkout-for-woocommerce' ),
				400,
				array( 'count' => count( $this->ip_allowlist->get_current_ranges() ) )
			);
		}

		return $this->ok(
			array(
				'message' => __( 'IP ranges refreshed successfully!', 'carticy-ai-checkout-for-woocommerce' ),
				'count'   => count( $this->ip_allowlist->get_current_ranges() ),
			)
		);
	}

	/**
	 * Assemble the GET/POST settings payload from options + services.
	 *
	 * @return array<string, mixed>
	 */
	private function build_payload(): array {
		$ip_enabled = 'yes' === get_option( 'carticy_ai_checkout_enable_ip_allowlist', 'no' );

		return array(
			'prerequisites'    => $this->prerequisite_rows(),
			'settings'         => array(
				'test_mode'                => 'yes' === get_option( 'carticy_ai_checkout_test_mode', 'yes' ),
				'delete_data_on_uninstall' => 'yes' === get_option( 'carticy_ai_checkout_delete_data_on_uninstall', 'no' ),
				'webhook_url'              => (string) get_option( 'carticy_ai_checkout_webhook_url', '' ),
				'enable_ip_allowlist'      => $ip_enabled,
			),
			'api_key'          => (string) ( $this->api_key->get_api_key() ?? '' ),
			'webhook_secret'   => (string) ( $this->api_key->get_webhook_secret() ?? '' ),
			'product_feed_url' => rest_url( 'carticy-ai-checkout/v1/products' ),
			'ip_allowlist'     => array(
				'enabled' => $ip_enabled,
				'count'   => count( (array) get_option( 'carticy_ai_checkout_openai_ip_ranges_backup', array() ) ),
			),
		);
	}

	/**
	 * Serialize the prerequisite checks into React-ready rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function prerequisite_rows(): array {
		$rows = array();
		foreach ( $this->prerequisites->check_all() as $key => $check ) {
			$passed = ! empty( $check['passed'] );
			$action = $check['action'] ?? '';

			$rows[] = array(
				'label'        => self::PREREQUISITE_LABELS[ $key ] ?? ucwords( str_replace( '_', ' ', (string) $key ) ),
				'status'       => $passed ? 'pass' : 'fail',
				'message'      => (string) ( $check['message'] ?? '' ),
				'action_url'   => '' !== $action ? esc_url_raw( (string) $action ) : '',
				'action_label' => '' !== $action ? __( 'Fix This', 'carticy-ai-checkout-for-woocommerce' ) : '',
			);
		}

		return $rows;
	}

	/**
	 * Normalize an incoming checkbox value to the stored 'yes'/'no' string.
	 *
	 * @param mixed $value Raw value from the request body.
	 * @return string 'yes' or 'no'.
	 */
	private function yes_no( mixed $value ): string {
		return in_array( $value, array( true, 'yes', '1', 1 ), true ) ? 'yes' : 'no';
	}
}
