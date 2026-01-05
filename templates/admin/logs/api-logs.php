<?php
/**
 * API Logs Tab Template
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array $data API logs data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entries    = $data['entries'] ?? array();
$file_count = $data['file_count'] ?? 0;
$pagination = $data['pagination'] ?? array(
	'current_page'  => 1,
	'per_page'      => 25,
	'total_entries' => 0,
	'total_pages'   => 1,
);

$start_entry = $pagination['total_entries'] > 0 ? ( ( $pagination['current_page'] - 1 ) * $pagination['per_page'] ) + 1 : 0;
$end_entry   = min( $start_entry + count( $entries ) - 1, $pagination['total_entries'] );
?>

<div class="api-logs-tab">
	<div class="logs-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
		<div>
			<h2 style="margin: 0 0 5px 0;"><?php esc_html_e( 'API Request Logs', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
			<p class="description" style="margin: 0;">
				<?php
				if ( $pagination['total_entries'] > 0 ) {
					printf(
						/* translators: 1: start entry number, 2: end entry number, 3: total entries, 4: log file count. */
						esc_html__( 'Showing %1$d-%2$d of %3$d entries from %4$d log file(s).', 'carticy-ai-checkout-for-woocommerce' ),
						absint( $start_entry ),
						absint( $end_entry ),
						absint( $pagination['total_entries'] ),
						absint( $file_count )
					);
				} else {
					printf(
						/* translators: %d: number of log files. */
						esc_html__( '%d log file(s) available.', 'carticy-ai-checkout-for-woocommerce' ),
						absint( $file_count )
					);
				}
				?>
			</p>
		</div>
		<?php if ( $pagination['total_entries'] > 0 ) : ?>
			<div class="logs-per-page" style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
				<label for="logs-per-page-select" style="margin: 0;"><?php esc_html_e( 'Show:', 'carticy-ai-checkout-for-woocommerce' ); ?></label>
				<select id="logs-per-page-select" onchange="window.location.href=this.value;">
					<?php
					$carticy_per_page_options = array( 10, 25, 50, 100 );
					foreach ( $carticy_per_page_options as $carticy_option ) {
						$carticy_url      = add_query_arg(
							array(
								'page'     => 'carticy-ai-checkout-logs',
								'tab'      => 'api',
								'per_page' => $carticy_option,
								'paged'    => 1,
							),
							admin_url( 'admin.php' )
						);
						$carticy_selected = $pagination['per_page'] === $carticy_option ? 'selected' : '';
						printf(
							'<option value="%s" %s>%d %s</option>',
							esc_url( $carticy_url ),
							esc_attr( $carticy_selected ),
							absint( $carticy_option ),
							esc_html__( 'entries', 'carticy-ai-checkout-for-woocommerce' )
						);
					}
					?>
				</select>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $entries ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'No API logs found. Logs will appear here when API requests are made.', 'carticy-ai-checkout-for-woocommerce' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped api-logs-table">
			<thead>
				<tr>
					<th class="column-timestamp"><?php esc_html_e( 'Timestamp', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th class="column-level"><?php esc_html_e( 'Level', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th class="column-endpoint"><?php esc_html_e( 'Endpoint', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th class="column-method"><?php esc_html_e( 'Method', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th class="column-duration"><?php esc_html_e( 'Duration', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th class="column-details"><?php esc_html_e( 'Details', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$context       = $entry['context']['context'] ?? array();
					$endpoint      = $context['endpoint'] ?? 'N/A';
					$method        = strtoupper( $context['method'] ?? 'N/A' );
					$status_code   = $context['status_code'] ?? 0;
					$duration      = $context['duration'] ?? 0;
					$level         = strtoupper( $entry['level'] ?? 'info' );
					$request_data  = $context['request'] ?? null;
					$response_data = $context['response'] ?? null;

					$level_class = match ( strtolower( $level ) ) {
						'error' => 'log-level-error',
						'warning' => 'log-level-warning',
						'info' => 'log-level-info',
						default => 'log-level-debug',
					};

					$status_class = $status_code >= 500 ? 'status-error' :
									( $status_code >= 400 ? 'status-warning' : 'status-success' );

					// Use clean message (context JSON already removed by LoggingService)
					$clean_message = $entry['message'];
	?>
					<tr>
						<td class="column-timestamp"><?php echo esc_html( $entry['timestamp'] ); ?></td>
						<td class="column-level"><span class="log-level <?php echo esc_attr( $level_class ); ?>"><?php echo esc_html( $level ); ?></span></td>
						<td class="column-endpoint"><code><?php echo esc_html( $endpoint ); ?></code></td>
						<td class="column-method"><span class="http-method"><?php echo esc_html( $method ); ?></span></td>
						<td class="column-status"><span class="status-code <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_code ); ?></span></td>
						<td class="column-duration"><?php echo esc_html( sprintf( '%.3fs', $duration ) ); ?></td>
						<td class="column-details">
							<?php if ( null !== $request_data || null !== $response_data ) : ?>
								<button type="button" class="button button-small view-api-context-btn"
										data-request="<?php echo esc_attr( wp_json_encode( $request_data ) ); ?>"
										data-response="<?php echo esc_attr( wp_json_encode( $response_data ) ); ?>"
										data-endpoint="<?php echo esc_attr( $endpoint ); ?>">
									<?php esc_html_e( 'View Context', 'carticy-ai-checkout-for-woocommerce' ); ?>
								</button>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'No data', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pagination['total_pages'] > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %s: number of items. */
							esc_html( _n( '%s item', '%s items', $pagination['total_entries'], 'carticy-ai-checkout-for-woocommerce' ) ),
							esc_html( number_format_i18n( $pagination['total_entries'] ) )
						);
						?>
					</span>
					<?php
					$base_url = add_query_arg(
						array(
							'page'     => 'carticy-ai-checkout-logs',
							'tab'      => 'api',
							'per_page' => $pagination['per_page'],
						),
						admin_url( 'admin.php' )
					);

					$prev_page = max( 1, $pagination['current_page'] - 1 );
					$next_page = min( $pagination['total_pages'], $pagination['current_page'] + 1 );

					$prev_url  = add_query_arg( 'paged', $prev_page, $base_url );
					$next_url  = add_query_arg( 'paged', $next_page, $base_url );
					$first_url = add_query_arg( 'paged', 1, $base_url );
					$last_url  = add_query_arg( 'paged', $pagination['total_pages'], $base_url );

					$prev_class = $pagination['current_page'] <= 1 ? 'disabled' : '';
					$next_class = $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '';
					?>
					<span class="pagination-links">
						<a class="first-page button <?php echo esc_attr( $prev_class ); ?>"
							href="<?php echo esc_url( $first_url ); ?>"
							<?php echo $prev_class ? 'aria-disabled="true"' : ''; ?>>
							<span class="screen-reader-text"><?php esc_html_e( 'First page', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span aria-hidden="true">&laquo;</span>
						</a>
						<a class="prev-page button <?php echo esc_attr( $prev_class ); ?>"
							href="<?php echo esc_url( $prev_url ); ?>"
							<?php echo $prev_class ? 'aria-disabled="true"' : ''; ?>>
							<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span aria-hidden="true">&lsaquo;</span>
						</a>
						<span class="paging-input">
							<label for="current-page-selector" class="screen-reader-text"><?php esc_html_e( 'Current Page', 'carticy-ai-checkout-for-woocommerce' ); ?></label>
							<input class="current-page" id="current-page-selector"
									type="text"
									name="paged"
									value="<?php echo esc_attr( $pagination['current_page'] ); ?>"
									size="<?php echo absint( strlen( (string) $pagination['total_pages'] ) ); ?>"
									aria-describedby="table-paging" />
							<span class="tablenav-paging-text">
								<?php
								printf(
									/* translators: %s: total number of pages. */
									esc_html__( 'of %s', 'carticy-ai-checkout-for-woocommerce' ),
									'<span class="total-pages">' . esc_html( number_format_i18n( $pagination['total_pages'] ) ) . '</span>'
								);
								?>
							</span>
						</span>
						<a class="next-page button <?php echo esc_attr( $next_class ); ?>"
							href="<?php echo esc_url( $next_url ); ?>"
							<?php echo $next_class ? 'aria-disabled="true"' : ''; ?>>
							<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span aria-hidden="true">&rsaquo;</span>
						</a>
						<a class="last-page button <?php echo esc_attr( $next_class ); ?>"
							href="<?php echo esc_url( $last_url ); ?>"
							<?php echo $next_class ? 'aria-disabled="true"' : ''; ?>>
							<span class="screen-reader-text"><?php esc_html_e( 'Last page', 'carticy-ai-checkout-for-woocommerce' ); ?></span>
							<span aria-hidden="true">&raquo;</span>
						</a>
					</span>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

