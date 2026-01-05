<?php
/**
 * Logs Viewer Template
 *
 * Content area for logs viewer (wrapped by layout-wrapper.php)
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var string $active_tab Current active tab
 * @var array  $tabs       Tabs configuration
 * @var array  $data       Tab-specific data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
	<?php foreach ( $tabs as $tab_id => $tab_config ) : ?>
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page'     => 'carticy-ai-checkout-logs',
					'tab'      => $tab_id,
					'_wpnonce' => wp_create_nonce( 'carticy_logs_tab' ),
				),
				admin_url( 'admin.php' )
			)
		);
		?>
					"
			class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons <?php echo esc_attr( $tab_config['icon'] ); ?>"></span>
			<?php echo esc_html( $tab_config['label'] ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<div class="tab-content">
	<?php
	switch ( $active_tab ) {
		case 'api':
			include CARTICY_AI_CHECKOUT_DIR . 'templates/admin/logs/api-logs.php';
			break;
		case 'errors':
			include CARTICY_AI_CHECKOUT_DIR . 'templates/admin/logs/error-logs.php';
			break;
		case 'webhooks':
			include CARTICY_AI_CHECKOUT_DIR . 'templates/admin/logs/webhook-logs.php';
			break;
		case 'sessions':
			include CARTICY_AI_CHECKOUT_DIR . 'templates/admin/logs/session-status.php';
			break;
	}
	?>
</div>

<div class="logs-viewer-footer">
	<p class="description">
		<?php esc_html_e( 'Logs are stored using WooCommerce Logger and are retained according to WooCommerce log retention settings.', 'carticy-ai-checkout-for-woocommerce' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ); ?>" target="_blank">
			<?php esc_html_e( 'View raw logs in WooCommerce', 'carticy-ai-checkout-for-woocommerce' ); ?>
		</a>
	</p>
</div>
