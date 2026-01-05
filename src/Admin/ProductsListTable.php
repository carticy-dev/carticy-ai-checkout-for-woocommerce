<?php
/**
 * Products List Table
 *
 * WordPress List Table for managing ChatGPT product feed integration.
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Admin;

use Carticy\AiCheckout\Services\ProductQualityChecker;

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Products list table implementation
 */
final class ProductsListTable extends \WP_List_Table {
	/**
	 * Product quality checker service
	 *
	 * @var ProductQualityChecker
	 */
	private ProductQualityChecker $quality_checker;

	/**
	 * Constructor
	 *
	 * @param ProductQualityChecker $quality_checker Quality checker instance.
	 */
	public function __construct( ProductQualityChecker $quality_checker ) {
		$this->quality_checker = $quality_checker;

		parent::__construct(
			array(
				'singular' => 'product',
				'plural'   => 'products',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get table columns
	 *
	 * @return array<string, string> Column definitions.
	 */
	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox" />',
			'image'          => __( 'Image', 'carticy-ai-checkout-for-woocommerce' ),
			'name'           => __( 'Product', 'carticy-ai-checkout-for-woocommerce' ),
			'sku'            => __( 'SKU', 'carticy-ai-checkout-for-woocommerce' ),
			'price'          => __( 'Price', 'carticy-ai-checkout-for-woocommerce' ),
			'chatgpt_status' => __( 'ChatGPT', 'carticy-ai-checkout-for-woocommerce' ),
			'quality'        => __( 'Quality Score', 'carticy-ai-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @return array<string, array<int, string|bool>> Sortable column definitions.
	 */
	protected function get_sortable_columns(): array {
		return array(
			'name'    => array( 'title', false ),
			'sku'     => array( 'sku', false ),
			'price'   => array( 'price', false ),
			'quality' => array( 'quality', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @return array<string, string> Bulk action definitions.
	 */
	protected function get_bulk_actions(): array {
		return array(
			'enable_chatgpt'  => __( 'Enable for ChatGPT', 'carticy-ai-checkout-for-woocommerce' ),
			'disable_chatgpt' => __( 'Disable for ChatGPT', 'carticy-ai-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Render checkbox column
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="product[]" value="%d" />', $item['id'] );
	}

	/**
	 * Render image column
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_image( $item ): string {
		$product = wc_get_product( $item['id'] );
		if ( ! $product ) {
			return '—';
		}

		$image = $product->get_image( 'thumbnail' );
		return $image ? $image : '—';
	}

	/**
	 * Render name column
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_name( $item ): string {
		$product = wc_get_product( $item['id'] );
		if ( ! $product ) {
			return '—';
		}

		$edit_url = get_edit_post_link( $item['id'] );
		$title    = $product->get_name();

		$actions = array(
			'edit'         => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				__( 'Edit', 'carticy-ai-checkout-for-woocommerce' )
			),
			'preview_feed' => sprintf(
				'<a href="#" class="carticy-preview-feed" data-product-id="%d">%s</a>',
				$item['id'],
				__( 'Preview Feed', 'carticy-ai-checkout-for-woocommerce' )
			),
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $title ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render SKU column
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_sku( $item ): string {
		$product = wc_get_product( $item['id'] );
		if ( ! $product ) {
			return '—';
		}

		$sku = $product->get_sku();
		return $sku ? esc_html( $sku ) : '—';
	}

	/**
	 * Render price column
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_price( $item ): string {
		$product = wc_get_product( $item['id'] );
		if ( ! $product ) {
			return '—';
		}

		return $product->get_price_html();
	}

	/**
	 * Render ChatGPT status column
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_chatgpt_status( $item ): string {
		$enabled    = get_post_meta( $item['id'], '_carticy_ai_checkout_enabled', true );
		$is_enabled = 'yes' === $enabled;

		$nonce_url = wp_nonce_url(
			admin_url( 'admin.php?page=carticy-ai-checkout-product-feed&action=toggle&product=' . $item['id'] ),
			'carticy_toggle_product_' . $item['id']
		);

		if ( $is_enabled ) {
			return sprintf(
				'<a href="%s" class="chatgpt-status enabled">✓ %s</a>',
				esc_url( $nonce_url ),
				__( 'Enabled', 'carticy-ai-checkout-for-woocommerce' )
			);
		}

		return sprintf(
			'<a href="%s" class="chatgpt-status disabled">○ %s</a>',
			esc_url( $nonce_url ),
			__( 'Disabled', 'carticy-ai-checkout-for-woocommerce' )
		);
	}

	/**
	 * Render quality column
	 *
	 * @param array<string, mixed> $item Row data.
	 * @return string Column HTML.
	 */
	protected function column_quality( $item ): string {
		$score  = $this->quality_checker->get_cached_quality_score( $item['id'] );
		$issues = $this->quality_checker->get_cached_quality_issues( $item['id'] );

		// Determine quality class.
		if ( $score >= 80 ) {
			$class = 'quality-excellent';
			$label = __( 'Excellent', 'carticy-ai-checkout-for-woocommerce' );
		} elseif ( $score >= 60 ) {
			$class = 'quality-good';
			$label = __( 'Good', 'carticy-ai-checkout-for-woocommerce' );
		} elseif ( $score >= 40 ) {
			$class = 'quality-fair';
			$label = __( 'Fair', 'carticy-ai-checkout-for-woocommerce' );
		} else {
			$class = 'quality-poor';
			$label = __( 'Poor', 'carticy-ai-checkout-for-woocommerce' );
		}

		$output = sprintf(
			'<span class="quality-badge %s">%d%% - %s</span>',
			$class,
			$score,
			$label
		);

		if ( ! empty( $issues ) ) {
			$issues_list = implode( '<br>', array_map( 'esc_html', $issues ) );
			$output     .= sprintf(
				'<span class="quality-issues-toggle" data-issues="%s">⚠ %d %s</span>',
				esc_attr( $issues_list ),
				count( $issues ),
				_n( 'issue', 'issues', count( $issues ), 'carticy-ai-checkout-for-woocommerce' )
			);
		}

		return $output;
	}

	/**
	 * Default column renderer
	 *
	 * @param array<string, mixed> $item        Row data.
	 * @param string               $column_name Column name.
	 * @return string Column HTML.
	 */
	protected function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '—';
	}

	/**
	 * Prepare items for display
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Build query args - exclude $0 products (not supported by ACP).
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_price',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		);

		// Handle search.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WP_List_Table search is a read-only display operation.
		if ( ! empty( $_REQUEST['s'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		// Handle category filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WP_List_Table filter is a read-only display operation.
		if ( ! empty( $_REQUEST['product_cat'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'terms'    => sanitize_text_field( wp_unslash( $_REQUEST['product_cat'] ) ),
				),
			);
		}

		// Handle ChatGPT status filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WP_List_Table filter is a read-only display operation.
		if ( ! empty( $_REQUEST['chatgpt_filter'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filter_value         = sanitize_text_field( wp_unslash( $_REQUEST['chatgpt_filter'] ) );
			$args['meta_query'][] = array(
				'key'     => '_carticy_ai_checkout_enabled',
				'value'   => 'enabled' === $filter_value ? 'yes' : 'no',
				'compare' => '=',
			);
		}

		// Handle sorting.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WP_List_Table sorting is a read-only display operation.
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$orderby = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'asc';

			switch ( $orderby ) {
				case 'title':
					$args['orderby'] = 'title';
					$args['order']   = $order;
					break;
				case 'sku':
					$args['meta_key'] = '_sku';
					$args['orderby']  = 'meta_value';
					$args['order']    = $order;
					break;
				case 'price':
					$args['meta_key'] = '_price';
					$args['orderby']  = 'meta_value_num';
					$args['order']    = $order;
					break;
				case 'quality':
					$args['meta_key'] = '_carticy_ai_checkout_quality_score';
					$args['orderby']  = 'meta_value_num';
					$args['order']    = $order;
					break;
			}
		}

		// Query products.
		$query = new \WP_Query( $args );

		// Get total items for pagination.
		$total_items = $query->found_posts;

		// Prepare items array.
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id' => $post->ID,
			);
		}

		$this->items = $items;

		// Set up pagination.
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		// Set column headers.
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Display extra table navigation
	 *
	 * @param string $which Position (top or bottom).
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		?>
		<div class="alignleft actions">
			<?php
			// Category filter.
			$categories = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
				)
			);

			if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter dropdown preserves selected value (read-only).
				$selected_cat = isset( $_REQUEST['product_cat'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['product_cat'] ) ) : '';
				?>
				<select name="product_cat">
					<option value=""><?php esc_html_e( 'All Categories', 'carticy-ai-checkout-for-woocommerce' ); ?></option>
					<?php foreach ( $categories as $category ) : ?>
						<option value="<?php echo esc_attr( $category->slug ); ?>"
							<?php selected( $selected_cat, $category->slug ); ?>>
							<?php echo esc_html( $category->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php
			}

			// ChatGPT status filter.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter dropdown preserves selected value (read-only).
			$selected_filter = isset( $_REQUEST['chatgpt_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['chatgpt_filter'] ) ) : '';
			?>
			<select name="chatgpt_filter">
				<option value=""><?php esc_html_e( 'All Products', 'carticy-ai-checkout-for-woocommerce' ); ?></option>
				<option value="enabled" <?php selected( $selected_filter, 'enabled' ); ?>>
					<?php esc_html_e( 'ChatGPT Enabled', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</option>
				<option value="disabled" <?php selected( $selected_filter, 'disabled' ); ?>>
					<?php esc_html_e( 'ChatGPT Disabled', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</option>
			</select>

			<?php submit_button( __( 'Filter', 'carticy-ai-checkout-for-woocommerce' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
