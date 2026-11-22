<?php
/**
 * Admin REST route registrar.
 *
 * Registers every admin controller's routes under the shared admin namespace,
 * defaulting each route to the admin capability gate unless the route supplies
 * its own permission_callback.
 *
 * @package Carticy\AiCheckout\Rest
 */

declare(strict_types=1);

namespace Carticy\AiCheckout\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers every admin controller's routes under the shared admin namespace.
 */
final class AdminRestApi {

	/**
	 * Admin controllers whose routes are registered.
	 *
	 * @var array<int, AbstractAdminController>
	 */
	private array $controllers;

	/**
	 * Constructor.
	 *
	 * @param array<int, AbstractAdminController> $controllers Admin controllers to register.
	 */
	public function __construct( array $controllers ) {
		$this->controllers = array_values( $controllers );
	}

	/**
	 * Register all admin routes (call on rest_api_init).
	 */
	public function register_routes(): void {
		foreach ( $this->controllers as $controller ) {
			foreach ( $controller->routes() as $route ) {
				$permission_callback = isset( $route['permission_callback'] ) && is_callable( $route['permission_callback'] )
					? $route['permission_callback']
					: array( $controller, 'can_manage' );

				register_rest_route(
					AbstractAdminController::NAMESPACE,
					(string) $route['route'],
					array(
						'methods'             => $route['methods'],
						'callback'            => $route['callback'],
						'permission_callback' => $permission_callback,
						'args'                => isset( $route['args'] ) && is_array( $route['args'] ) ? $route['args'] : array(),
					)
				);
			}
		}
	}
}
