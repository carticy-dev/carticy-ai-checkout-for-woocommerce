<?php
/**
 * Admin Layout Footer Template
 *
 * Reusable footer for all Carticy admin pages
 *
 * @package Carticy\AdminLayout
 * @var string $product_name    Product name (optional)
 * @var string $product_version Product version number (optional)
 * @var string $logo_url        Logo image URL (optional)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set defaults.
$product_name = $product_name ?? 'Carticy';
$logo_url     = $logo_url ?? 'https://carticy.com/wp-content/uploads/2025/06/logo-300x91.png';
$show_version = isset( $product_version ) && ! empty( $product_version );
?>

<div class="carticy-admin-footer">
	<div class="carticy-admin-footer-inner">
		<div class="carticy-admin-footer-left">
			<?php if ( $show_version ) : ?>
				<p class="carticy-admin-footer-text">
					<span class="carticy-admin-footer-version">
						<?php
						printf(
							/* translators: %s: version number */
							esc_html__( 'Version %s', 'carticy' ),
							esc_html( $product_version )
						);
						?>
					</span>
				</p>
			<?php endif; ?>
		</div>
		<div class="carticy-admin-footer-right">
			<img src="<?php echo esc_url( $logo_url ); ?>"
				 alt="<?php echo esc_attr( $product_name ); ?>"
				 class="carticy-admin-footer-logo">
		</div>
	</div>
</div>
