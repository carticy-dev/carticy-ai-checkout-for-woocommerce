<?php
/**
 * Application Wizard Template
 *
 * Content area for application wizard (wrapped by layout-wrapper.php)
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 * @var int                                              $current_step    Current step number
 * @var int                                              $total_steps     Total number of steps
 * @var int                                              $completion      Completion percentage
 * @var array                                            $wizard_data     Wizard data
 * @var \Carticy\AiCheckout\Admin\ApplicationWizard    $wizard_instance Wizard instance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="carticy-wizard-container">
	<!-- Wizard Header -->
	<div class="wizard-header">
		<p><?php esc_html_e( 'This wizard will guide you through preparing your application for OpenAI ChatGPT integration.', 'carticy-ai-checkout-for-woocommerce' ); ?></p>
	</div>

	<!-- Progress Bar -->
	<div class="wizard-progress">
		<div class="progress-bar">
			<div class="progress-fill" style="width: <?php echo esc_attr( $completion ); ?>%;"></div>
		</div>
		<div class="progress-steps">
			<?php for ( $i = 1; $i <= $total_steps; $i++ ) : ?>
				<?php
				$step_class = 'progress-step';
				if ( $i === $current_step ) {
					$step_class .= ' active';
				} elseif ( $wizard_instance->is_step_completed( $i ) ) {
					$step_class .= ' completed';
				}
				?>
				<div class="<?php echo esc_attr( $step_class ); ?>">
					<?php echo esc_html( $wizard_instance->get_step_title_public( $i ) ); ?>
				</div>
			<?php endfor; ?>
		</div>
	</div>

	<!-- Step Content -->
	<div class="wizard-content">
		<?php $wizard_instance->render_step_public( $current_step ); ?>
	</div>
</div>

<!-- Wizard Information Notice -->
<div class="notice notice-info inline" style="margin-top: 20px;">
	<p>
		<strong><?php esc_html_e( 'About This Wizard:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
		<?php
		printf(
			/* translators: %s: URL to OpenAI merchant form */
			esc_html__( 'This wizard helps you prepare application data for OpenAI. Final submission is done manually through OpenAI\'s web form at %s - there is no automated submission API.', 'carticy-ai-checkout-for-woocommerce' ),
			'chatgpt.com/merchants'
		);
		?>
	</p>
</div>

<!-- Reset Button -->
<div class="wizard-reset">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
		<?php wp_nonce_field( 'carticy_wizard_reset' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_reset">
		<button type="submit" class="button wizard-reset-button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to restart the wizard? All progress will be lost.', 'carticy-ai-checkout-for-woocommerce' ); ?>');">
			<?php esc_html_e( 'Restart Wizard', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</button>
	</form>
</div>
