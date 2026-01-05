<?php
/**
 * Wizard Step 5: System Prerequisites Check
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

$prerequisites_checks = $prerequisites->check_all();
$all_met              = $prerequisites->all_met();
?>

<div class="wizard-step-content">
	<h2><?php esc_html_e( 'Step 5: System Prerequisites', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Before running tests, we need to verify that all system prerequisites are met. All checks must pass to continue.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>

	<?php if ( $all_met ) : ?>
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( '✓ All Prerequisites Met!', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Your system is ready for testing and production deployment.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( '⚠ Some Prerequisites Not Met', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'Please resolve the issues below before continuing to the testing phase.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="prerequisites-checker">
		<table class="widefat striped" style="margin-top: 20px;">
			<thead>
				<tr>
					<th style="width: 40px;"><?php esc_html_e( 'Status', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Requirement', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Details', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Action', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $prerequisites_checks as $name => $check ) : ?>
					<tr class="<?php echo $check['passed'] ? 'prerequisite-passed' : 'prerequisite-failed'; ?>">
						<td class="prerequisite-status">
							<?php if ( $check['passed'] ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 24px;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss" style="color: #dc3232; font-size: 24px;"></span>
							<?php endif; ?>
						</td>
						<td class="prerequisite-name">
							<strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $name ) ) ); ?></strong>
						</td>
						<td class="prerequisite-message">
							<?php echo esc_html( $check['message'] ); ?>
							<?php if ( ! $check['passed'] && ! empty( $check['details'] ) ) : ?>
								<br>
								<span class="description" style="color: #666;"><?php echo esc_html( $check['details'] ); ?></span>
							<?php endif; ?>
						</td>
						<td class="prerequisite-action">
							<?php if ( ! $check['passed'] && ! empty( $check['action'] ) ) : ?>
								<a href="<?php echo esc_url( $check['action'] ); ?>" target="_blank" class="button button-small button-primary">
									<?php esc_html_e( 'Fix This', 'carticy-ai-checkout-for-woocommerce' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="prerequisites-actions" style="margin-top: 20px;">
		<button type="button" id="recheck-prerequisites" class="button button-secondary button-large">
			<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
			<?php esc_html_e( 'Recheck All Prerequisites', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</button>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'After resolving any issues, click "Recheck" to verify all prerequisites are met.', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="prerequisites-form">
		<?php wp_nonce_field( 'carticy_wizard_step' ); ?>
		<input type="hidden" name="action" value="carticy_ai_checkout_wizard_save_step">
		<input type="hidden" name="step" value="5">

		<?php
		$current_step      = 5;
		$continue_label    = __( 'Continue to Test Setup', 'carticy-ai-checkout-for-woocommerce' );
		$continue_disabled = ! $all_met;
		require __DIR__ . '/navigation.php';
		?>
	</form>
</div>

