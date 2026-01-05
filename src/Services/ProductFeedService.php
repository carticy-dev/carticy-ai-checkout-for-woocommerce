<?php
/**
 * Product Feed Service
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

use WC_Product;
use WC_Product_Variable;

/**
 * Generates product feed in OpenAI Product Feed Spec format
 */
final class ProductFeedService {

	/**
	 * Cache key prefix
	 */
	private const CACHE_PREFIX = 'carticy_ai_checkout_product_feed_';

	/**
	 * Cache duration (15 minutes)
	 */
	private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Generate product feed
	 *
	 * @param string $format Output format (json, csv, xml, tsv).
	 * @return array<int, array<string, mixed>> Product feed data.
	 */
	public function generate_feed( string $format = 'json' ): array {
		// Check cache first.
		$cache_key = self::CACHE_PREFIX . $format;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get enabled product IDs via direct SQL, then load only those products.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$enabled_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE pm.meta_key = %s
				AND pm.meta_value = %s
				AND p.post_type = 'product'
				AND p.post_status = 'publish'",
				'_carticy_ai_checkout_enabled',
				'yes'
			)
		);

		// If no enabled products, return empty feed.
		if ( empty( $enabled_ids ) ) {
			set_transient( $cache_key, array(), self::CACHE_TTL );
			return array();
		}

		// Load only the enabled products.
		$products = wc_get_products(
			array(
				'include' => $enabled_ids,
				'limit'   => -1,
			)
		);

		$feed = array();

		foreach ( $products as $product ) {
			// Skip products with no price or price <= 0.
			$price = (float) $product->get_price();
			if ( $price <= 0 ) {
				continue;
			}

			// Variable products: add variations only (not parent).
			if ( $product->is_type( 'variable' ) && $product instanceof WC_Product_Variable ) {
				$variations = $product->get_available_variations( 'objects' );
				foreach ( $variations as $variation ) {
					$var_price = (float) $variation->get_price();
					if ( $variation->is_purchasable() && $variation->is_in_stock() && $var_price > 0 ) {
						$feed[] = $this->map_product( $variation, $product );
					}
				}
			} elseif ( $product->is_purchasable() ) {
				// Simple products: add the product.
				$feed[] = $this->map_product( $product );
			}
		}

		// Cache the feed.
		set_transient( $cache_key, $feed, self::CACHE_TTL );

		return $feed;
	}

	/**
	 * Map WooCommerce product to OpenAI Product Feed format
	 *
	 * @param WC_Product      $product        Product object.
	 * @param WC_Product|null $parent_product Parent product for variations.
	 * @return array<string, mixed> Mapped product data.
	 */
	private function map_product( WC_Product $product, ?WC_Product $parent_product = null ): array {
		$product_id   = $product->get_id();
		$is_variation = $product->is_type( 'variation' );

		// Build title (include variation attributes if applicable).
		$title = $product->get_name();
		if ( $is_variation && $parent_product ) {
			$title = $parent_product->get_name() . ' - ' . $product->get_name();
		}

		// Get price (WooCommerce get_price() returns sale price if active, otherwise regular price).
		$price = $product->get_price();

		// Get currency.
		$currency = get_woocommerce_currency();

		// Get availability.
		$availability = $this->map_availability( $product );

		// Get image URL.
		$image_id = $product->get_image_id();
		if ( ! $image_id && $parent_product ) {
			$image_id = $parent_product->get_image_id();
		}
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';

		// Get additional images.
		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) && $parent_product ) {
			$gallery_ids = $parent_product->get_gallery_image_ids();
		}
		$additional_images = array_filter(
			array_map(
				function ( $id ) {
					return wp_get_attachment_image_url( $id, 'full' );
				},
				$gallery_ids
			)
		);

		// Get categories.
		$category_ids = $product->get_category_ids();
		if ( empty( $category_ids ) && $parent_product ) {
			$category_ids = $parent_product->get_category_ids();
		}
		$categories = array_map(
			function ( $term_id ) {
				$term = get_term( $term_id, 'product_cat' );
				return $term ? $term->name : '';
			},
			$category_ids
		);
		$categories = array_filter( $categories );

		// Get brand (from product attributes or custom taxonomy).
		$brand = $this->get_product_brand( $product, $parent_product );

		// Build product link.
		$link = get_permalink( $is_variation && $parent_product ? $parent_product->get_id() : $product_id );

		// Get SKU or generate fallback.
		// ACP specification requires SKU as primary identifier.
		$sku = $product->get_sku();
		if ( empty( $sku ) ) {
			$sku = 'PRODUCT-' . $product_id;
			// Log warning for products without SKUs.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'Carticy AI Checkout: Product %d has no SKU, using fallback: %s', $product_id, $sku ) );
			}
		}

		// Map to OpenAI Product Feed Spec.
		// Use SKU as primary identifier per ACP specification.
		$description = $product->get_description();
		$mapped      = array(
			'id'                    => $sku,
			'product_id'            => (string) $product_id,
			'title'                 => $title,
			'description'           => $description ? $description : $product->get_short_description(),
			'link'                  => $link,
			'image_link'            => $image_url,
			'additional_image_link' => array_values( $additional_images ),
			'price'                 => array(
				'value'    => $price,
				'currency' => $currency,
			),
			'availability'          => $availability,
			'condition'             => 'new', // Default to new.
		);

		// Add optional fields if available.
		if ( ! empty( $categories ) ) {
			$mapped['product_type'] = implode( ' > ', $categories );
		}

		if ( $brand ) {
			$mapped['brand'] = $brand;
		}

		// Add weight if available.
		$weight = $product->get_weight();
		if ( $weight ) {
			$mapped['shipping_weight'] = array(
				'value' => (float) $weight,
				'unit'  => get_option( 'woocommerce_weight_unit', 'kg' ),
			);
		}

		// Add SKU in MPN field for compatibility.
		$mapped['mpn'] = $sku;

		// Add variation attributes if applicable.
		if ( $is_variation ) {
			$mapped['item_group_id'] = (string) $product->get_parent_id();

			// Map WooCommerce variation attributes to ACP top-level fields.
			// ACP spec requires variant attributes as top-level fields, NOT nested arrays.
			$this->map_variant_attributes( $product, $mapped );
		}

		return $mapped;
	}

	/**
	 * Map WooCommerce stock status to OpenAI availability
	 *
	 * @param WC_Product $product Product object.
	 * @return string Availability status.
	 */
	private function map_availability( WC_Product $product ): string {
		if ( ! $product->is_in_stock() ) {
			return 'out_of_stock';
		}

		if ( $product->is_on_backorder() ) {
			return 'preorder';
		}

		return 'in_stock';
	}

	/**
	 * Get product brand from attributes or taxonomy
	 *
	 * @param WC_Product      $product        Product object.
	 * @param WC_Product|null $parent_product Parent product for variations.
	 * @return string Brand name or empty string.
	 */
	private function get_product_brand( WC_Product $product, ?WC_Product $parent_product = null ): string {
		// Try custom brand taxonomy first.
		if ( taxonomy_exists( 'product_brand' ) ) {
			$terms = get_the_terms( $product->get_id(), 'product_brand' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$brand_term = reset( $terms );
				return $brand_term->name;
			}

			// Try parent for variations.
			if ( $parent_product ) {
				$terms = get_the_terms( $parent_product->get_id(), 'product_brand' );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$brand_term = reset( $terms );
					return $brand_term->name;
				}
			}
		}

		// Try brand attribute using get_attribute() method (works for all product types).
		// This returns a string for both simple products and variations.
		$brand_value = $product->get_attribute( 'brand' );
		if ( ! empty( $brand_value ) ) {
			return $brand_value;
		}

		// Try parent attributes for variations.
		if ( $parent_product ) {
			$brand_value = $parent_product->get_attribute( 'brand' );
			if ( ! empty( $brand_value ) ) {
				return $brand_value;
			}
		}

		return '';
	}

	/**
	 * Map WooCommerce variation attributes to ACP top-level fields
	 *
	 * ACP requires variant attributes as top-level fields (color, size, etc.)
	 * NOT as a nested array. This method maps WooCommerce attributes to ACP fields.
	 *
	 * @param WC_Product           $product Product object (variation).
	 * @param array<string, mixed> $mapped Mapped product array (passed by reference).
	 * @return void
	 */
	private function map_variant_attributes( WC_Product $product, array &$mapped ): void {
		$variation_attributes = $product->get_variation_attributes();

		// ACP standard variant fields mapping.
		$standard_fields = array(
			'color'     => array( 'pa_color', 'pa_colour', 'color', 'colour' ),
			'size'      => array( 'pa_size', 'size' ),
			'pattern'   => array( 'pa_pattern', 'pattern' ),
			'gender'    => array( 'pa_gender', 'gender' ),
			'age_group' => array( 'pa_age_group', 'age_group' ),
			'material'  => array( 'pa_material', 'material' ),
		);

		$custom_variant_index = 1;

		foreach ( $variation_attributes as $attr_name => $attr_value ) {
			if ( empty( $attr_value ) ) {
				continue;
			}

			// Remove 'attribute_' prefix from WooCommerce attribute names.
			$clean_name = str_replace( 'attribute_', '', strtolower( $attr_name ) );

			// Check if this maps to a standard ACP field.
			$mapped_to_standard = false;
			foreach ( $standard_fields as $acp_field => $possible_names ) {
				if ( in_array( $clean_name, $possible_names, true ) ) {
					$mapped[ $acp_field ] = $attr_value;
					$mapped_to_standard   = true;
					break;
				}
			}

			// If not a standard field, use custom variant fields (max 3).
			if ( ! $mapped_to_standard && $custom_variant_index <= 3 ) {
				// Get human-readable attribute label.
				// wc_attribute_label() expects the clean attribute name (without 'attribute_' prefix).
				$attr_label = wc_attribute_label( $clean_name );

				// If label is same as slug, create a better human-readable name.
				if ( $attr_label === $clean_name || $attr_label === $attr_name ) {
					// Remove 'pa_' prefix and convert to title case.
					$attr_label = str_replace( 'pa_', '', $clean_name );
					$attr_label = ucwords( str_replace( array( '-', '_' ), ' ', $attr_label ) );
				}

				$mapped[ "custom_variant{$custom_variant_index}_category" ] = $attr_label;
				$mapped[ "custom_variant{$custom_variant_index}_option" ]   = $attr_value;
				++$custom_variant_index;
			}
		}
	}

	/**
	 * Get feed data for a single product (for preview)
	 *
	 * @param WC_Product $product Product object.
	 * @return array<string, mixed> Product feed data.
	 */
	public function get_product_feed_data( WC_Product $product ): array {
		return $this->map_product( $product );
	}

	/**
	 * Invalidate product feed cache
	 *
	 * @return void
	 */
	public function invalidate_cache(): void {
		delete_transient( self::CACHE_PREFIX . 'json' );
		delete_transient( self::CACHE_PREFIX . 'csv' );
		delete_transient( self::CACHE_PREFIX . 'xml' );
		delete_transient( self::CACHE_PREFIX . 'tsv' );
		update_option( 'carticy_ai_checkout_feed_last_updated', time() );
	}
}
