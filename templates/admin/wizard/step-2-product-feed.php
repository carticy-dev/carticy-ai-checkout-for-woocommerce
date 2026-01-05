<?php
/**
 * Wizard Step 2: Product Feed Configuration
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array                                                   $data            Wizard data.
 * @var \Carticy\AiCheckout\Services\ApplicationWizardService $wizard_service  Wizard service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get products with ChatGPT enabled.
$args = array(
	'status' => 'publish',
	'limit'  => -1,
);

$products              = wc_get_products( $args );
$chatgpt_enabled_count = 0;

foreach ( $products as $product ) {
	if ( 'yes' === $product->get_meta( '_carticy_ai_checkout_enabled' ) ) {
		++$chatgpt_enabled_count;
	}
}

$total_products = count( $products );
?>

<div class="wizard-step-content">
	<h2><?php esc_html_e( 'Step 2: Product Feed Configuration', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Select which products should be available in ChatGPT. You can configure individual products from the Product Feed Manager.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>

	<div class="wizard-info-box">
		<h3><?php esc_html_e( 'Current Product Feed Status', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<table class="widefat">
			<tr>
				<th><?php esc_html_e( 'Total Products', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $total_products ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'ChatGPT Enabled', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<td>
					<strong><?php echo esc_html( number_format_i18n( $chatgpt_enabled_count ) ); ?></strong>
					<?php if ( 0 === $chatgpt_enabled_count ) : ?>
						<span class="status-warning" style="display: inline-flex; align-items: center; gap: 4px; color: #f0ad4e;">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
								<path d="M8 5v4M8 11h.01M8 1a7 7 0 110 14A7 7 0 018 1z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<?php esc_html_e( 'No products enabled yet', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</span>
					<?php else : ?>
						<span class="status-success" style="display: inline-flex; align-items: center; color: #46b450;">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
								<path d="M13.5 4L6 11.5L2.5 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Product Feed URL', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<td>
					<code><?php echo esc_url( rest_url( 'carticy-ai-checkout/v1/products' ) ); ?></code>
					<button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js( rest_url( 'carticy-ai-checkout/v1/products' ) ); ?>');">
						<?php esc_html_e( 'Copy URL', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</button>
				</td>
			</tr>
		</table>
	</div>

	<div class="wizard-info-box">
		<h3><?php esc_html_e( 'Product Selection', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<p>
			<?php esc_html_e( 'You can manage which products appear in ChatGPT from the Product Feed Manager page.', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=carticy-ai-checkout-product-feed' ) ); ?>" class="button button-secondary" target="_blank">
				<?php esc_html_e( 'Open Product Feed Manager', 'carticy-ai-checkout-for-woocommerce' ); ?> ↗
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'Tip: For best results, enable products with complete information (images, descriptions, pricing, and brand).', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</p>
	</div>

	<?php if ( 0 === $chatgpt_enabled_count ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'Action Required:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'You should enable at least some products for ChatGPT before submitting your application.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'carticy_wizard_step' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_step">
		<input type="hidden" name="step" value="2">

		<?php
		$current_step = 2;
		require __DIR__ . '/navigation.php';
		?>
	</form>
</div>
