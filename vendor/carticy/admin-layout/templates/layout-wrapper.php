<?php
/**
 * Admin Layout Wrapper Template
 *
 * Main wrapper for all Carticy admin pages
 * Includes header, content area, and footer
 *
 * @package Carticy\AdminLayout
 * @var string   $page_title       Page title for header
 * @var callable $content          Content callback function
 * @var array    $content_data     Data to pass to content callback
 * @var array    $header_data      Optional header data
 * @var array    $footer_data      Optional footer data
 * @var bool     $show_notifications Whether to display WordPress notifications (default: true)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_notifications = $show_notifications ?? true;
?>

<div class="wrap carticy-ai-checkout carticy-admin-layout">
	<?php
	// Render header
	require __DIR__ . '/layout-header.php';
	?>

	<div class="carticy-admin-content">
		<?php if ( $show_notifications ) : ?>
			<?php
			// Notification area - manually output settings errors with 'inline' class
			// to prevent WordPress core JS from moving them to the header
			$settings_errors = get_settings_errors();
			if ( ! empty( $settings_errors ) ) :
				?>
				<div class="carticy-admin-notifications">
					<?php foreach ( $settings_errors as $error ) : ?>
						<div id="setting-error-<?php echo esc_attr( $error['code'] ); ?>" class="notice notice-<?php echo esc_attr( $error['type'] ); ?> settings-error inline is-dismissible">
							<p><?php echo wp_kses_post( $error['message'] ); ?></p>
							<button type="button" class="notice-dismiss">
								<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'carticy' ); ?></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				<?php
				// Clear settings errors after display to prevent persistence
				global $wp_settings_errors;
				$wp_settings_errors = array();
			endif;
			?>
		<?php endif; ?>

		<div class="carticy-admin-content-inner">
			<?php
			// Render page-specific content
			if ( isset( $content ) && is_callable( $content ) ) {
				call_user_func( $content, $content_data ?? array() );
			}
			?>
		</div>
	</div>

	<?php
	// Render footer
	if ( isset( $footer_data ) && is_array( $footer_data ) ) {
		extract( $footer_data );
	}
	require __DIR__ . '/layout-footer.php';
	?>
</div>
