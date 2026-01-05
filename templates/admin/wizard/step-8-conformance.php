<?php
/**
 * Wizard Step 8: ACP Conformance Tests
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

$test_results        = get_transient( 'carticy_ai_checkout_wizard_test_results' );
$all_passed          = false;
$all_blocking_passed = false;

if ( $test_results && isset( $test_results['summary'] ) ) {
	$all_passed          = $test_results['summary']['all_passed'];
	$all_blocking_passed = $test_results['summary']['all_blocking_passed'] ?? false;
}
?>

<div class="wizard-step-content">
	<h2><?php esc_html_e( 'Step 8: ACP Conformance Tests', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Run the complete ACP conformance test suite to validate your integration. All 17 required tests must pass for production readiness.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>

	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'About These Tests', 'carticy-ai-checkout-for-woocommerce' ); ?></strong><br>
			<?php esc_html_e( 'These tests validate compliance with the OpenAI Agentic Commerce Protocol (ACP) specification. They test your API endpoints, session management, payment processing, webhooks, and security requirements.', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</p>
	</div>

	<?php if ( $all_passed ) : ?>
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( '✓ All Tests Passed!', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Your integration is ACP-compliant and ready for production deployment.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php elseif ( $test_results && $all_blocking_passed ) : ?>
		<div class="notice notice-info inline">
			<p>
				<strong><?php esc_html_e( '✓ All Critical Tests Passed', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Your integration meets all required criteria. Some optional optimization tests have warnings - you may proceed or address them to improve your integration.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php elseif ( $test_results ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( '⚠ Some Critical Tests Failed', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Please resolve the failed critical tests (marked in red) to continue. Warning tests (marked in orange) are optional.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Test Runner -->
	<div class="test-runner" style="margin-top: 30px;">
		<div style="background: #fff; padding: 25px; border: 1px solid #ddd; border-radius: 6px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
				<h3 style="margin: 0;">
					<span class="dashicons dashicons-admin-tools" style="color: #2271b1;"></span>
					<?php esc_html_e( 'Test Suite Runner', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</h3>
				<button type="button" id="run-all-tests" class="button button-primary button-large">
					<span class="dashicons dashicons-controls-play"></span>
					<?php esc_html_e( 'Run All Tests', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</button>
			</div>

			<!-- Progress Bar (initially hidden) -->
			<div id="test-progress-container" style="display: none; margin-bottom: 20px;">
				<div style="margin-bottom: 10px; text-align: center;">
					<div id="test-progress-message" style="font-weight: 600; color: #2271b1; font-size: 14px;">
						<?php esc_html_e( 'Preparing tests...', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</div>
				</div>
				<div style="background: #f0f0f1; height: 30px; border-radius: 15px; overflow: hidden; position: relative;">
					<div id="test-progress-bar" style="background: linear-gradient(90deg, #2271b1, #4285f4); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
					<div id="test-progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; color: #333; font-size: 14px;">
						0 / 17
					</div>
				</div>
			</div>

			<!-- Test Groups -->
			<div id="test-groups">

				<!-- Core Session Tests -->
				<div class="test-group">
					<h4 class="test-group-title">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<?php esc_html_e( 'Core Session Tests (1-3)', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h4>
					<div class="test-items">
						<div class="test-item" data-test="1">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 1: Session Creation With Shipping Address', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="2">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 2: Session Creation Without Shipping Address', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="3">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 3: Shipping Option Updates', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
					</div>
				</div>

				<!-- Payment & Completion Tests -->
				<div class="test-group">
					<h4 class="test-group-title">
						<span class="dashicons dashicons-money-alt"></span>
						<?php esc_html_e( 'Payment & Completion Tests (4-5)', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h4>
					<div class="test-items">
						<div class="test-item" data-test="4">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 4: SharedPaymentToken Processing', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="5">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 5: Order Completion (201 Status)', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
					</div>
				</div>

				<!-- Webhook & Error Tests -->
				<div class="test-group">
					<h4 class="test-group-title">
						<span class="dashicons dashicons-megaphone"></span>
						<?php esc_html_e( 'Webhook & Error Tests (6-7)', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h4>
					<div class="test-items">
						<div class="test-item" data-test="6">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 6: Webhook Emission', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="7">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 7: Error Scenarios', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
					</div>
				</div>

				<!-- Security Tests -->
				<div class="test-group">
					<h4 class="test-group-title">
						<span class="dashicons dashicons-shield"></span>
						<?php esc_html_e( 'Security Tests (8-9)', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h4>
					<div class="test-items">
						<div class="test-item" data-test="8">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 8: Idempotency Validation', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="9">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 9: Security Requirements', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
					</div>
				</div>

				<!-- Product Feed Tests -->
				<div class="test-group">
					<h4 class="test-group-title">
						<span class="dashicons dashicons-products"></span>
						<?php esc_html_e( 'Product Feed Tests (10-12)', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h4>
					<div class="test-items">
						<div class="test-item" data-test="10">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 10: Product Feed Endpoint Accessibility', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="11">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 11: Product Feed Data Quality', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="12">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 12: Feed Refresh Mechanism', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
					</div>
				</div>

				<!-- Advanced Tests -->
				<div class="test-group">
					<h4 class="test-group-title">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Advanced Tests (13-17)', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</h4>
					<div class="test-items">
						<div class="test-item" data-test="13">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 13: API-Version Header Validation', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="14">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 14: Rate Limiting Enforcement', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="15">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 15: Robots.txt Configuration', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="16">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 16: IP Allowlist Validation', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
						<div class="test-item" data-test="17">
							<span class="test-status"><span class="dashicons dashicons-minus"></span></span>
							<span class="test-name"><?php esc_html_e( 'Test 17: Production Prerequisites', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span class="test-result"></span>
						</div>
					</div>
				</div>

			</div>

			<!-- Test Summary (initially hidden) -->
			<div id="test-summary" style="display: none; margin-top: 30px; padding: 20px; background: #f0f0f1; border-radius: 6px;">
				<h4 style="margin: 0 0 15px 0;"><?php esc_html_e( 'Test Results Summary', 'carticy-ai-checkout-for-woocommerce' ); ?></h4>
				<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; line-height: 30px;">
					<div style="text-align: center;">
						<div style="font-size: 36px; font-weight: bold; color: #2271b1;" id="summary-total">0</div>
						<div style="color: #666;"><?php esc_html_e( 'Total Tests', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
					</div>
					<div style="text-align: center;">
						<div style="font-size: 36px; font-weight: bold; color: #46b450;" id="summary-passed">0</div>
						<div style="color: #666;"><?php esc_html_e( 'Passed', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
					</div>
					<div style="text-align: center;">
						<div style="font-size: 36px; font-weight: bold; color: #dc3232;" id="summary-failed">0</div>
						<div style="color: #666;"><?php esc_html_e( 'Failed', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
					</div>
				</div>
			</div>

		</div>
	</div>

	<!-- Test Limitations Notice -->
	<div class="notice notice-warning inline" style="margin-top: 20px;">
		<p><strong><?php esc_html_e( '⚠️ Important: Test Scope & Limitations', 'carticy-ai-checkout-for-woocommerce' ); ?></strong></p>
		<p><?php esc_html_e( 'Automated tests validate:', 'carticy-ai-checkout-for-woocommerce' ); ?></p>
		<ul style="margin: 10px 0 10px 20px;">
			<li><?php esc_html_e( '✓ REST API endpoints and response formats', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( '✓ Session workflow and state management', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( '✓ Error handling and status codes', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( '✓ Webhook delivery to test endpoint', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
		</ul>
		<p style="margin-top: 10px;">
			<strong><?php esc_html_e( 'For complete validation:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
			<?php esc_html_e( 'Use real Stripe SharedPaymentTokens from the Stripe Test Helpers API. These tests simulate payment flow but do not create actual Stripe PaymentIntents.', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'carticy_wizard_step' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_step">
		<input type="hidden" name="step" value="8">

		<?php
		$current_step      = 8;
		$continue_label    = __( 'Continue to Final Step', 'carticy-ai-checkout-for-woocommerce' );
		$continue_disabled = ! $all_blocking_passed;
		$continue_id       = 'continue-button';
		require __DIR__ . '/navigation.php';
		?>

		<?php if ( ! $all_blocking_passed ) : ?>
			<p class="description" style="text-align: center; margin-top: 15px; color: #666;">
				<?php esc_html_e( 'All critical tests (marked in red if failed) must pass to continue. Warning tests (orange) are optional.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>
	</form>

</div>