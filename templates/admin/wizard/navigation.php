<?php
/**
 * Wizard Navigation Partial
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * Required variables:
 * @var int    $current_step       Current wizard step.
 * @var string $continue_label     Label for continue button (optional).
 * @var bool   $continue_disabled  Whether continue button is disabled (optional).
 * @var string $continue_id        ID attribute for continue button (optional).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$continue_label    = $continue_label ?? __( 'Continue', 'carticy-ai-checkout-for-woocommerce' );
$continue_disabled = $continue_disabled ?? false;
$continue_id       = $continue_id ?? '';
?>

<div class="wizard-navigation" style="margin-top: 30px; display: flex; gap: 10px; align-items: center;">
	<?php if ( $current_step > 1 ) : ?>
		<button type="button" class="button button-large" onclick="carticyWizardNavigate('prev');">
			← <?php esc_html_e( 'Previous', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</button>
	<?php endif; ?>

	<button type="submit" class="button button-primary button-large" <?php echo $continue_disabled ? 'disabled' : ''; ?><?php echo $continue_id ? ' id="' . esc_attr( $continue_id ) . '"' : ''; ?>>
		<?php echo esc_html( $continue_label ); ?> →
	</button>
</div>

