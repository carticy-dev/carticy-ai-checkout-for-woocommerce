<?php
/**
 * Service Container
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Core;

/**
 * Simple dependency injection container
 */
class Container {
	/**
	 * Instantiated services
	 *
	 * @var array<string, mixed>
	 */
	private array $services = array();

	/**
	 * Service factory callables
	 *
	 * @var array<string, callable>
	 */
	private array $factories = array();

	/**
	 * Register a service factory
	 *
	 * @param string   $id Service identifier.
	 * @param callable $factory Factory callable that returns the service instance.
	 * @return void
	 */
	public function register( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
	}

	/**
	 * Get a service instance
	 *
	 * @param string $id Service identifier.
	 * @return mixed Service instance.
	 * @throws \Exception If service not found.
	 */
	public function get( string $id ): mixed {
		if ( ! isset( $this->services[ $id ] ) ) {
			if ( ! isset( $this->factories[ $id ] ) ) {
				throw new \Exception( sprintf( 'Service %s not found', esc_html( $id ) ) );
			}
			$this->services[ $id ] = $this->factories[ $id ]( $this );
		}
		return $this->services[ $id ];
	}

	/**
	 * Check if a service exists
	 *
	 * @param string $id Service identifier.
	 * @return bool True if service exists.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || isset( $this->services[ $id ] );
	}
}
