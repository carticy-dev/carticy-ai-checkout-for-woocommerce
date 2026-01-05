<?php
/**
 * Wizard Step 9: Generate Application Data & Submit
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array                                                   $data            Wizard data.
 * @var \Carticy\AiCheckout\Services\ApplicationWizardService  $wizard_service  Wizard service.
 * @var \Carticy\AiCheckout\Services\PrerequisitesChecker      $prerequisites   Prerequisites checker.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Generate application data.
$application_data      = $wizard_service->generate_application_data();
$application_json      = wp_json_encode( $application_data, JSON_PRETTY_PRINT );
$all_prerequisites_met = $prerequisites->all_met();
?>

<div class="wizard-step-content">
	<h2><?php esc_html_e( 'Step 9: Application Data & Submission', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Your application data has been generated. Review the information, download it, and submit your application to OpenAI.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>

	<div class="notice notice-success inline">
		<p>
			<strong><?php esc_html_e( '✓ Application Data Ready!', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
			<?php esc_html_e( 'Your store information has been compiled and is ready for submission to OpenAI.', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</p>
	</div>

	<!-- Application Summary -->
	<div class="application-summary wizard-section">
		<h3><?php esc_html_e( 'Application Summary', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<div class="wizard-box">
			<table class="widefat">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Business Name', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $application_data['business']['name'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Website', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td><?php echo esc_url( $application_data['business']['website'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Contact Email', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $application_data['business']['contact']['email'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Product Feed URL', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td>
							<code><?php echo esc_url( $application_data['technical']['product_feed_url'] ?? '' ); ?></code>
							<button type="button" class="button button-small" onclick="copyToClipboard('<?php echo esc_js( $application_data['technical']['product_feed_url'] ?? '' ); ?>', this)">
								<?php esc_html_e( 'Copy', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Terms of Service', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td>
							<a href="<?php echo esc_url( $application_data['policies']['terms_of_service'] ?? '' ); ?>" target="_blank">
								<?php echo esc_url( $application_data['policies']['terms_of_service'] ?? '' ); ?> ↗
							</a>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Privacy Policy', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td>
							<a href="<?php echo esc_url( $application_data['policies']['privacy_policy'] ?? '' ); ?>" target="_blank">
								<?php echo esc_url( $application_data['policies']['privacy_policy'] ?? '' ); ?> ↗
							</a>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Return Policy', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td>
							<a href="<?php echo esc_url( $application_data['policies']['return_policy'] ?? '' ); ?>" target="_blank">
								<?php echo esc_url( $application_data['policies']['return_policy'] ?? '' ); ?> ↗
							</a>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'SSL Enabled', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td>
							<?php if ( $application_data['technical']['ssl_enabled'] ) : ?>
								<strong class="status-success">✓ <?php esc_html_e( 'Yes', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
							<?php else : ?>
								<strong class="status-error">✗ <?php esc_html_e( 'No', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Payment Processor', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td>
							<?php echo esc_html( $application_data['integration']['payment_processor'] ?? '' ); ?>
							<?php if ( $application_data['integration']['stripe_connected'] ) : ?>
								<strong class="status-success">(✓ <?php esc_html_e( 'Connected', 'carticy-ai-checkout-for-woocommerce' ); ?>)</strong>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Export Application Data -->
	<div class="application-data-export wizard-section">
		<h3><?php esc_html_e( 'Export Application Data', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<p>
			<?php esc_html_e( 'Download or copy the complete application data in JSON format for your records or OpenAI submission.', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</p>

		<div class="wizard-box">
			<div class="export-actions">
				<button type="button" class="button button-primary" id="copy-json-btn">
					<span class="dashicons dashicons-admin-page"></span><?php esc_html_e( 'Copy JSON to Clipboard', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="download-json-btn">
					<span class="dashicons dashicons-download"></span><?php esc_html_e( 'Download JSON File', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</button>
			</div>

			<div class="json-preview">
				<h4><?php esc_html_e( 'JSON Preview', 'carticy-ai-checkout-for-woocommerce' ); ?></h4>
				<textarea id="application-json" readonly><?php echo esc_textarea( $application_json ); ?></textarea>
			</div>
		</div>
	</div>

	<!-- Merchant Program Fees -->
	<div class="fees-box wizard-section">
		<h3><?php esc_html_e( 'Merchant Program Fees', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Feature', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Cost', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Product Discovery in ChatGPT', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						<p class="description table-description"><?php esc_html_e( 'Your products will be searchable and discoverable in ChatGPT', 'carticy-ai-checkout-for-woocommerce' ); ?></p>
					</td>
					<td><strong class="status-success"><?php esc_html_e( 'FREE', 'carticy-ai-checkout-for-woocommerce' ); ?></strong></td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Transaction Fee', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						<p class="description table-description"><?php esc_html_e( 'Small fee per completed purchase (refunded if order is returned)', 'carticy-ai-checkout-for-woocommerce' ); ?></p>
					</td>
					<td><?php esc_html_e( 'Per transaction', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Monthly/Setup Fees', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						<p class="description table-description"><?php esc_html_e( 'No subscription or setup charges', 'carticy-ai-checkout-for-woocommerce' ); ?></p>
					</td>
					<td><strong class="status-success"><?php esc_html_e( 'NONE', 'carticy-ai-checkout-for-woocommerce' ); ?></strong></td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Requirements Checklist -->
	<div class="requirements-box wizard-section">
		<h3><?php esc_html_e( 'Application Requirements Checklist', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<table class="widefat">
			<tbody>
				<tr>
					<td>
						<?php if ( $all_prerequisites_met ) : ?>
							<span class="status-success">✓</span>
						<?php else : ?>
							<span class="status-error">✗</span>
						<?php endif; ?>
					</td>
					<td><?php esc_html_e( 'SSL Certificate (HTTPS)', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><span class="status-success">✓</span></td>
					<td><?php esc_html_e( 'Stripe Account Configured', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><span class="status-success">✓</span></td>
					<td><?php esc_html_e( 'Product Feed with Quality Data', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><span class="status-success">✓</span></td>
					<td><?php esc_html_e( 'Terms of Service URL', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><span class="status-success">✓</span></td>
					<td><?php esc_html_e( 'Privacy Policy URL', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><span class="status-success">✓</span></td>
					<td><?php esc_html_e( 'Return/Refund Policy URL', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><span class="status-success">✓</span></td>
					<td><?php esc_html_e( 'ACP Conformance Tests Passed', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Eligibility & Onboarding -->
	<div class="eligibility-box wizard-section">
		<h3><?php esc_html_e( 'Eligibility & Onboarding', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Merchants are onboarded on a rolling basis', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Shopify and Etsy merchants are automatically eligible (no integration needed)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Other merchants must apply and implement the Agentic Commerce Protocol', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'OpenAI will review your application and notify you of approval status', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
		</ul>
	</div>

	<!-- Submit Application -->
	<div class="submit-application wizard-section">
		<h3><?php esc_html_e( 'Submit Your Application', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>

		<?php if ( ! $all_prerequisites_met ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( '⚠ Prerequisites Not Met:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Some prerequisites are not yet met. You should resolve these issues before submitting your application.', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=carticy-ai-checkout' ) ); ?>" class="button">
						<?php esc_html_e( 'View Prerequisites', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</a>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-success inline">
				<p>
					<strong><?php esc_html_e( '✓ Ready to Submit!', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'All prerequisites are met and all tests have passed. You can now submit your application to OpenAI.', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<div class="submit-button-wrapper">
			<p>
				<?php esc_html_e( 'Click the button below to open the OpenAI merchant application form. You will need to provide the information you generated above.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
			<p>
				<a href="https://chatgpt.com/merchants" class="button button-primary button-large" target="_blank">
					<span class="dashicons dashicons-external"></span><?php esc_html_e( 'Submit Application to OpenAI', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</a>
			</p>
			<p class="description">
				<?php esc_html_e( 'Opens in a new tab: https://chatgpt.com/merchants', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>

		<!-- What Happens Next -->
		<div class="wizard-box-blue">
			<h3>
				<span class="dashicons dashicons-info"></span><?php esc_html_e( 'What Happens After Submission?', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</h3>

			<div class="inner-content-box">
				<ol>
					<li><strong><?php esc_html_e( 'OpenAI reviews your application', 'carticy-ai-checkout-for-woocommerce' ); ?></strong> – <?php esc_html_e( 'typically takes 3-7 business days', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><strong><?php esc_html_e( 'You receive approval notification', 'carticy-ai-checkout-for-woocommerce' ); ?></strong> – <?php esc_html_e( 'via email with your approval status', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><strong><?php esc_html_e( 'OpenAI provides your webhook URL', 'carticy-ai-checkout-for-woocommerce' ); ?></strong> – <?php esc_html_e( 'a unique endpoint for receiving order updates', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><strong><?php esc_html_e( 'Configure webhook in Settings', 'carticy-ai-checkout-for-woocommerce' ); ?></strong> – <?php esc_html_e( 'go to AI Checkout → Settings → Integration', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><strong><?php esc_html_e( 'Switch to production mode', 'carticy-ai-checkout-for-woocommerce' ); ?></strong> – <?php esc_html_e( 'disable test mode and verify live Stripe keys', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><strong><?php esc_html_e( 'Your store goes live', 'carticy-ai-checkout-for-woocommerce' ); ?></strong> – <?php esc_html_e( 'customers can purchase via ChatGPT!', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
				</ol>
			</div>

			<div class="notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( '⚠️ Important:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'You will need the webhook URL from OpenAI before you can go live. This will be provided after your application is approved. You can configure it later in Settings → Integration.', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- Additional Resources -->
	<div class="additional-resources wizard-section">
		<h3><?php esc_html_e( 'Additional Resources', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<div class="wizard-box">
			<ul>
				<li>
					<a href="https://developers.openai.com/commerce/guides/get-started/" target="_blank">
						<?php esc_html_e( 'OpenAI Commerce Developer Guide', 'carticy-ai-checkout-for-woocommerce' ); ?> ↗
					</a>
				</li>
				<li>
					<a href="https://developers.openai.com/commerce/specs/feed/" target="_blank">
						<?php esc_html_e( 'Product Feed Specification', 'carticy-ai-checkout-for-woocommerce' ); ?> ↗
					</a>
				</li>
				<li>
					<a href="https://developers.openai.com/commerce/specs/checkout/" target="_blank">
						<?php esc_html_e( 'Checkout API Specification', 'carticy-ai-checkout-for-woocommerce' ); ?> ↗
					</a>
				</li>
			</ul>
		</div>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'carticy_wizard_step' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_step">
		<input type="hidden" name="step" value="9">

		<?php
		$current_step   = 9;
		$continue_label = __( 'Complete Wizard', 'carticy-ai-checkout-for-woocommerce' );
		require __DIR__ . '/navigation.php';
		?>
	</form>
</div>

