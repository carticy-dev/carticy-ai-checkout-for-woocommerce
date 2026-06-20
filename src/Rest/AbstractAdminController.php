<?php
/**
 * Shared behavior for admin-only REST controllers.
 *
 * These controllers back the React admin screens. They are separate from the
 * public ChatGPT integration API (namespace carticy-ai-checkout/v1) and are
 * gated by the same capability the admin pages use (manage_options). The
 * wp_rest nonce is sent automatically by @wordpress/api-fetch.
 *
 * @package Carticy\AiCheckout\Rest
 */

declare(strict_types=1);

namespace Carticy\AiCheckout\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for admin-only REST controllers backing the React screens.
 */
abstract class AbstractAdminController {

	/**
	 * REST namespace shared by every admin controller.
	 */
	public const NAMESPACE = 'carticy-ai-checkout-admin/v1';

	/**
	 * Capability that gates the admin screens (matches AdminHandler).
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Route definitions for this controller.
	 *
	 * Each entry: { methods, route, callback, permission_callback?, args? }.
	 * When permission_callback is omitted it defaults to can_manage().
	 *
	 * @return array<int, array<string, mixed>>
	 */
	abstract public function routes(): array;

	/**
	 * Admin capability gate (the default permission callback for routes).
	 *
	 * @return bool Whether the current user may use the admin screens.
	 */
	public function can_manage(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAPABILITY );
	}

	/**
	 * Build a success response.
	 *
	 * @param array<string, mixed> $data   Response payload.
	 * @param int                  $status HTTP status code.
	 */
	protected function ok( array $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response( $data, $status );
	}

	/**
	 * Build an error response.
	 *
	 * @param string               $code    Machine error code.
	 * @param string               $message Human-readable message.
	 * @param int                  $status  HTTP status code.
	 * @param array<string, mixed> $data    Extra error data.
	 */
	protected function fail( string $code, string $message, int $status, array $data = array() ): \WP_Error {
		return new \WP_Error( $code, $message, array_merge( array( 'status' => $status ), $data ) );
	}

	/**
	 * Decoded request body (JSON first, then form params).
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return array<string, mixed>
	 */
	protected function body( \WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		return is_array( $json ) ? $json : $request->get_body_params();
	}
}
