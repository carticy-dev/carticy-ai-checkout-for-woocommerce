<?php
/**
 * Error Logs Tab Template
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array $data Error logs data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entries           = $data['entries'] ?? array();
$statistics        = $data['statistics'] ?? array();
$selected_category = $data['selected_category'] ?? null;
?>

<div class="error-logs-tab">
	<div class="logs-header">
		<h2><?php esc_html_e( 'Error Logs', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
		<div class="logs-actions">
			<select id="error-category-filter" onchange="window.location.href=this.value;">
				<option value="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'     => 'carticy-ai-checkout-logs',
							'tab'      => 'errors',
							'_wpnonce' => wp_create_nonce( 'carticy_error_filter' ),
						),
						admin_url( 'admin.php' )
					)
				);
				?>
				">
					<?php esc_html_e( 'All Categories', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</option>
				<?php
				$categories = array(
					'payment'    => __( 'Payment', 'carticy-ai-checkout-for-woocommerce' ),
					'api'        => __( 'API', 'carticy-ai-checkout-for-woocommerce' ),
					'webhook'    => __( 'Webhook', 'carticy-ai-checkout-for-woocommerce' ),
					'validation' => __( 'Validation', 'carticy-ai-checkout-for-woocommerce' ),
					'system'     => __( 'System', 'carticy-ai-checkout-for-woocommerce' ),
				);
				foreach ( $categories as $category_id => $cat_label ) :
					?>
					<option value="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page'     => 'carticy-ai-checkout-logs',
								'tab'      => 'errors',
								'category' => $category_id,
								'_wpnonce' => wp_create_nonce( 'carticy_error_filter' ),
							),
							admin_url( 'admin.php' )
						)
					);
					?>
									"
							<?php selected( $selected_category, $category_id ); ?>>
						<?php echo esc_html( $cat_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<a href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'page'   => 'carticy-ai-checkout-logs',
							'action' => 'carticy_ai_checkout_clear_error_cache',
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
		<div class="error-statistics">
			<h3><?php esc_html_e( 'Error Statistics (Last 24 Hours)', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
			<div class="stats-grid">
				<?php foreach ( $categories as $category_id => $cat_label ) : ?>
					<div class="stat-card">
						<span class="stat-label"><?php echo esc_html( $cat_label ); ?></span>
						<span class="stat-value"><?php echo esc_html( $statistics[ $category_id ] ?? 0 ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( empty( $entries ) ) : ?>
		<div class="notice notice-success inline">
			<p><?php esc_html_e( 'No errors found. This is good!', 'carticy-ai-checkout-for-woocommerce' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Timestamp', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Category', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Message', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Context', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$context  = $entry['context']['context'] ?? array();
					$category = $context['category'] ?? 'unknown';
					?>
					<tr>
						<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
						<td><span class="error-category category-<?php echo esc_attr( $category ); ?>"><?php echo esc_html( ucfirst( $category ) ); ?></span></td>
						<td><?php echo esc_html( $entry['message'] ); ?></td>
						<td>
							<?php if ( ! empty( $context ) ) : ?>
								<button type="button" class="button button-small view-error-context-btn"
										data-context="<?php echo esc_attr( wp_json_encode( $context ) ); ?>"
										data-category="<?php echo esc_attr( ucfirst( $category ) ); ?>"
										data-message="<?php echo esc_attr( $entry['message'] ); ?>">
									<?php esc_html_e( 'View Details', 'carticy-ai-checkout-for-woocommerce' ); ?>
								</button>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'No additional context', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

