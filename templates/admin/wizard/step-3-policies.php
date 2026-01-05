<?php
/**
 * Wizard Step 3: Business Policies
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

$policies = $data['policies'] ?? array();

// Get validation errors from transient (for field-level error indicators).
$validation_errors = get_transient( 'carticy_ai_checkout_wizard_errors_' . get_current_user_id() );
if ( $validation_errors ) {
	delete_transient( 'carticy_ai_checkout_wizard_errors_' . get_current_user_id() );
}
$validation_errors = $validation_errors ? $validation_errors : array();
?>

<div class="wizard-step-content">
	<h2><?php esc_html_e( 'Step 3: Business Policies', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'OpenAI requires publicly accessible policy URLs for merchant verification. Terms of Service and Privacy Policy are required. Return/Refund policy is optional.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>

	<div class="wizard-info-box">
		<h3><?php esc_html_e( 'Policy Requirements', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Policies must be publicly accessible (not password protected)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'URLs must return a 2xx HTTP status code (200, 201, etc.)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'You may use the same URL for multiple policies if they\'re on one page', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Return/Refund policy is optional for digital-only stores', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'HTTPS is recommended for all policy pages', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
		</ul>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wizard-form">
		<?php wp_nonce_field( 'carticy_wizard_step' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_step">
		<input type="hidden" name="step" value="3">

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="terms_of_service">
						<?php esc_html_e( 'Terms of Service URL', 'carticy-ai-checkout-for-woocommerce' ); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input
						type="url"
						id="terms_of_service"
						name="step_data[terms_of_service]"
						value="<?php echo esc_url( $policies['terms_of_service'] ?? '' ); ?>"
						class="regular-text <?php echo isset( $validation_errors['terms_of_service'] ) ? 'error' : ''; ?>"
						placeholder="https://yourstore.com/terms-of-service"
						required>
					<p class="description">
						<?php esc_html_e( 'URL to your Terms of Service page. This will be verified for accessibility.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
					<?php if ( isset( $validation_errors['terms_of_service'] ) ) : ?>
						<p class="error-message"><?php echo esc_html( $validation_errors['terms_of_service'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="privacy_policy">
						<?php esc_html_e( 'Privacy Policy URL', 'carticy-ai-checkout-for-woocommerce' ); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input
						type="url"
						id="privacy_policy"
						name="step_data[privacy_policy]"
						value="<?php echo esc_url( $policies['privacy_policy'] ?? '' ); ?>"
						class="regular-text <?php echo isset( $validation_errors['privacy_policy'] ) ? 'error' : ''; ?>"
						placeholder="https://yourstore.com/privacy-policy"
						required>
					<p class="description">
						<?php esc_html_e( 'URL to your Privacy Policy page. This will be verified for accessibility.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
					<?php if ( isset( $validation_errors['privacy_policy'] ) ) : ?>
						<p class="error-message"><?php echo esc_html( $validation_errors['privacy_policy'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="return_policy">
						<?php esc_html_e( 'Return/Refund Policy URL', 'carticy-ai-checkout-for-woocommerce' ); ?>
						<span class="optional-label" style="font-weight: normal; color: #666;"><?php esc_html_e( '(Optional)', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					</label>
				</th>
				<td>
					<input
						type="url"
						id="return_policy"
						name="step_data[return_policy]"
						value="<?php echo esc_url( $policies['return_policy'] ?? '' ); ?>"
						class="regular-text <?php echo isset( $validation_errors['return_policy'] ) ? 'error' : ''; ?>"
						placeholder="https://yourstore.com/return-policy">
					<p class="description">
						<?php esc_html_e( 'URL to your Return/Refund Policy page. If provided, it will be verified for accessibility.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
					<?php if ( isset( $validation_errors['return_policy'] ) ) : ?>
						<p class="error-message"><?php echo esc_html( $validation_errors['return_policy'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<div class="notice notice-info inline">
			<p>
				<strong><?php esc_html_e( 'Note:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'When you click "Validate and Continue", the wizard will check if each URL is accessible and returns a valid response.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>

		<?php
		$current_step   = 3;
		$continue_label = __( 'Validate and Continue', 'carticy-ai-checkout-for-woocommerce' );
		require __DIR__ . '/navigation.php';
		?>
	</form>
</div>
