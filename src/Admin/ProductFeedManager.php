<?php
/**
 * Product Feed Manager
 *
 * Admin interface for managing ChatGPT product feed integration.
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Admin;

use Carticy\AiCheckout\Services\ProductFeedService;
use Carticy\AiCheckout\Services\ProductQualityChecker;

/**
 * Product feed management admin page
 */
final class ProductFeedManager {
	/**
	 * Product feed service
	 *
	 * @var ProductFeedService
	 */
	private ProductFeedService $feed_service;

	/**
	 * Product quality checker
	 *
	 * @var ProductQualityChecker
	 */
	private ProductQualityChecker $quality_checker;

	/**
	 * Products list table
	 *
	 * @var ProductsListTable|null
	 */
	private ?ProductsListTable $list_table = null;

	/**
	 * Constructor
	 *
	 * @param ProductFeedService    $feed_service     Feed service instance.
	 * @param ProductQualityChecker $quality_checker Quality checker instance.
	 */
	public function __construct( ProductFeedService $feed_service, ProductQualityChecker $quality_checker ) {
		$this->feed_service    = $feed_service;
		$this->quality_checker = $quality_checker;

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Add AJAX handlers.
		add_action( 'wp_ajax_carticy_ai_checkout_preview_product_feed', array( $this, 'ajax_preview_product_feed' ) );
		add_action( 'wp_ajax_carticy_ai_checkout_regenerate_feed', array( $this, 'ajax_regenerate_feed' ) );
		add_action( 'wp_ajax_carticy_ai_checkout_recalculate_quality', array( $this, 'ajax_recalculate_quality' ) );

		// Add meta box to product edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_product_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_product_meta' ) );

		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Render product feed manager page
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle bulk actions.
		$this->handle_bulk_actions();

		// Handle single product toggle.
		$this->handle_product_toggle();

		// Create list table instance.
		$this->list_table = new ProductsListTable( $this->quality_checker );
		$this->list_table->prepare_items();

		// Get statistics.
		$stats = $this->get_feed_statistics();

		?>
		<div class="wrap carticy-ai-checkout-products">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Product Feed Manager', 'carticy-ai-checkout-for-woocommerce' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=carticy-ai-checkout' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Back to Settings', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php $this->display_admin_notices(); ?>

			<!-- Statistics Dashboard -->
			<div class="feed-statistics">
				<div class="stats-grid">
					<div class="stat-box">
						<div class="stat-number"><?php echo esc_html( $stats['total_products'] ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Total Products', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
					</div>
					<div class="stat-box">
						<div class="stat-number"><?php echo esc_html( $stats['chatgpt_enabled'] ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'ChatGPT Enabled', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
					</div>
					<div class="stat-box">
						<div class="stat-number"><?php echo esc_html( $stats['avg_quality'] ); ?>%</div>
						<div class="stat-label"><?php esc_html_e( 'Avg Quality Score', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
					</div>
					<div class="stat-box">
						<div class="stat-number"><?php echo esc_html( $stats['products_with_issues'] ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Products with Issues', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
					</div>
				</div>

				<div class="feed-actions">
					<button type="button" class="button button-secondary" id="carticy-regenerate-feed">
						<?php esc_html_e( 'Regenerate Feed', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="carticy-recalculate-quality">
						<?php esc_html_e( 'Recalculate Quality Scores', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</button>

					<?php if ( ! empty( $stats['feed_last_updated'] ) ) : ?>
						<p class="feed-last-updated">
							<?php
							printf(
								/* translators: %s: human-readable time difference */
								esc_html__( 'Feed last updated: %s ago', 'carticy-ai-checkout-for-woocommerce' ),
								esc_html( human_time_diff( $stats['feed_last_updated'] ) )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Products List -->
			<form method="get">
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserving page parameter for form submission. ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( isset( $_REQUEST['page'] ) ? sanitize_key( $_REQUEST['page'] ) : '' ); ?>" />
				<?php
				$this->list_table->search_box( __( 'Search Products', 'carticy-ai-checkout-for-woocommerce' ), 'product' );
				$this->list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle bulk actions
	 *
	 * @return void
	 */
	private function handle_bulk_actions(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_REQUEST['action'] ) && empty( $_REQUEST['action2'] ) ) {
			return;
		}

		$action = ! empty( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action']
			? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )
			: sanitize_text_field( wp_unslash( $_REQUEST['action2'] ?? '' ) );

		if ( empty( $action ) || '-1' === $action ) {
			return;
		}

		if ( empty( $_REQUEST['product'] ) || ! is_array( $_REQUEST['product'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Verify nonce.
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-products' ) ) {
			wp_die( esc_html__( 'Security check failed', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		$product_ids = array_map( 'absint', wp_unslash( $_REQUEST['product'] ) );

		switch ( $action ) {
			case 'enable_chatgpt':
				foreach ( $product_ids as $product_id ) {
					update_post_meta( $product_id, '_carticy_ai_checkout_enabled', 'yes' );
				}
				add_settings_error(
					'carticy_product_feed',
					'products_enabled',
					sprintf(
						/* translators: %d: number of products */
						_n(
							'%d product enabled for ChatGPT.',
							'%d products enabled for ChatGPT.',
							count( $product_ids ),
							'carticy-ai-checkout-for-woocommerce'
						),
						count( $product_ids )
					),
					'success'
				);
				break;

			case 'disable_chatgpt':
				foreach ( $product_ids as $product_id ) {
					update_post_meta( $product_id, '_carticy_ai_checkout_enabled', 'no' );
				}
				add_settings_error(
					'carticy_product_feed',
					'products_disabled',
					sprintf(
						/* translators: %d: number of products */
						_n(
							'%d product disabled for ChatGPT.',
							'%d products disabled for ChatGPT.',
							count( $product_ids ),
							'carticy-ai-checkout-for-woocommerce'
						),
						count( $product_ids )
					),
					'success'
				);
				break;
		}

		// Invalidate feed cache after bulk actions.
		$this->feed_service->invalidate_cache();

		// Redirect to clean URL.
		wp_safe_redirect(
			remove_query_arg( array( 'action', 'action2', 'product', '_wpnonce', '_wp_http_referer' ) )
		);
		exit;
	}

	/**
	 * Handle single product toggle
	 *
	 * @return void
	 */
	private function handle_product_toggle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['action'] ) || 'toggle' !== $_GET['action'] || empty( $_GET['product'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$product_id = absint( $_GET['product'] );

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_toggle_product_' . $product_id ) ) {
			wp_die( esc_html__( 'Security check failed', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		$current_status = get_post_meta( $product_id, '_carticy_ai_checkout_enabled', true );
		$new_status     = 'yes' === $current_status ? 'no' : 'yes';

		update_post_meta( $product_id, '_carticy_ai_checkout_enabled', $new_status );

		// Invalidate feed cache.
		$this->feed_service->invalidate_cache();

		// Redirect to clean URL.
		wp_safe_redirect(
			remove_query_arg( array( 'action', 'product', '_wpnonce' ) )
		);
		exit;
	}

	/**
	 * Get feed statistics
	 *
	 * @return array<string, mixed> Statistics data.
	 */
	private function get_feed_statistics(): array {
		global $wpdb;

		// Total products.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_products = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
		);

		// ChatGPT enabled products.
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

		// Average quality score - only for ChatGPT-enabled products.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg_quality = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CAST(pm_quality.meta_value AS UNSIGNED))
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

		// Products with quality issues (score < 80) - only ChatGPT-enabled products.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$products_with_issues = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm_quality
				INNER JOIN {$wpdb->posts} p ON pm_quality.post_id = p.ID
				INNER JOIN {$wpdb->postmeta} pm_enabled ON p.ID = pm_enabled.post_id
				WHERE p.post_type = %s AND p.post_status = %s
				AND pm_quality.meta_key = %s AND CAST(pm_quality.meta_value AS UNSIGNED) < 80
				AND pm_enabled.meta_key = %s AND pm_enabled.meta_value = %s",
				'product',
				'publish',
				'_carticy_ai_checkout_quality_score',
				'_carticy_ai_checkout_enabled',
				'yes'
			)
		);

		// Feed last updated.
		$feed_last_updated = get_option( 'carticy_ai_checkout_feed_last_updated', 0 );

		return array(
			'total_products'       => $total_products,
			'chatgpt_enabled'      => $chatgpt_enabled,
			'avg_quality'          => $avg_quality,
			'products_with_issues' => $products_with_issues,
			'feed_last_updated'    => $feed_last_updated,
		);
	}

	/**
	 * Display admin notices
	 *
	 * @return void
	 */
	private function display_admin_notices(): void {
		settings_errors( 'carticy_product_feed' );
	}

	/**
	 * Add meta box to product edit screen
	 *
	 * @return void
	 */
	public function add_product_meta_box(): void {
		add_meta_box(
			'carticy_chatgpt_settings',
			__( 'ChatGPT Integration', 'carticy-ai-checkout-for-woocommerce' ),
			array( $this, 'render_product_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render product meta box
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_product_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'carticy_save_product_meta', 'carticy_product_meta_nonce' );

		$enabled       = get_post_meta( $post->ID, '_carticy_ai_checkout_enabled', true );
		$score         = $this->quality_checker->get_cached_quality_score( $post->ID );
		$issues        = $this->quality_checker->get_cached_quality_issues( $post->ID );
		$quality_class = $score >= 80 ? 'quality-excellent' : ( $score >= 60 ? 'quality-good' : 'quality-fair' );
		$issues_count  = count( $issues );

		?>
		<div class="carticy-ai-checkout-product-meta-box">
			<label class="carticy-ai-checkout-meta-checkbox">
				<input type="checkbox" name="carticy_chatgpt_enabled" value="yes"
					<?php checked( $enabled, 'yes' ); ?> />
				<?php esc_html_e( 'Enable for ChatGPT', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</label>

			<div class="carticy-ai-checkout-meta-divider"></div>

			<div class="carticy-ai-checkout-meta-quality">
				<div class="carticy-ai-checkout-meta-row">
					<span class="carticy-ai-checkout-meta-label"><?php esc_html_e( 'Quality Score:', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="carticy-ai-checkout-quality-score <?php echo esc_attr( $quality_class ); ?>">
						<?php echo esc_html( $score ); ?>%
					</span>
				</div>

				<?php if ( ! empty( $issues ) ) : ?>
					<details class="carticy-ai-checkout-meta-details">
						<summary>
							<span class="dashicons dashicons-warning"></span>
							<?php
							printf(
								/* translators: %d: number of issues */
								esc_html( _n( '%d issue to fix', '%d issues to fix', $issues_count, 'carticy-ai-checkout-for-woocommerce' ) ),
								absint( $issues_count )
							);
							?>
						</summary>
						<ul class="carticy-ai-checkout-issues-list">
							<?php foreach ( $issues as $issue ) : ?>
								<li><?php echo esc_html( $issue ); ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
			</div>

			<div class="carticy-ai-checkout-meta-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=carticy-ai-checkout-product-feed' ) ); ?>">
					<?php esc_html_e( 'Manage in Product Feed', 'carticy-ai-checkout-for-woocommerce' ); ?> &rarr;
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product meta
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_product_meta( int $post_id ): void {
		// Verify nonce.
		if ( ! isset( $_POST['carticy_product_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['carticy_product_meta_nonce'] ) ), 'carticy_save_product_meta' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save enabled status.
		$enabled = isset( $_POST['carticy_chatgpt_enabled'] ) && 'yes' === $_POST['carticy_chatgpt_enabled'] ? 'yes' : 'no';
		update_post_meta( $post_id, '_carticy_ai_checkout_enabled', $enabled );

		// Recalculate quality score.
		$this->quality_checker->update_product_quality_cache( $post_id );

		// Invalidate feed cache.
		$this->feed_service->invalidate_cache();
	}

	/**
	 * AJAX handler for feed preview
	 *
	 * @return void
	 */
	public function ajax_preview_product_feed(): void {
		check_ajax_referer( 'carticy_product_feed_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID', 'carticy-ai-checkout-for-woocommerce' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found', 'carticy-ai-checkout-for-woocommerce' ) ) );
		}

		// Get single product feed data.
		$feed_data = $this->feed_service->get_product_feed_data( $product );

		wp_send_json_success( array( 'feed' => wp_json_encode( $feed_data, JSON_PRETTY_PRINT ) ) );
	}

	/**
	 * AJAX handler for feed regeneration
	 *
	 * @return void
	 */
	public function ajax_regenerate_feed(): void {
		check_ajax_referer( 'carticy_product_feed_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) ) );
		}

		$this->feed_service->invalidate_cache();
		update_option( 'carticy_ai_checkout_feed_last_updated', time() );

		wp_send_json_success( array( 'message' => __( 'Feed cache cleared successfully', 'carticy-ai-checkout-for-woocommerce' ) ) );
	}

	/**
	 * AJAX handler for quality score recalculation
	 *
	 * @return void
	 */
	public function ajax_recalculate_quality(): void {
		check_ajax_referer( 'carticy_product_feed_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) ) );
		}

		$count = $this->quality_checker->recalculate_all_quality_scores();

		wp_send_json_success(
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
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Check if we're on product feed manager page OR product edit screen.
		$is_feed_page = 'ai-checkout_page_carticy-ai-checkout-product-feed' === $hook;

		// Check for product edit screen using get_current_screen().
		$screen          = get_current_screen();
		$is_product_edit = ( 'post.php' === $hook || 'post-new.php' === $hook )
			&& $screen
			&& 'product' === $screen->post_type;

		if ( ! $is_feed_page && ! $is_product_edit ) {
			return;
		}

		// Always enqueue styles (needed for both pages).
		wp_enqueue_style(
			'carticy-product-manager',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/dist/admin-product-manager.min.css',
			array(),
			CARTICY_AI_CHECKOUT_VERSION
		);

		// Only enqueue scripts on feed manager page (not needed on product edit).
		if ( ! $is_feed_page ) {
			return;
		}

		// Enqueue scripts.
		wp_enqueue_script(
			'carticy-product-manager',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/dist/admin-product-manager.min.js',
			array( 'jquery' ),
			CARTICY_AI_CHECKOUT_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'carticy-product-manager',
			'carticyProductManager',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'carticy_product_feed_nonce' ),
				'i18n'     => array(
					'confirm_regenerate'  => __( 'Are you sure you want to regenerate the product feed cache?', 'carticy-ai-checkout-for-woocommerce' ),
					'confirm_recalculate' => __( 'Are you sure you want to recalculate all product quality scores? This may take a few moments.', 'carticy-ai-checkout-for-woocommerce' ),
					'copy'                => __( 'Copy to Clipboard', 'carticy-ai-checkout-for-woocommerce' ),
					'copied'              => __( 'Copied to clipboard!', 'carticy-ai-checkout-for-woocommerce' ),
				),
			)
		);
	}
}
