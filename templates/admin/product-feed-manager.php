<?php
/**
 * Product Feed Manager Template
 *
 * Content area for product feed manager (wrapped by layout-wrapper.php)
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array                                         $stats      Feed statistics
 * @var \Carticy\AiCheckout\Admin\ProductsListTable $list_table Products list table instance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="carticy-ai-checkout-products">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=carticy-ai-checkout' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Back to Settings', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</a>

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
	$list_table->search_box( __( 'Search Products', 'carticy-ai-checkout-for-woocommerce' ), 'product' );
	$list_table->display();
	?>
</form>

<!-- Feed Preview Modal -->
<div id="carticy-feed-preview-modal" class="carticy-modal" style="display:none;">
	<div class="carticy-modal-overlay"></div>
	<div class="carticy-modal-content">
		<div class="carticy-modal-header">
			<h2><?php esc_html_e( 'Product Feed Preview', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
			<button type="button" class="carticy-modal-close">&times;</button>
		</div>
		<div class="carticy-modal-body">
			<div class="feed-preview-loading"><?php esc_html_e( 'Loading...', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
			<pre id="carticy-feed-preview-content"></pre>
		</div>
		<div class="carticy-modal-footer">
			<button type="button" class="button" id="carticy-copy-feed"><?php esc_html_e( 'Copy to Clipboard', 'carticy-ai-checkout-for-woocommerce' ); ?></button>
			<button type="button" class="button button-primary carticy-modal-close"><?php esc_html_e( 'Close', 'carticy-ai-checkout-for-woocommerce' ); ?></button>
		</div>
	</div>
</div>
</div><!-- .carticy-ai-checkout-products -->
