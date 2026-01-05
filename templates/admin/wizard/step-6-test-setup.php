<?php
/**
 * Wizard Step 6: Test Environment Setup
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array                                                   $data            Wizard data.
 * @var \Carticy\AiCheckout\Services\ApplicationWizardService  $wizard_service  Wizard service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$test_mode_enabled = 'yes' === get_option( 'carticy_ai_checkout_test_mode', 'yes' );
$test_webhook_url  = get_option( 'carticy_ai_checkout_test_webhook_url', '' );

// Check enabled products for testing
$enabled_products = get_posts(
	array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'     => '_carticy_ai_checkout_enabled',
				'value'   => 'yes',
				'compare' => '=',
			),
		),
		'fields'         => 'ids',
	)
);

// Check for out of stock products
$out_of_stock_products = get_posts(
	array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array(
				'key'     => '_stock_status',
				'value'   => 'outofstock',
				'compare' => '=',
			),
			array(
				'key'     => '_carticy_ai_checkout_enabled',
				'value'   => 'yes',
				'compare' => '=',
			),
		),
		'fields'         => 'ids',
	)
);

$has_enabled_products = ! empty( $enabled_products );
$has_out_of_stock     = ! empty( $out_of_stock_products );
$all_setup_complete   = $test_mode_enabled && ! empty( $test_webhook_url ) && $has_enabled_products;

// Get Stripe settings
$stripe_settings         = get_option( 'woocommerce_stripe_settings', array() );
$stripe_test_publishable = ! empty( $stripe_settings['test_publishable_key'] );
$stripe_test_secret      = ! empty( $stripe_settings['test_secret_key'] );
$stripe_configured       = $stripe_test_publishable && $stripe_test_secret;
?>

<div class="wizard-step-content">
	<h2><?php esc_html_e( 'Step 6: Test Environment Setup', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure your testing environment to run ACP conformance tests. Follow the checklist below to prepare for testing.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>

	<?php if ( $all_setup_complete ) : ?>
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( '✓ Test Environment Ready!', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Your testing environment is configured and ready for conformance tests.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-info inline">
			<p>
				<strong><?php esc_html_e( 'Setup Required', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Complete the setup steps below to prepare your test environment.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="test-setup-checklist" style="margin-top: 30px;">

		<!-- Test Mode Card -->
		<div class="setup-card <?php echo $test_mode_enabled ? 'setup-complete' : 'setup-incomplete'; ?>" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid <?php echo $test_mode_enabled ? '#46b450' : '#ffb900'; ?>;">
			<div style="display: flex; align-items: flex-start; gap: 15px;">
				<div class="setup-icon" style="font-size: 32px; line-height: 1;">
					<?php if ( $test_mode_enabled ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
					<?php else : ?>
						<span class="dashicons dashicons-marker" style="color: #ffb900;"></span>
					<?php endif; ?>
				</div>
				<div style="flex: 1;">
					<h3 style="margin: 0 0 10px 0;">
						<?php esc_html_e( '1. Enable Test Mode', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h3>
					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'Test mode bypasses SSL/HTTPS and IP allowlist checks, allowing you to run tests locally.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>

					<?php if ( $test_mode_enabled ) : ?>
						<div style="padding: 10px; background: #d4edda; border-radius: 4px; margin-bottom: 10px;">
							<strong style="color: #155724;"><?php esc_html_e( '✓ Test mode is currently enabled', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						</div>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
						<?php wp_nonce_field( 'carticy_wizard_test_setup' ); ?>
						<input type="hidden" name="action" value="carticy_ai_checkout_wizard_toggle_test_mode">
						<button type="submit" class="button <?php echo $test_mode_enabled ? 'button-secondary' : 'button-primary'; ?>">
							<?php
							echo $test_mode_enabled
								? esc_html__( 'Disable Test Mode', 'carticy-ai-checkout-for-woocommerce' )
								: esc_html__( 'Enable Test Mode', 'carticy-ai-checkout-for-woocommerce' );
							?>
						</button>
					</form>
				</div>
			</div>
		</div>

		<!-- Test Webhook URL Card -->
		<div class="setup-card <?php echo ! empty( $test_webhook_url ) ? 'setup-complete' : 'setup-incomplete'; ?>" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid <?php echo ! empty( $test_webhook_url ) ? '#46b450' : '#ffb900'; ?>;">
			<div style="display: flex; align-items: flex-start; gap: 15px;">
				<div class="setup-icon" style="font-size: 32px; line-height: 1;">
					<?php if ( ! empty( $test_webhook_url ) ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
					<?php else : ?>
						<span class="dashicons dashicons-marker" style="color: #ffb900;"></span>
					<?php endif; ?>
				</div>
				<div style="flex: 1;">
					<h3 style="margin: 0 0 10px 0;">
						<?php esc_html_e( '2. Configure Test Webhook URL', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h3>
					<p class="description" style="margin-bottom: 10px;">
						<?php esc_html_e( 'Use a free testing URL from webhook.site to verify webhook delivery works correctly. This is NOT your production OpenAI webhook.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>

					<div class="notice notice-info inline" style="margin: 0 0 15px 0;">
						<p style="margin: 8px 0;">
							<strong><?php esc_html_e( 'ℹ️ Test Webhook vs OpenAI Webhook:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong><br>
							<?php esc_html_e( 'This test webhook (webhook.site) is only for validating that your plugin can send webhooks with the correct format. Your actual OpenAI production webhook URL will be provided by OpenAI after your application is approved (Step 9).', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</p>
					</div>

					<div style="background: #f0f0f1; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
						<strong><?php esc_html_e( 'How to get a test webhook URL:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						<ol style="margin: 10px 0 0 20px;">
							<li><?php esc_html_e( 'Visit webhook.site in a new tab', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Copy your unique URL (looks like: https://webhook.site/abc123...)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Paste it in the field below', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
						</ol>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'carticy_wizard_test_setup' ); ?>
						<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_test_webhook">
						<input type="url" name="test_webhook_url" class="regular-text" placeholder="https://webhook.site/..." value="<?php echo esc_attr( $test_webhook_url ); ?>" required>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Save Webhook URL', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</button>
						<a href="https://webhook.site" target="_blank" class="button button-secondary">
							<?php esc_html_e( 'Open webhook.site', 'carticy-ai-checkout-for-woocommerce' ); ?> ↗
						</a>
					</form>

					<?php if ( ! empty( $test_webhook_url ) ) : ?>
						<div style="padding: 10px; background: #d4edda; border-radius: 4px; margin-top: 10px;">
							<strong style="color: #155724;"><?php esc_html_e( '✓ Test webhook URL configured', 'carticy-ai-checkout-for-woocommerce' ); ?></strong><br>
							<code style="display: block; margin-top: 5px; word-break: break-all;"><?php echo esc_html( $test_webhook_url ); ?></code>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Product Feed Card -->
		<div class="setup-card <?php echo $has_enabled_products ? 'setup-complete' : 'setup-incomplete'; ?>" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid <?php echo $has_enabled_products ? '#46b450' : '#ffb900'; ?>;">
			<div style="display: flex; align-items: flex-start; gap: 15px;">
				<div class="setup-icon" style="font-size: 32px; line-height: 1;">
					<?php if ( $has_enabled_products ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
					<?php else : ?>
						<span class="dashicons dashicons-marker" style="color: #ffb900;"></span>
					<?php endif; ?>
				</div>
				<div style="flex: 1;">
					<h3 style="margin: 0 0 10px 0;">
						<?php esc_html_e( '3. Enable Test Products', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h3>
					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'Enable at least one product for ChatGPT checkout to run product feed tests.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>

					<?php if ( $has_enabled_products ) : ?>
						<div style="padding: 10px; background: #d4edda; border-radius: 4px; margin-bottom: 10px;">
							<strong style="color: #155724;">
								<?php
								/* translators: %d: number of enabled products */
								printf( esc_html__( '✓ %d product(s) enabled for ChatGPT', 'carticy-ai-checkout-for-woocommerce' ), count( $enabled_products ) );
								?>
							</strong>
						</div>
					<?php else : ?>
						<div style="padding: 10px; background: #fff3cd; border-radius: 4px; margin-bottom: 10px;">
							<strong style="color: #856404;"><?php esc_html_e( '⚠ No products enabled yet', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						</div>
					<?php endif; ?>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=carticy-ai-checkout-product-feed' ) ); ?>" class="button button-primary" target="_blank">
						<?php esc_html_e( 'Manage Product Feed', 'carticy-ai-checkout-for-woocommerce' ); ?> ↗
					</a>

					<?php if ( ! $has_out_of_stock ) : ?>
						<p class="description" style="margin-top: 10px; color: #666;">
							<strong><?php esc_html_e( 'Recommended:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
							<?php esc_html_e( 'Create at least one out-of-stock product to test error scenarios.', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Stripe Configuration Card -->
		<div class="setup-card <?php echo $stripe_configured ? 'setup-complete' : 'setup-incomplete'; ?>" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid <?php echo $stripe_configured ? '#46b450' : '#ffb900'; ?>;">
			<div style="display: flex; align-items: flex-start; gap: 15px;">
				<div class="setup-icon" style="font-size: 32px; line-height: 1;">
					<?php if ( $stripe_configured ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
					<?php else : ?>
						<span class="dashicons dashicons-marker" style="color: #ffb900;"></span>
					<?php endif; ?>
				</div>
				<div style="flex: 1;">
					<h3 style="margin: 0 0 10px 0;">
						<?php esc_html_e( '4. Stripe Test API Keys', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h3>
					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'Configure Stripe test API keys in WooCommerce Stripe Gateway settings.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>

					<?php if ( $stripe_configured ) : ?>
						<div style="padding: 10px; background: #d4edda; border-radius: 4px; margin-bottom: 10px;">
							<strong style="color: #155724;"><?php esc_html_e( '✓ Stripe test keys configured', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						</div>
					<?php else : ?>
						<div style="padding: 10px; background: #fff3cd; border-radius: 4px; margin-bottom: 10px;">
							<strong style="color: #856404;"><?php esc_html_e( '⚠ Stripe test keys not configured', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						</div>
					<?php endif; ?>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) ); ?>" class="button button-primary" target="_blank">
						<?php esc_html_e( 'Configure Stripe Settings', 'carticy-ai-checkout-for-woocommerce' ); ?> ↗
					</a>
				</div>
			</div>
		</div>

	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'carticy_wizard_step' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_step">
		<input type="hidden" name="step" value="6">

		<?php
		$current_step      = 6;
		$continue_label    = __( 'Continue to Integration Setup', 'carticy-ai-checkout-for-woocommerce' );
		$continue_disabled = ! $all_setup_complete;
		require __DIR__ . '/navigation.php';
		?>

		<?php if ( ! $all_setup_complete ) : ?>
			<p class="description" style="text-align: center; margin-top: 15px; color: #666;">
				<?php esc_html_e( 'Complete all setup steps above to continue.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>
	</form>
</div>

