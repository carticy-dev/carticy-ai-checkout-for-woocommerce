<?php
/**
 * Analytics Dashboard Template
 *
 * Content area for analytics dashboard (wrapped by layout-wrapper.php)
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 * @var int   $days
 * @var int   $chatgpt_orders
 * @var float $chatgpt_revenue
 * @var int   $regular_orders
 * @var float $regular_revenue
 * @var float $conversion_rate
 * @var float $chatgpt_aov
 * @var float $regular_aov
 * @var array $recent_orders
 * @var array $status_stats
 * @var int   $total_orders
 * @var float $total_revenue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p class="description">
	<?php esc_html_e( 'Track ChatGPT order performance, conversion metrics, and revenue analytics.', 'carticy-ai-checkout-for-woocommerce' ); ?>
</p>

<!-- Date Filter -->
	<div class="date-filter">
		<form method="get" class="filter-form">
			<input type="hidden" name="page" value="carticy-ai-checkout-analytics">
			<?php wp_nonce_field( 'carticy_analytics_filter', '_wpnonce', false ); ?>
			<label for="days-filter"><?php esc_html_e( 'Date Range:', 'carticy-ai-checkout-for-woocommerce' ); ?></label>
			<select name="days" id="days-filter" onchange="this.form.submit()">
				<option value="7" <?php selected( $days, 7 ); ?>>
					<?php esc_html_e( 'Last 7 Days', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</option>
				<option value="30" <?php selected( $days, 30 ); ?>>
					<?php esc_html_e( 'Last 30 Days', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</option>
				<option value="90" <?php selected( $days, 90 ); ?>>
					<?php esc_html_e( 'Last 90 Days', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</option>
			</select>
		</form>
	</div>

	<!-- Metrics Overview -->
	<div class="analytics-metrics">
		<!-- ChatGPT Orders -->
		<div class="metric-card chatgpt">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20Z" fill="currentColor"/>
					<path d="M8 11C8.55 11 9 10.55 9 10C9 9.45 8.55 9 8 9C7.45 9 7 9.45 7 10C7 10.55 7.45 11 8 11Z" fill="currentColor"/>
					<path d="M16 11C16.55 11 17 10.55 17 10C17 9.45 16.55 9 16 9C15.45 9 15 9.45 15 10C15 10.55 15.45 11 16 11Z" fill="currentColor"/>
					<path d="M16 13H8C7.45 13 7 13.45 7 14C7 14.55 7.45 15 8 15H16C16.55 15 17 14.55 17 14C17 13.45 16.55 13 16 13Z" fill="currentColor"/>
				</svg>
			</div>
			<div class="metric-content">
				<div class="metric-value"><?php echo esc_html( number_format_i18n( $chatgpt_orders ) ); ?></div>
				<div class="metric-label"><?php esc_html_e( 'ChatGPT Orders', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
				<div class="metric-detail">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: formatted revenue amount */
							__( 'Revenue: %s', 'carticy-ai-checkout-for-woocommerce' ),
							wp_strip_all_tags( wc_price( $chatgpt_revenue ) )
						)
					);
					?>
				</div>
			</div>
		</div>

		<!-- Regular Orders -->
		<div class="metric-card regular">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M7 18C5.9 18 5.01 18.9 5.01 20C5.01 21.1 5.9 22 7 22C8.1 22 9 21.1 9 20C9 18.9 8.1 18 7 18ZM1 2V4H3L6.6 11.59L5.25 14.04C5.09 14.32 5 14.65 5 15C5 16.1 5.9 17 7 17H19V15H7.42C7.28 15 7.17 14.89 7.17 14.75L7.2 14.63L8.1 13H15.55C16.3 13 16.96 12.59 17.3 11.97L20.88 5.48C20.96 5.34 21 5.17 21 5C21 4.45 20.55 4 20 4H5.21L4.27 2H1ZM17 18C15.9 18 15.01 18.9 15.01 20C15.01 21.1 15.9 22 17 22C18.1 22 19 21.1 19 20C19 18.9 18.1 18 17 18Z" fill="currentColor"/>
				</svg>
			</div>
			<div class="metric-content">
				<div class="metric-value"><?php echo esc_html( number_format_i18n( $regular_orders ) ); ?></div>
				<div class="metric-label"><?php esc_html_e( 'Regular Orders', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
				<div class="metric-detail">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: formatted revenue amount */
							__( 'Revenue: %s', 'carticy-ai-checkout-for-woocommerce' ),
							wp_strip_all_tags( wc_price( $regular_revenue ) )
						)
					);
					?>
				</div>
			</div>
		</div>

		<!-- Conversion Rate -->
		<div class="metric-card conversion">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M3 13H5V19H3V13ZM7 9H9V19H7V9ZM11 5H13V19H11V5ZM15 9H17V19H15V9ZM19 13H21V19H19V13Z" fill="currentColor"/>
					<path d="M22 22H2V2H4V20H22V22Z" fill="currentColor"/>
				</svg>
			</div>
			<div class="metric-content">
				<div class="metric-value"><?php echo esc_html( number_format_i18n( $conversion_rate, 2 ) ); ?>%</div>
				<div class="metric-label"><?php esc_html_e( 'ChatGPT Conversion', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
				<div class="metric-detail">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: ChatGPT orders, 2: total orders */
							__( '%1$d of %2$d total orders', 'carticy-ai-checkout-for-woocommerce' ),
							$chatgpt_orders,
							$total_orders
						)
					);
					?>
				</div>
			</div>
		</div>

		<!-- Average Order Value -->
		<div class="metric-card aov">
			<div class="metric-icon">
				<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20Z" fill="currentColor"/>
					<path d="M12.5 6.5V8C13.45 8.24 14.19 8.89 14.54 9.72L13.13 10.42C12.95 10.05 12.5 9.75 12 9.75C11.31 9.75 10.75 10.31 10.75 11C10.75 11.69 11.31 12.25 12 12.25C13.24 12.25 14.25 13.26 14.25 14.5C14.25 15.45 13.65 16.26 12.83 16.62V18H11.17V16.61C10.21 16.37 9.46 15.73 9.09 14.89L10.5 14.19C10.68 14.57 11.12 14.87 11.62 14.87C12.31 14.87 12.87 14.31 12.87 13.62C12.87 12.93 12.31 12.37 11.62 12.37C10.38 12.37 9.37 11.36 9.37 10.12C9.37 9.18 9.96 8.37 10.77 8L10.5 6.5H12.5Z" fill="currentColor"/>
				</svg>
			</div>
			<div class="metric-content">
				<div class="metric-value">
					<?php echo esc_html( wp_strip_all_tags( wc_price( $chatgpt_aov ) ) ); ?>
				</div>
				<div class="metric-label"><?php esc_html_e( 'ChatGPT AOV', 'carticy-ai-checkout-for-woocommerce' ); ?></div>
				<div class="metric-detail">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: formatted AOV amount */
							__( 'Regular: %s', 'carticy-ai-checkout-for-woocommerce' ),
							wp_strip_all_tags( wc_price( $regular_aov ) )
						)
					);
					?>
				</div>
			</div>
		</div>
	</div>

	<!-- Status Distribution -->
	<div class="status-distribution">
		<h2><?php esc_html_e( 'ChatGPT Order Status Distribution', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
		<table class="widefat status-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Status', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Count', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Percentage', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $status_stats as $order_status => $count ) : ?>
					<?php if ( $count > 0 ) : ?>
						<tr>
							<td>
								<span class="status-badge status-<?php echo esc_attr( $order_status ); ?>">
									<?php echo esc_html( ucfirst( $order_status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
							<td>
								<?php
								$percentage = $chatgpt_orders > 0 ? ( $count / $chatgpt_orders ) * 100 : 0;
								echo esc_html( number_format_i18n( $percentage, 1 ) ) . '%';
								?>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Recent ChatGPT Orders -->
	<div class="recent-orders">
		<h2><?php esc_html_e( 'Recent ChatGPT Orders', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>

		<?php if ( empty( $recent_orders ) ) : ?>
			<p class="no-orders">
				<?php esc_html_e( 'No ChatGPT orders found in the selected date range.', 'carticy-ai-checkout-for-woocommerce' ); ?>
			</p>
		<?php else : ?>
			<table class="widefat orders-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Date', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Total', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_orders as $wc_order ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( $wc_order->get_edit_order_url() ); ?>">
									#<?php echo esc_html( $wc_order->get_order_number() ); ?>
								</a>
							</td>
							<td>
								<?php echo esc_html( $wc_order->get_date_created()->date_i18n( wc_date_format() ) ); ?>
							</td>
							<td>
								<?php
								$billing_name = $wc_order->get_formatted_billing_full_name();
								echo esc_html( $billing_name ? $billing_name : __( 'Guest', 'carticy-ai-checkout-for-woocommerce' ) );
								?>
							</td>
							<td><?php echo wp_kses_post( $wc_order->get_formatted_order_total() ); ?></td>
							<td>
								<span class="status-badge status-<?php echo esc_attr( $wc_order->get_status() ); ?>">
									<?php echo esc_html( wc_get_order_status_name( $wc_order->get_status() ) ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
