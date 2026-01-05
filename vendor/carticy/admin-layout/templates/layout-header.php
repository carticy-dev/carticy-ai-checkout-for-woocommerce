<?php
/**
 * Admin Layout Header Template
 *
 * Reusable header for all Carticy admin pages
 *
 * @package Carticy\AdminLayout
 * @var string $page_title    Page title to display
 * @var array  $header_data   Optional header data (badges, actions, etc.)
 * @var bool   $show_test_badge Optional test mode badge (default: false)
 * @var string $test_badge_text Optional custom text for test badge (default: 'Test Mode')
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set defaults
$show_test_badge = $show_test_badge ?? false;
$test_badge_text = $test_badge_text ?? __( 'Test Mode', 'carticy' );
?>

<div class="carticy-admin-header">
	<div class="carticy-admin-header-inner">
		<div class="carticy-admin-header-left">
			<h1 class="carticy-admin-title"><?php echo esc_html( $page_title ); ?></h1>
		</div>

		<div class="carticy-admin-header-right">
			<?php if ( $show_test_badge ) : ?>
				<div class="carticy-test-mode-badge">
					<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M8 1L10 5L15 6L11.5 9.5L12.5 15L8 12.5L3.5 15L4.5 9.5L1 6L6 5L8 1Z" fill="currentColor"/>
					</svg>
					<span><?php echo esc_html( $test_badge_text ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $header_data['actions'] ) ) : ?>
				<div class="carticy-admin-header-actions">
					<?php
					// Allow custom header actions
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $header_data['actions'];
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
