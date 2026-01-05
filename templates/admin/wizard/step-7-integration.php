<?php
/**
 * Wizard Step 7: Integration Configuration
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

$api_key        = get_option( 'carticy_ai_checkout_api_key', '' );
$webhook_secret = get_option( 'carticy_ai_checkout_webhook_secret', '' );
$ip_allowlist   = get_option( 'carticy_ai_checkout_enable_ip_allowlist', 'no' );

$all_configured = ! empty( $api_key ) && ! empty( $webhook_secret );
?>

<div class="wizard-step-content">
	<h2><?php esc_html_e( 'Step 7: Integration Configuration', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure the API keys and webhook settings for OpenAI integration. These credentials are automatically generated and secured.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>

	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'ℹ️ About These Credentials', 'carticy-ai-checkout-for-woocommerce' ); ?></strong><br>
			<?php esc_html_e( 'Your API key and webhook secret are automatically generated and secured. The API key will be included in your application submission (Step 9) that you provide to OpenAI.', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</p>
	</div>

	<div class="integration-config" style="margin-top: 30px;">

		<!-- API Key Card -->
		<div class="config-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px;">
			<h3 style="margin: 0 0 15px 0;">
				<span class="dashicons dashicons-admin-network" style="color: #2271b1;"></span>
				<?php esc_html_e( 'API Key (Bearer Token)', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</h3>
			<p class="description" style="margin-bottom: 15px;">
				<?php esc_html_e( 'This key authenticates API requests from OpenAI. Include it in the Authorization header as: Bearer {key}', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>

			<div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
				<div style="display: flex; align-items: center; gap: 10px;">
					<input type="text" id="api-key-field" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" readonly style="font-family: monospace; background: #fff;">
					<button type="button" class="button button-secondary" onclick="copyToClipboard('api-key-field')">
						<span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'Copy', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="regenerate-api-key" data-action="regenerate_api_key">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Regenerate', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</button>
				</div>
			</div>

			<div class="notice notice-warning inline" style="margin: 0;">
				<p style="margin: 8px 0;">
					<strong><?php esc_html_e( '⚠️ Important:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Keep this key secure. Regenerating will invalidate the old key and require updating OpenAI configuration.', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</p>
			</div>
		</div>

		<!-- Webhook Secret Card -->
		<div class="config-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px;">
			<h3 style="margin: 0 0 15px 0;">
				<span class="dashicons dashicons-lock" style="color: #2271b1;"></span>
				<?php esc_html_e( 'Webhook Secret (HMAC)', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</h3>
			<p class="description" style="margin-bottom: 15px;">
				<?php esc_html_e( 'Used to sign outgoing webhooks to OpenAI. This ensures webhook authenticity via HMAC-SHA256 signature.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>

			<div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
				<div style="display: flex; align-items: center; gap: 10px;">
					<input type="text" id="webhook-secret-field" class="regular-text" value="<?php echo esc_attr( $webhook_secret ); ?>" readonly style="font-family: monospace; background: #fff;">
					<button type="button" class="button button-secondary" onclick="copyToClipboard('webhook-secret-field')">
						<span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'Copy', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="regenerate-webhook-secret" data-action="regenerate_webhook_secret">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Regenerate', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Security Settings Card -->
		<div class="config-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px;">
			<h3 style="margin: 0 0 15px 0;">
				<span class="dashicons dashicons-shield" style="color: #2271b1;"></span>
				<?php esc_html_e( 'Security Settings', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</h3>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'carticy_wizard_integration' ); ?>
				<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_security">

				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'IP Allowlisting', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="enable_ip_allowlist" value="yes" <?php checked( $ip_allowlist, 'yes' ); ?>>
								<?php esc_html_e( 'Only allow requests from OpenAI IP addresses', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Recommended for production. Automatically bypassed in test mode. IP ranges are updated hourly from OpenAI.', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Security Settings', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</button>
			</form>

			<div style="margin-top: 20px; padding: 12px; background: #f0f0f1; border-radius: 4px;">
				<strong><?php esc_html_e( 'Active Security Features:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<ul style="margin: 10px 0 0 20px;">
					<li><?php esc_html_e( '✓ SSL/HTTPS enforcement (production mode)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( '✓ Bearer token authentication', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( '✓ Rate limiting per endpoint', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( '✓ Idempotency key validation', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( '✓ HMAC-SHA256 webhook signatures', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
				</ul>
			</div>
		</div>

	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'carticy_wizard_step' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_step">
		<input type="hidden" name="step" value="7">

		<?php
		$current_step   = 7;
		$continue_label = __( 'Continue to Conformance Tests', 'carticy-ai-checkout-for-woocommerce' );
		require __DIR__ . '/navigation.php';
		?>
	</form>
</div>

