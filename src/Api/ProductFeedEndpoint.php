<?php
/**
 * Product Feed REST API Endpoint
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Api;

use Carticy\AiCheckout\Services\ProductFeedService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles product feed REST API endpoint
 */
final class ProductFeedEndpoint {
	/**
	 * API namespace
	 */
	private const NAMESPACE = 'carticy-ai-checkout/v1';

	/**
	 * Product feed service
	 *
	 * @var ProductFeedService
	 */
	private ProductFeedService $feed_service;

	/**
	 * Constructor
	 *
	 * @param ProductFeedService $feed_service Product feed service instance.
	 */
	public function __construct( ProductFeedService $feed_service ) {
		$this->feed_service = $feed_service;
		$this->register_routes();
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => '__return_true', // Public endpoint for OpenAI.
				'args'                => array(
					'format' => array(
						'type'              => 'string',
						'default'           => 'json',
						'enum'              => array( 'json', 'csv', 'xml', 'tsv' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get product feed
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$format_param = $request->get_param( 'format' );
		$format       = $format_param ? $format_param : 'json';

		try {
			$feed = $this->feed_service->generate_feed( $format );

			// Return based on format.
			switch ( $format ) {
				case 'json':
					return rest_ensure_response( $feed );

				case 'csv':
					return $this->format_as_csv( $feed );

				case 'tsv':
					return $this->format_as_tsv( $feed );

				case 'xml':
					return $this->format_as_xml( $feed );

				default:
					return rest_ensure_response( $feed );
			}
		} catch ( \Exception $e ) {
			return new WP_Error(
				'feed_generation_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Format feed as CSV
	 *
	 * @param array<int, array<string, mixed>> $feed Product feed data.
	 * @return WP_REST_Response Response with CSV data.
	 */
	private function format_as_csv( array $feed ): WP_REST_Response {
		if ( empty( $feed ) ) {
			return new WP_REST_Response( '', 200, array( 'Content-Type' => 'text/csv' ) );
		}

		// Get headers from first product.
		$headers = array_keys( $this->flatten_product( $feed[0] ) );

		// Build CSV as string array.
		$lines   = array();
		$lines[] = $this->array_to_csv_line( $headers );

		foreach ( $feed as $product ) {
			$lines[] = $this->array_to_csv_line( array_values( $this->flatten_product( $product ) ) );
		}

		$output = implode( "\n", $lines );

		return new WP_REST_Response(
			$output,
			200,
			array(
				'Content-Type'        => 'text/csv',
				'Content-Disposition' => 'attachment; filename="products.csv"',
			)
		);
	}

	/**
	 * Convert array to CSV line string.
	 *
	 * @param array<int, mixed> $fields Array of field values.
	 * @return string CSV formatted line.
	 */
	private function array_to_csv_line( array $fields ): string {
		$escaped = array_map(
			function ( $field ) {
				$field = (string) $field;
				// Escape quotes and wrap in quotes if contains comma, quote, or newline.
				if ( strpos( $field, ',' ) !== false || strpos( $field, '"' ) !== false || strpos( $field, "\n" ) !== false ) {
					return '"' . str_replace( '"', '""', $field ) . '"';
				}
				return $field;
			},
			$fields
		);
		return implode( ',', $escaped );
	}

	/**
	 * Format feed as TSV
	 *
	 * @param array<int, array<string, mixed>> $feed Product feed data.
	 * @return WP_REST_Response Response with TSV data.
	 */
	private function format_as_tsv( array $feed ): WP_REST_Response {
		if ( empty( $feed ) ) {
			return new WP_REST_Response( '', 200, array( 'Content-Type' => 'text/tab-separated-values' ) );
		}

		// Get headers from first product.
		$headers = array_keys( $this->flatten_product( $feed[0] ) );

		// Build TSV.
		$rows   = array();
		$rows[] = implode( "\t", $headers );

		foreach ( $feed as $product ) {
			$rows[] = implode( "\t", array_values( $this->flatten_product( $product ) ) );
		}

		$output = implode( "\n", $rows );

		return new WP_REST_Response(
			$output,
			200,
			array(
				'Content-Type'        => 'text/tab-separated-values',
				'Content-Disposition' => 'attachment; filename="products.tsv"',
			)
		);
	}

	/**
	 * Format feed as XML
	 *
	 * @param array<int, array<string, mixed>> $feed Product feed data.
	 * @return WP_REST_Response Response with XML data.
	 */
	private function format_as_xml( array $feed ): WP_REST_Response {
		$xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><products></products>' );

		foreach ( $feed as $product_data ) {
			$product_node = $xml->addChild( 'product' );
			$this->array_to_xml( $product_data, $product_node );
		}

		return new WP_REST_Response(
			$xml->asXML(),
			200,
			array(
				'Content-Type'        => 'application/xml',
				'Content-Disposition' => 'attachment; filename="products.xml"',
			)
		);
	}

	/**
	 * Flatten nested product data for CSV/TSV
	 *
	 * @param array<string, mixed> $product Product data.
	 * @return array<string, string> Flattened product data.
	 */
	private function flatten_product( array $product ): array {
		$flattened = array();

		foreach ( $product as $key => $value ) {
			if ( is_array( $value ) ) {
				// Handle nested arrays.
				if ( isset( $value['value'] ) && isset( $value['currency'] ) ) {
					// Price object.
					$flattened[ $key . '_value' ]    = (string) $value['value'];
					$flattened[ $key . '_currency' ] = (string) $value['currency'];
				} elseif ( isset( $value['value'] ) && isset( $value['unit'] ) ) {
					// Weight object.
					$flattened[ $key . '_value' ] = (string) $value['value'];
					$flattened[ $key . '_unit' ]  = (string) $value['unit'];
				} else {
					// Array of values (e.g., additional images).
					$flattened[ $key ] = implode( '|', array_map( 'strval', $value ) );
				}
			} else {
				$flattened[ $key ] = (string) $value;
			}
		}

		return $flattened;
	}

	/**
	 * Convert array to XML recursively
	 *
	 * @param array<string, mixed> $data Data to convert.
	 * @param \SimpleXMLElement    $xml XML element to append to.
	 * @return void
	 */
	private function array_to_xml( array $data, \SimpleXMLElement $xml ): void {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				// Check if it's a numeric array (list).
				if ( array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
					foreach ( $value as $item ) {
						if ( is_array( $item ) ) {
							$child = $xml->addChild( $key );
							$this->array_to_xml( $item, $child );
						} else {
							$xml->addChild( $key, htmlspecialchars( (string) $item ) );
						}
					}
				} else {
					$child = $xml->addChild( $key );
					$this->array_to_xml( $value, $child );
				}
			} else {
				$xml->addChild( $key, htmlspecialchars( (string) $value ) );
			}
		}
	}
}
