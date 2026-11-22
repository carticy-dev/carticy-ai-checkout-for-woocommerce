<?php
/**
 * Product Feed screen REST controller.
 *
 * Wraps ProductFeedService and ProductQualityChecker so the React Product Feed
 * screen can load the KPI stats and the product list, toggle ChatGPT
 * enablement, preview a single product's feed payload, and trigger feed/quality
 * maintenance — the same operations the server template + jQuery screen used to
 * perform.
 *
 * @package Carticy\AiCheckout\Rest
 */

declare(strict_types=1);

namespace Carticy\AiCheckout\Rest;

use Carticy\AiCheckout\Services\ProductFeedService;
use Carticy\AiCheckout\Services\ProductQualityChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backs the React Product Feed screen.
 */
final class ProductFeedController extends AbstractAdminController {

	/**
	 * Products shown per page (mirrors the legacy WP_List_Table page size).
	 */
	private const PER_PAGE = 20;

	/**
	 * Product feed data service.
	 *
	 * @var ProductFeedService
	 */
	private ProductFeedService $feed_service;

	/**
	 * Product quality checker service.
	 *
	 * @var ProductQualityChecker
	 */
	private ProductQualityChecker $quality_checker;

	/**
	 * Constructor.
	 *
	 * @param ProductFeedService    $feed_service    Product feed data service.
	 * @param ProductQualityChecker $quality_checker Product quality checker service.
	 */
	public function __construct( ProductFeedService $feed_service, ProductQualityChecker $quality_checker ) {
		$this->feed_service    = $feed_service;
		$this->quality_checker = $quality_checker;
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
				'route'    => '/products',
				'callback' => array( $this, 'get_products' ),
				'args'     => array(
					'search'   => array(
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'category' => array(
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'chatgpt'  => array(
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'paged'    => array(
						'sanitize_callback' => 'absint',
						'default'           => 1,
					),
					'per_page' => array(
						'sanitize_callback' => 'absint',
						'default'           => self::PER_PAGE,
					),
				),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/products/bulk',
				'callback' => array( $this, 'bulk_products' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/products/toggle',
				'callback' => array( $this, 'toggle_product' ),
			),
			array(
				'methods'  => 'GET',
				'route'    => '/products/preview',
				'callback' => array( $this, 'preview_product' ),
				'args'     => array(
					'id' => array(
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
				),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/products/regenerate-feed',
				'callback' => array( $this, 'regenerate_feed' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/products/recalculate-quality',
				'callback' => array( $this, 'recalculate_quality' ),
			),
		);
	}

	/**
	 * Return the KPI stats and a page of products for the current filters.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public function get_products( \WP_REST_Request $request ): \WP_REST_Response {
		$search   = (string) $request->get_param( 'search' );
		$category = (string) $request->get_param( 'category' );
		$chatgpt  = (string) $request->get_param( 'chatgpt' );
		$paged    = max( 1, (int) $request->get_param( 'paged' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		if ( $per_page < 1 || $per_page > 100 ) {
			$per_page = self::PER_PAGE;
		}

		// Build the product query (mirrors ProductsListTable::prepare_items):
		// published products with a positive price; optional search, category,
		// and ChatGPT-enablement filters.
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_price',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			),
		);

		if ( 'enabled' === $chatgpt || 'disabled' === $chatgpt ) {
			$meta_query[] = array(
				'key'     => '_carticy_ai_checkout_enabled',
				'value'   => 'enabled' === $chatgpt ? 'yes' : 'no',
				'compare' => '=',
			);
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( '' !== $category ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		$query       = new \WP_Query( $args );
		$total       = (int) $query->found_posts;
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

		$rows = array();
		foreach ( $query->posts as $post ) {
			$row = $this->product_row( (int) $post->ID );
			if ( null !== $row ) {
				$rows[] = $row;
			}
		}

		return $this->ok(
			array(
				'stats'      => $this->stats(),
				'rows'       => $rows,
				'pagination' => array(
					'current_page' => $paged,
					'per_page'     => $per_page,
					'total'        => $total,
					'total_pages'  => $total_pages,
				),
			)
		);
	}

	/**
	 * Enable or disable a set of products for ChatGPT in bulk.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bulk_products( \WP_REST_Request $request ) {
		$body   = $this->body( $request );
		$action = isset( $body['action'] ) ? sanitize_text_field( (string) $body['action'] ) : '';
		$ids    = isset( $body['ids'] ) && is_array( $body['ids'] ) ? array_map( 'absint', $body['ids'] ) : array();
		$ids    = array_values( array_filter( $ids ) );

		if ( ! in_array( $action, array( 'enable', 'disable' ), true ) ) {
			return $this->fail( 'invalid_action', __( 'Invalid bulk action.', 'carticy-ai-checkout-for-woocommerce' ), 400 );
		}

		if ( empty( $ids ) ) {
			return $this->fail( 'no_products', __( 'No products selected.', 'carticy-ai-checkout-for-woocommerce' ), 400 );
		}

		$value = 'enable' === $action ? 'yes' : 'no';
		foreach ( $ids as $product_id ) {
			update_post_meta( $product_id, '_carticy_ai_checkout_enabled', $value );
		}

		// Keep the generated feed consistent with the new enablement state.
		$this->feed_service->invalidate_cache();

		$updated = count( $ids );
		$message = 'enable' === $action
			? sprintf(
				/* translators: %d: number of products */
				_n( '%d product enabled for ChatGPT.', '%d products enabled for ChatGPT.', $updated, 'carticy-ai-checkout-for-woocommerce' ),
				$updated
			)
			: sprintf(
				/* translators: %d: number of products */
				_n( '%d product disabled for ChatGPT.', '%d products disabled for ChatGPT.', $updated, 'carticy-ai-checkout-for-woocommerce' ),
				$updated
			);

		return $this->ok(
			array(
				'message' => $message,
				'updated' => $updated,
			)
		);
	}

	/**
	 * Flip a single product's ChatGPT enablement.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function toggle_product( \WP_REST_Request $request ) {
		$body = $this->body( $request );
		$id   = isset( $body['id'] ) ? absint( $body['id'] ) : 0;

		if ( ! $id ) {
			return $this->fail( 'invalid_product', __( 'Invalid product ID.', 'carticy-ai-checkout-for-woocommerce' ), 400 );
		}

		$enabled     = 'yes' === get_post_meta( $id, '_carticy_ai_checkout_enabled', true );
		$new_enabled = ! $enabled;

		update_post_meta( $id, '_carticy_ai_checkout_enabled', $new_enabled ? 'yes' : 'no' );

		// Keep the generated feed consistent with the new enablement state.
		$this->feed_service->invalidate_cache();

		return $this->ok(
			array(
				'id'      => $id,
				'enabled' => $new_enabled,
			)
		);
	}

	/**
	 * Return the pretty-printed feed payload for a single product (preview).
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview_product( \WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( ! $id ) {
			return $this->fail( 'invalid_product', __( 'Invalid product ID.', 'carticy-ai-checkout-for-woocommerce' ), 400 );
		}

		$product = wc_get_product( $id );
		if ( ! $product ) {
			return $this->fail( 'product_not_found', __( 'Product not found.', 'carticy-ai-checkout-for-woocommerce' ), 404 );
		}

		$feed_data = $this->feed_service->get_product_feed_data( $product );

		return $this->ok( array( 'feed' => (string) wp_json_encode( $feed_data, JSON_PRETTY_PRINT ) ) );
	}

	/**
	 * Invalidate the feed cache so the next request regenerates it.
	 */
	public function regenerate_feed(): \WP_REST_Response {
		$this->feed_service->invalidate_cache();
		update_option( 'carticy_ai_checkout_feed_last_updated', time() );

		return $this->ok( array( 'message' => __( 'Feed cache cleared successfully.', 'carticy-ai-checkout-for-woocommerce' ) ) );
	}

	/**
	 * Recalculate the cached quality scores for the product catalog.
	 */
	public function recalculate_quality(): \WP_REST_Response {
		$count = $this->quality_checker->recalculate_all_quality_scores();

		return $this->ok(
			array(
				'message' => sprintf(
					/* translators: %d: number of products processed */
					_n(
						'%d product quality score recalculated.',
						'%d product quality scores recalculated.',
						$count,
						'carticy-ai-checkout-for-woocommerce'
					),
					$count
				),
			)
		);
	}

	/**
	 * Build a single React-ready product row from a product ID.
	 *
	 * @param int $id Product ID.
	 * @return array<string, mixed>|null Row data, or null if the product is gone.
	 */
	private function product_row( int $id ): ?array {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return null;
		}

		$image_id  = $product->get_image_id();
		$image_url = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		$sku       = $product->get_sku();

		return array(
			'id'             => $id,
			'image'          => $image_url,
			'name'           => $product->get_name(),
			'sku'            => '' !== $sku ? $sku : '',
			// WooCommerce's price markup as it prints in its own product list:
			// sale prices struck through, and screen-reader text that wp-admin hides.
			'price_html'     => wp_kses_post( $product->get_price_html() ),
			'enabled'        => 'yes' === get_post_meta( $id, '_carticy_ai_checkout_enabled', true ),
			'quality_score'  => $this->quality_checker->get_cached_quality_score( $id ),
			'quality_issues' => array_values( $this->quality_checker->get_cached_quality_issues( $id ) ),
		);
	}

	/**
	 * Compute the four KPI numbers for the screen header.
	 *
	 * Replicates ProductFeedManager::get_feed_statistics() (which is private):
	 * published products with a positive price, ChatGPT-enabled count, the
	 * average quality score of enabled products, and the count of enabled
	 * products carrying quality issues.
	 *
	 * @return array<string, int>
	 */
	private function stats(): array {
		global $wpdb;

		// Total published products with a positive price (the feed-eligible set).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_products = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE p.post_type = %s AND p.post_status = %s
				AND pm.meta_key = %s AND CAST( pm.meta_value AS DECIMAL(10,2) ) > 0",
				'product',
				'publish',
				'_price'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$chatgpt_enabled = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE p.post_type = %s AND p.post_status = %s
				AND pm.meta_key = %s AND pm.meta_value = %s",
				'product',
				'publish',
				'_carticy_ai_checkout_enabled',
				'yes'
			)
		);

		// Average quality score across enabled products with a stored score.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg_quality = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG( CAST( pm_quality.meta_value AS UNSIGNED ) )
				FROM {$wpdb->postmeta} pm_quality
				INNER JOIN {$wpdb->posts} p ON pm_quality.post_id = p.ID
				INNER JOIN {$wpdb->postmeta} pm_enabled ON p.ID = pm_enabled.post_id
				WHERE p.post_type = %s AND p.post_status = %s
				AND pm_quality.meta_key = %s AND pm_quality.meta_value != ''
				AND pm_enabled.meta_key = %s AND pm_enabled.meta_value = %s",
				'product',
				'publish',
				'_carticy_ai_checkout_quality_score',
				'_carticy_ai_checkout_enabled',
				'yes'
			)
		);

		// Enabled products carrying a non-empty quality-issues list.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$products_with_issues = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm_issues
				INNER JOIN {$wpdb->posts} p ON pm_issues.post_id = p.ID
				INNER JOIN {$wpdb->postmeta} pm_enabled ON p.ID = pm_enabled.post_id
				WHERE p.post_type = %s AND p.post_status = %s
				AND pm_issues.meta_key = %s AND pm_issues.meta_value != '' AND pm_issues.meta_value != %s
				AND pm_enabled.meta_key = %s AND pm_enabled.meta_value = %s",
				'product',
				'publish',
				'_carticy_ai_checkout_quality_issues',
				'a:0:{}',
				'_carticy_ai_checkout_enabled',
				'yes'
			)
		);

		return array(
			'total_products'       => $total_products,
			'chatgpt_enabled'      => $chatgpt_enabled,
			'avg_quality'          => $avg_quality,
			'products_with_issues' => $products_with_issues,
		);
	}
}
