<?php
/**
 * Product Quality Checker Service
 *
 * Validates WooCommerce product data quality for ChatGPT integration.
 * Checks required fields, calculates quality scores, and provides recommendations.
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

/**
 * Product quality validation and scoring service
 */
final class ProductQualityChecker {
	/**
	 * Check all quality requirements for a product
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @return array<string, mixed> Quality check results with score and issues.
	 */
	public function check_product_quality( \WC_Product $product ): array {
		$checks = array(
			'has_title'           => $this->check_has_title( $product ),
			'has_description'     => $this->check_has_description( $product ),
			'has_price'           => $this->check_has_price( $product ),
			'has_image'           => $this->check_has_image( $product ),
			'has_category'        => $this->check_has_category( $product ),
			'has_brand'           => $this->check_has_brand( $product ),
			'description_length'  => $this->check_description_length( $product ),
			'has_multiple_images' => $this->check_has_multiple_images( $product ),
		);

		$score  = $this->calculate_quality_score( $checks );
		$issues = $this->generate_issues_list( $checks );

		return array(
			'score'  => $score,
			'issues' => $issues,
			'checks' => $checks,
		);
	}

	/**
	 * Check if product has title
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{passed: bool, message: string} Check result.
	 */
	private function check_has_title( \WC_Product $product ): array {
		$title  = $product->get_name();
		$passed = ! empty( $title );

		return array(
			'passed'  => $passed,
			'message' => $passed
				? __( 'Has title', 'carticy-ai-checkout-for-woocommerce' )
				: __( 'Missing title (required)', 'carticy-ai-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Check if product has description
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{passed: bool, message: string} Check result.
	 */
	private function check_has_description( \WC_Product $product ): array {
		$description = $product->get_description();
		$passed      = ! empty( $description );

		return array(
			'passed'  => $passed,
			'message' => $passed
				? __( 'Has description', 'carticy-ai-checkout-for-woocommerce' )
				: __( 'Missing description (recommended)', 'carticy-ai-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Check if product has price
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{passed: bool, message: string} Check result.
	 */
	private function check_has_price( \WC_Product $product ): array {
		$price  = $product->get_regular_price();
		$passed = ! empty( $price ) && $price > 0;

		return array(
			'passed'  => $passed,
			'message' => $passed
				? __( 'Has price', 'carticy-ai-checkout-for-woocommerce' )
				: __( 'Missing price (required)', 'carticy-ai-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Check if product has featured image
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{passed: bool, message: string} Check result.
	 */
	private function check_has_image( \WC_Product $product ): array {
		$image_id = $product->get_image_id();
		$passed   = ! empty( $image_id );

		return array(
			'passed'  => $passed,
			'message' => $passed
				? __( 'Has featured image', 'carticy-ai-checkout-for-woocommerce' )
				: __( 'Missing featured image (required)', 'carticy-ai-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Check if product has category
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{passed: bool, message: string} Check result.
	 */
	private function check_has_category( \WC_Product $product ): array {
		$category_ids = $product->get_category_ids();
		$passed       = ! empty( $category_ids );

		return array(
			'passed'  => $passed,
			'message' => $passed
				? __( 'Has category', 'carticy-ai-checkout-for-woocommerce' )
				: __( 'Missing category (recommended)', 'carticy-ai-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Check if product has brand attribute
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{passed: bool, message: string} Check result.
	 */
	private function check_has_brand( \WC_Product $product ): array {
		$has_brand = false;

		// Check for brand attribute using get_attribute() method (works for all product types).
		// Common brand attribute names.
		$brand_names = array( 'brand', 'manufacturer', 'pa_brand' );

		foreach ( $brand_names as $brand_name ) {
			$brand_value = $product->get_attribute( $brand_name );
			if ( ! empty( $brand_value ) ) {
				$has_brand = true;
				break;
			}
		}

		// Also check custom brand taxonomy if it exists.
		if ( ! $has_brand && taxonomy_exists( 'product_brand' ) ) {
			$terms = get_the_terms( $product->get_id(), 'product_brand' );
			if ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$has_brand = true;
			}
		}

		return array(
			'passed'  => $has_brand,
			'message' => $has_brand
				? __( 'Has brand attribute', 'carticy-ai-checkout-for-woocommerce' )
				: __( 'Missing brand attribute (optional)', 'carticy-ai-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Check if description meets minimum length
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{passed: bool, message: string} Check result.
	 */
	private function check_description_length( \WC_Product $product ): array {
		$description    = $product->get_description();
		$min_length     = 50;
		$current_length = strlen( $description );
		$passed         = $current_length >= $min_length;

		return array(
			'passed'  => $passed,
			'message' => $passed
				? sprintf(
					/* translators: %d: character count */
					__( 'Description is %d characters', 'carticy-ai-checkout-for-woocommerce' ),
					$current_length
				)
				: sprintf(
					/* translators: 1: current count, 2: minimum count */
					__( 'Description too short (%1$d/%2$d characters)', 'carticy-ai-checkout-for-woocommerce' ),
					$current_length,
					$min_length
				),
		);
	}

	/**
	 * Check if product has multiple images
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{passed: bool, message: string} Check result.
	 */
	private function check_has_multiple_images( \WC_Product $product ): array {
		$gallery_ids = $product->get_gallery_image_ids();
		$image_count = count( $gallery_ids ) + ( $product->get_image_id() ? 1 : 0 );
		$passed      = $image_count > 1;

		return array(
			'passed'  => $passed,
			'message' => $passed
				? sprintf(
					/* translators: %d: image count */
					__( 'Has %d images', 'carticy-ai-checkout-for-woocommerce' ),
					$image_count
				)
				: __( 'Only 1 image (multiple recommended)', 'carticy-ai-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Calculate weighted quality score (0-100)
	 *
	 * @param array<string, array{passed: bool, message: string}> $checks Quality check results.
	 * @return int Quality score 0-100.
	 */
	private function calculate_quality_score( array $checks ): int {
		$weights = array(
			'has_title'           => 20,
			'has_price'           => 20,
			'has_image'           => 20,
			'has_description'     => 15,
			'description_length'  => 10,
			'has_category'        => 10,
			'has_brand'           => 5,
			'has_multiple_images' => 0, // Bonus, doesn't affect score.
		);

		$score = 0;
		foreach ( $weights as $check_name => $weight ) {
			if ( isset( $checks[ $check_name ] ) && $checks[ $check_name ]['passed'] ) {
				$score += $weight;
			}
		}

		return min( 100, max( 0, $score ) );
	}

	/**
	 * Generate list of issues for display
	 *
	 * @param array<string, array{passed: bool, message: string}> $checks Quality check results.
	 * @return string[] Array of issue messages.
	 */
	private function generate_issues_list( array $checks ): array {
		$issues = array();

		foreach ( $checks as $check_name => $check ) {
			if ( ! $check['passed'] ) {
				$issues[] = $check['message'];
			}
		}

		return $issues;
	}

	/**
	 * Calculate and cache quality score for product
	 *
	 * Updates product meta with quality score and issues.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return void
	 */
	public function update_product_quality_cache( int $product_id ): void {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		$quality = $this->check_product_quality( $product );

		// Update product meta.
		update_post_meta( $product_id, '_carticy_ai_checkout_quality_score', $quality['score'] );
		update_post_meta( $product_id, '_carticy_ai_checkout_quality_issues', $quality['issues'] );
	}

	/**
	 * Get cached quality score for product
	 *
	 * @param int $product_id Product ID.
	 * @return int Quality score 0-100.
	 */
	public function get_cached_quality_score( int $product_id ): int {
		$score = get_post_meta( $product_id, '_carticy_ai_checkout_quality_score', true );

		if ( '' === $score ) {
			// No cache, calculate and cache.
			$this->update_product_quality_cache( $product_id );
			$score = get_post_meta( $product_id, '_carticy_ai_checkout_quality_score', true );
		}

		return (int) $score;
	}

	/**
	 * Get cached quality issues for product
	 *
	 * @param int $product_id Product ID.
	 * @return string[] Array of issues.
	 */
	public function get_cached_quality_issues( int $product_id ): array {
		$issues = get_post_meta( $product_id, '_carticy_ai_checkout_quality_issues', true );

		if ( ! is_array( $issues ) ) {
			// No cache, calculate and cache.
			$this->update_product_quality_cache( $product_id );
			$issues = get_post_meta( $product_id, '_carticy_ai_checkout_quality_issues', true );
		}

		return is_array( $issues ) ? $issues : array();
	}

	/**
	 * Bulk recalculate quality scores for all products
	 *
	 * @param int $batch_size Number of products to process per batch.
	 * @return int Number of products processed.
	 */
	public function recalculate_all_quality_scores( int $batch_size = 50 ): int {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $batch_size,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$product_ids = get_posts( $args );
		$count       = 0;

		foreach ( $product_ids as $product_id ) {
			$this->update_product_quality_cache( $product_id );
			++$count;
		}

		return $count;
	}
}
