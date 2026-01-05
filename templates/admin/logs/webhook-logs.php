<?php
/**
 * Webhook Logs Tab Template
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array $data Webhook logs data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entries     = $data['entries'] ?? array();
$statistics  = $data['statistics'] ?? array();
$retry_queue = $data['retry_queue'] ?? array();
?>

<div class="webhook-logs-tab">
	<div class="logs-header">
		<h2><?php esc_html_e( 'Webhook Activity', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
		<div class="logs-actions">
			<a href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'page'   => 'carticy-ai-checkout-logs',
							'action' => 'carticy_ai_checkout_clear_webhook_cache',
						),
						admin_url( 'admin.php' )
					),
					'carticy_logs_action'
				)
			);
			?>
			"
				class="button">
				<?php esc_html_e( 'Refresh Statistics', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</a>
		</div>
	</div>

	<?php if ( ! empty( $statistics ) ) : ?>
		<div class="webhook-statistics">
			<h3><?php esc_html_e( 'Webhook Statistics (Last 24 Hours)', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
			<div class="stats-grid">
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Total Sent', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $statistics['total_sent'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card stat-success">
					<span class="stat-label"><?php esc_html_e( 'Successful', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $statistics['total_success'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card stat-error">
					<span class="stat-label"><?php esc_html_e( 'Failed', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $statistics['total_failed'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Retry Rate', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( ( $statistics['retry_rate'] ?? 0 ) . '%' ); ?></span>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $retry_queue ) ) : ?>
		<div class="retry-queue">
			<h3><?php esc_html_e( 'Failed Webhooks (Retry Queue)', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
			<p class="description">
				<?php
				printf(
					/* translators: %d: number of webhooks pending retry */
					esc_html__( '%d webhook(s) pending retry', 'carticy-ai-checkout-for-woocommerce' ),
					count( $retry_queue )
				);
				?>
				<a href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'page'   => 'carticy-ai-checkout-logs',
								'action' => 'carticy_ai_checkout_clear_retry_queue',
							),
							admin_url( 'admin.php' )
						),
						'carticy_logs_action'
					)
				);
				?>
							"
					class="button button-small">
					<?php esc_html_e( 'Clear Queue', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $entries ) ) : ?>
		<div class="notice notice-info inline">
			<p>
				<strong><?php esc_html_e( 'No webhook activity found.', 'carticy-ai-checkout-for-woocommerce' ); ?></strong><br>
				<?php esc_html_e( 'Webhooks are only sent for orders created through ChatGPT Instant Checkout. Create a test order via the ChatGPT interface or use the Testing Tools to trigger webhooks.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Timestamp', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Event Type', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'URL', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Attempt', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Result', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$success_class = $entry['success'] ? 'webhook-success' : 'webhook-failed';
					?>
					<tr>
						<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
						<td><code><?php echo esc_html( $entry['event_type'] ); ?></code></td>
						<td class="url-cell"><small><?php echo esc_html( $entry['url'] ); ?></small></td>
						<td><span class="status-code"><?php echo esc_html( $entry['status_code'] ); ?></span></td>
						<td><?php echo esc_html( $entry['attempt'] ); ?></td>
						<td><span class="webhook-result <?php echo esc_attr( $success_class ); ?>"><?php echo $entry['success'] ? esc_html__( 'Success', 'carticy-ai-checkout-for-woocommerce' ) : esc_html__( 'Failed', 'carticy-ai-checkout-for-woocommerce' ); ?></span></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
