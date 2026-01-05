<?php
/**
 * Wizard Step 1: Business Information
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

$business_info = $data['business_info'] ?? array();

// Get validation errors from transient (for field-level error indicators).
$validation_errors = get_transient( 'carticy_ai_checkout_wizard_errors_' . get_current_user_id() );
if ( $validation_errors ) {
	delete_transient( 'carticy_ai_checkout_wizard_errors_' . get_current_user_id() );
}
$validation_errors = $validation_errors ? $validation_errors : array();

// Auto-fill defaults.
$defaults = array(
	'business_name' => get_bloginfo( 'name' ),
	'contact_email' => get_option( 'admin_email' ),
	'store_url'     => site_url(),
);

$business_info = array_merge( $defaults, $business_info );
?>

<div class="wizard-step-content">
	<h2><?php esc_html_e( 'Step 1: Business Information', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Provide your business information for OpenAI merchant application.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wizard-form">
		<?php wp_nonce_field( 'carticy_wizard_step' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_step">
		<input type="hidden" name="step" value="1">

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="business_name">
						<?php esc_html_e( 'Business Name', 'carticy-ai-checkout-for-woocommerce' ); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="business_name"
						name="step_data[business_name]"
						value="<?php echo esc_attr( $business_info['business_name'] ?? '' ); ?>"
						class="regular-text <?php echo isset( $validation_errors['business_name'] ) ? 'error' : ''; ?>"
						required>
					<p class="description">
						<?php esc_html_e( 'Your legal business name or DBA (Doing Business As) name.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="business_entity">
						<?php esc_html_e( 'Business Entity Type', 'carticy-ai-checkout-for-woocommerce' ); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<select
						id="business_entity"
						name="step_data[business_entity]"
						class="regular-text <?php echo isset( $validation_errors['business_entity'] ) ? 'error' : ''; ?>"
						required>
						<option value=""><?php esc_html_e( 'Select entity type...', 'carticy-ai-checkout-for-woocommerce' ); ?></option>
						<option value="sole_proprietor" <?php selected( $business_info['business_entity'] ?? '', 'sole_proprietor' ); ?>>
							<?php esc_html_e( 'Sole Proprietor', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</option>
						<option value="partnership" <?php selected( $business_info['business_entity'] ?? '', 'partnership' ); ?>>
							<?php esc_html_e( 'Partnership', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</option>
						<option value="llc" <?php selected( $business_info['business_entity'] ?? '', 'llc' ); ?>>
							<?php esc_html_e( 'LLC (Limited Liability Company)', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</option>
						<option value="corporation" <?php selected( $business_info['business_entity'] ?? '', 'corporation' ); ?>>
							<?php esc_html_e( 'Corporation', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</option>
						<option value="nonprofit" <?php selected( $business_info['business_entity'] ?? '', 'nonprofit' ); ?>>
							<?php esc_html_e( 'Non-Profit Organization', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Your legal business structure.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="contact_name">
						<?php esc_html_e( 'Contact Name', 'carticy-ai-checkout-for-woocommerce' ); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="contact_name"
						name="step_data[contact_name]"
						value="<?php echo esc_attr( $business_info['contact_name'] ?? '' ); ?>"
						class="regular-text <?php echo isset( $validation_errors['contact_name'] ) ? 'error' : ''; ?>"
						required>
					<p class="description">
						<?php esc_html_e( 'Primary contact person for this integration.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="contact_email">
						<?php esc_html_e( 'Contact Email', 'carticy-ai-checkout-for-woocommerce' ); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input
						type="email"
						id="contact_email"
						name="step_data[contact_email]"
						value="<?php echo esc_attr( $business_info['contact_email'] ?? '' ); ?>"
						class="regular-text <?php echo isset( $validation_errors['contact_email'] ) ? 'error' : ''; ?>"
						required>
					<p class="description">
						<?php esc_html_e( 'Email address for important notifications about your integration.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="store_url">
						<?php esc_html_e( 'Store URL', 'carticy-ai-checkout-for-woocommerce' ); ?>
						<span class="required">*</span>
					</label>
				</th>
				<td>
					<input
						type="url"
						id="store_url"
						name="step_data[store_url]"
						value="<?php echo esc_url( $business_info['store_url'] ?? '' ); ?>"
						class="regular-text <?php echo isset( $validation_errors['store_url'] ) ? 'error' : ''; ?>"
						required>
					<p class="description">
						<?php esc_html_e( 'Your primary store URL (must be HTTPS for production).', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php
		$current_step   = 1;
		$continue_label = __( 'Save and Continue', 'carticy-ai-checkout-for-woocommerce' );
		require __DIR__ . '/navigation.php';
		?>
	</form>
</div>
