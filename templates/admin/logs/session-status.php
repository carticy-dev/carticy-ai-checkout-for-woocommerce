<?php
/**
 * Session Status Tab Template
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array $data Session status data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_count    = $data['active_count'] ?? 0;
$total_count     = $data['total_count'] ?? 0;
$status_counts   = $data['status_counts'] ?? array();
$recent_sessions = $data['recent_sessions'] ?? array();
$performance     = $data['performance'] ?? array();
$system_health   = $data['system_health'] ?? array();
?>

<div class="session-status-tab">
	<div class="logs-header">
		<h2><?php esc_html_e( 'Session Status & Performance', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
		<div class="logs-actions">
			<a href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'page'   => 'carticy-ai-checkout-logs',
							'action' => 'carticy_ai_checkout_clear_performance_cache',
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

	<div class="session-overview">
		<h3><?php esc_html_e( 'Session Status Overview', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>

		<div class="stats-grid">
			<div class="stat-card stat-large">
				<span class="stat-label"><?php esc_html_e( 'Currently Active Checkout Sessions', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
				<span class="stat-value-large"><?php echo esc_html( $active_count ); ?></span>
			</div>
		</div>

		<?php if ( ! empty( $status_counts ) ) : ?>
			<h4 style="margin-top: 20px;"><?php esc_html_e( 'Session Status Breakdown', 'carticy-ai-checkout-for-woocommerce' ); ?></h4>
			<div class="stats-grid status-breakdown">
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Total Sessions', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $total_count ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Active', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value" style="color: #007017;"><?php echo esc_html( $status_counts['active'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Completed', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value" style="color: #007017;"><?php echo esc_html( $status_counts['completed'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Failed', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value" style="color: #d63638;"><?php echo esc_html( $status_counts['failed'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Cancelled', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value" style="color: #646970;"><?php echo esc_html( $status_counts['cancelled'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Refunded', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value" style="color: #d63638;"><?php echo esc_html( $status_counts['refunded'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Expired', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value" style="color: #646970;"><?php echo esc_html( $status_counts['expired'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Unknown', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value" style="color: #646970;"><?php echo esc_html( $status_counts['unknown'] ?? 0 ); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $recent_sessions ) ) : ?>
			<h4 style="margin-top: 30px;"><?php esc_html_e( 'Recent Sessions', 'carticy-ai-checkout-for-woocommerce' ); ?></h4>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Session ID', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Items', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Created', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Last Updated', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Shipping', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_sessions as $session ) : ?>
						<?php
						$time_remaining = null;
						if ( $session['expires_at'] ) {
							$time_remaining    = $session['expires_at'] - time();
							$hours_remaining   = floor( $time_remaining / 3600 );
							$minutes_remaining = floor( ( $time_remaining % 3600 ) / 60 );
						}

						$status_class = match ( $session['status'] ) {
							'active' => 'status-active',
							'completed' => 'status-success',
							'expired' => 'status-inactive',
							default => 'status-inactive',
						};
	?>
						<tr>
							<td><code><?php echo esc_html( substr( $session['session_id'], 0, 20 ) ) . '...'; ?></code></td>
							<td>
								<span class="status-indicator <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( ucfirst( $session['status'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $session['items_count'] ); ?></td>
							<td><?php echo esc_html( $session['created_at'] ? wp_date( 'Y-m-d H:i:s', $session['created_at'] ) : 'N/A' ); ?></td>
							<td><?php echo esc_html( $session['updated_at'] ? wp_date( 'Y-m-d H:i:s', $session['updated_at'] ) : 'N/A' ); ?></td>
							<td>
								<?php if ( null !== $time_remaining && $time_remaining > 0 ) : ?>
									<?php echo esc_html( sprintf( '%dh %dm', $hours_remaining, $minutes_remaining ) ); ?>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'Expired', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $session['has_shipping'] ) : ?>
									<span class="dashicons dashicons-yes" style="color: #008a00;"></span>
								<?php else : ?>
									<span class="dashicons dashicons-minus" style="color: #646970;"></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description" style="margin-top: 20px;">
				<?php esc_html_e( 'No active sessions found. Sessions are created when customers start the checkout process via ChatGPT.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $performance ) ) : ?>
		<div class="performance-metrics">
			<h3><?php esc_html_e( 'API Performance (Last 24 Hours)', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
			<div class="stats-grid">
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Total Requests', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $performance['api_requests']['total'] ?? 0 ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Avg Duration', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( sprintf( '%.3fs', $performance['api_requests']['average_duration'] ?? 0 ) ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Slowest Request', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( sprintf( '%.3fs', $performance['api_requests']['slowest_duration'] ?? 0 ) ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Avg Memory', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( sprintf( '%.2f MB', $performance['api_requests']['average_memory'] ?? 0 ) ); ?></span>
				</div>
				<div class="stat-card">
					<span class="stat-label"><?php esc_html_e( 'Avg DB Queries', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $performance['api_requests']['average_db_queries'] ?? 0 ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $performance['by_endpoint'] ) ) : ?>
				<h4><?php esc_html_e( 'Performance by Endpoint', 'carticy-ai-checkout-for-woocommerce' ); ?></h4>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Endpoint', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Count', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Avg Duration', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Slowest', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $performance['by_endpoint'] as $endpoint => $stats ) : ?>
							<tr>
								<td><code><?php echo esc_html( $endpoint ); ?></code></td>
								<td><?php echo esc_html( $stats['count'] ); ?></td>
								<td><?php echo esc_html( sprintf( '%.3fs', $stats['average_duration'] ) ); ?></td>
								<td><?php echo esc_html( sprintf( '%.3fs', $stats['slowest_duration'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $system_health ) ) : ?>
		<div class="system-health">
			<h3><?php esc_html_e( 'System Health', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'PHP Version', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $system_health['php_version'] ?? 'N/A' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Memory Limit', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $system_health['memory_limit'] ?? 'N/A' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Max Execution Time', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $system_health['max_execution_time'] ?? 'N/A' ); ?>s</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WordPress Version', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $system_health['wordpress_version'] ?? 'N/A' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WooCommerce Version', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $system_health['woocommerce_version'] ?? 'N/A' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Object Cache', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td>
							<span class="status-indicator <?php echo ( $system_health['cache_status']['object_cache'] ?? false ) ? 'status-active' : 'status-inactive'; ?>">
								<?php echo ( $system_health['cache_status']['object_cache'] ?? false ) ? esc_html__( 'Active', 'carticy-ai-checkout-for-woocommerce' ) : esc_html__( 'Inactive', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'OPCache', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<td>
							<span class="status-indicator <?php echo ( $system_health['cache_status']['opcode_cache'] ?? false ) ? 'status-active' : 'status-inactive'; ?>">
								<?php echo ( $system_health['cache_status']['opcode_cache'] ?? false ) ? esc_html__( 'Active', 'carticy-ai-checkout-for-woocommerce' ) : esc_html__( 'Inactive', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</span>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
