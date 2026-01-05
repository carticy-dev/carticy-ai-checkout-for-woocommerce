<?php
/**
 * Wizard Step 4: Product Data Quality Review
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array                                                   $data            Wizard data.
 * @var \Carticy\AiCheckout\Services\ApplicationWizardService $wizard_service  Wizard service.
 * @var \Carticy\AiCheckout\Services\ProductQualityChecker    $quality_checker Quality checker service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get products enabled for ChatGPT.
$args = array(
	'status'     => 'publish',
	'limit'      => -1,
	'meta_query' => array(
		array(
			'key'     => '_carticy_ai_checkout_enabled',
			'value'   => 'yes',
			'compare' => '=',
		),
	),
);

$products             = wc_get_products( $args );
$quality_issues       = array();
$total_issues         = 0;
$products_with_issues = 0;
$total_quality_score  = 0;

foreach ( $products as $product ) {
	$product_quality = $quality_checker->check_product_quality( $product );

	// Add this product's score to the total for average calculation.
	$total_quality_score += $product_quality['score'];

	// Track products with issues for display.
	if ( ! empty( $product_quality['issues'] ) ) {
		$quality_issues[ $product->get_id() ] = array(
			'name'   => $product->get_name(),
			'score'  => $product_quality['score'],
			'issues' => $product_quality['issues'],
		);
		$total_issues                        += count( $product_quality['issues'] );
		++$products_with_issues;
	}
}

$total_products = count( $products );
// Calculate average quality score across all products.
$quality_score = $total_products > 0 ? round( $total_quality_score / $total_products ) : 100;
?>

<div class="wizard-step-content">
	<h2><?php esc_html_e( 'Step 4: Product Data Quality Review', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Review your product data quality for ChatGPT integration. OpenAI recommends complete product information for best results.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>

	<div class="quality-score-box">
		<h3><?php esc_html_e( 'Overall Quality Score', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<div class="quality-score">
			<div class="score-circle">
				<span class="score-number"><?php echo esc_html( number_format( $quality_score, 0 ) ); ?>%</span>
			</div>
			<div class="score-details">
				<p><strong><?php esc_html_e( 'Products Reviewed:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong> <?php echo esc_html( number_format_i18n( $total_products ) ); ?></p>
				<p><strong><?php esc_html_e( 'Products with Issues:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong> <?php echo esc_html( number_format_i18n( $products_with_issues ) ); ?></p>
				<p><strong><?php esc_html_e( 'Total Issues Found:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong> <?php echo esc_html( number_format_i18n( $total_issues ) ); ?></p>
			</div>
		</div>
	</div>

	<?php if ( $quality_score >= 80 ) : ?>
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( 'Excellent!', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Your product data quality is great. Your store is ready for ChatGPT integration.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php elseif ( $quality_score >= 60 ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'Good, but could be better.', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Consider fixing the issues below for optimal ChatGPT performance.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'Action Needed.', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Your product data has several issues that should be fixed before submitting your application.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $quality_issues ) ) : ?>
		<div class="quality-issues-list">
			<h3><?php esc_html_e( 'Issues to Fix', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Score', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Issues', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Action', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $quality_issues, 0, 10 ) as $product_id => $issue_data ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $issue_data['name'] ); ?></strong>
							</td>
							<td style="text-align: center;">
								<span class="quality-score-badge" style="background: <?php echo $issue_data['score'] >= 70 ? '#4caf50' : ( $issue_data['score'] >= 50 ? '#ff9800' : '#f44336' ); ?>; color: white; padding: 4px 8px; border-radius: 3px; font-weight: bold;">
									<?php echo esc_html( $issue_data['score'] ); ?>%
								</span>
							</td>
							<td>
								<ul class="issue-list" style="margin: 0; padding-left: 20px;">
									<?php foreach ( $issue_data['issues'] as $issue ) : ?>
										<li><?php echo esc_html( $issue ); ?></li>
									<?php endforeach; ?>
								</ul>
							</td>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>" class="button button-small" target="_blank">
									<?php esc_html_e( 'Edit Product', 'carticy-ai-checkout-for-woocommerce' ); ?> ↗
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $quality_issues ) > 10 ) : ?>
				<p class="description">
					<?php
					/* translators: %d: number of additional products with issues */
					printf( esc_html__( '... and %d more products with issues.', 'carticy-ai-checkout-for-woocommerce' ), count( $quality_issues ) - 10 );
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( 'Perfect!', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'No issues found. All your products have complete information.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="wizard-info-box">
		<h3><?php esc_html_e( 'Data Quality Best Practices', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'High-quality product images (at least 800x800px)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Detailed product descriptions (minimum 50 characters)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Accurate pricing information', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Brand/manufacturer information when applicable', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Proper categorization', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
		</ul>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'carticy_wizard_step' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_step">
		<input type="hidden" name="step" value="4">

		<?php
		$current_step = 4;
		require __DIR__ . '/navigation.php';
		?>
	</form>
</div>
