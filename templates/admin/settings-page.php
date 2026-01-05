<?php
/**
 * Settings Page Template
 *
 * Content area for settings page (wrapped by layout-wrapper.php)
 *
 * @package Carticy\AiCheckout
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables from parent scope.
 *
 * @var array  $prerequisites     Prerequisites check results
 * @var string $api_key           API key for authentication
 * @var string $webhook_secret    Webhook HMAC secret
 * @var string $product_feed_url  Product feed endpoint URL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if all prerequisites are met and add notice via settings API
$all_met = true;
foreach ( $prerequisites as $check ) {
	if ( ! $check['status'] ) {
		$all_met = false;
		break;
	}
}

// Add prerequisite status notice (will be displayed in notification area)
if ( ! $all_met ) {
	add_settings_error(
		'carticy_ai_checkout_settings',
		'prerequisites_not_met',
		'<strong>' . esc_html__( 'Some prerequisites are not met.', 'carticy-ai-checkout-for-woocommerce' ) . '</strong><br>' .
		esc_html__( 'Please resolve the issues below before using ChatGPT integration.', 'carticy-ai-checkout-for-woocommerce' ),
		'warning'
	);
} else {
	add_settings_error(
		'carticy_ai_checkout_settings',
		'prerequisites_met',
		'<strong>' . esc_html__( 'All prerequisites met!', 'carticy-ai-checkout-for-woocommerce' ) . '</strong><br>' .
		esc_html__( 'Your store is ready for ChatGPT integration.', 'carticy-ai-checkout-for-woocommerce' ),
		'success'
	);
}
?>

<h2 class="nav-tab-wrapper">
	<a href="#settings" class="nav-tab nav-tab-active"><?php esc_html_e( 'Settings', 'carticy-ai-checkout-for-woocommerce' ); ?></a>
	<a href="#security" class="nav-tab"><?php esc_html_e( 'Security', 'carticy-ai-checkout-for-woocommerce' ); ?></a>
	<a href="#api" class="nav-tab"><?php esc_html_e( 'API Configuration', 'carticy-ai-checkout-for-woocommerce' ); ?></a>
	<a href="#uninstall" class="nav-tab"><?php esc_html_e( 'Uninstall', 'carticy-ai-checkout-for-woocommerce' ); ?></a>
</h2>

<!-- Settings Tab -->
<div id="settings" class="tab-content">
	<!-- System Prerequisites Section -->
	<h2><?php esc_html_e( 'System Prerequisites', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<table class="widefat" style="margin-bottom: 30px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Check', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Status', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Message', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $prerequisites as $name => $check ) : ?>
				<tr>
					<td><?php echo esc_html( $check['label'] ); ?></td>
					<td>
						<?php if ( $check['status'] ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 20px;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-dismiss" style="color: #dc3232; font-size: 20px;"></span>
						<?php endif; ?>
					</td>
					<td>
						<?php echo esc_html( $check['message'] ); ?>
						<?php if ( ! $check['status'] && ! empty( $check['action_url'] ) ) : ?>
							<br>
							<a href="<?php echo esc_url( $check['action_url'] ); ?>" target="_blank" class="button button-small">
								<?php echo esc_html( $check['action_label'] ); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- General Settings Section -->
	<h2><?php esc_html_e( 'General Settings', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>
	<form method="post" action="options.php">
		<?php
		settings_fields( 'carticy_ai_checkout_settings' );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Test Mode', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="carticy_ai_checkout_test_mode" value="yes"
							<?php checked( get_option( 'carticy_ai_checkout_test_mode', 'yes' ), 'yes' ); ?>>
						<strong><?php esc_html_e( 'Enable plugin-wide test mode', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
					<?php
					$is_test     = 'yes' === get_option( 'carticy_ai_checkout_test_mode', 'yes' );
					$badge_color = $is_test ? '#f0ad4e' : '#5cb85c';
					$badge_label = $is_test ? __( 'TEST', 'carticy-ai-checkout-for-woocommerce' ) : __( 'LIVE', 'carticy-ai-checkout-for-woocommerce' );
					?>
					<span style="background: <?php echo esc_attr( $badge_color ); ?>; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; margin-left: 10px;">
						<?php echo esc_html( $badge_label ); ?>
					</span>
				</label>
				<p class="description" style="margin-top: 8px;">
					<strong><?php esc_html_e( 'Affects ALL API requests including real ChatGPT orders:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				</p>
				<ul style="margin: 5px 0 0 20px; font-size: 13px; color: #666;">
					<li><?php esc_html_e( 'When enabled: Bypasses SSL/HTTPS and IP allowlist checks (allows localhost testing)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'When disabled: Enforces SSL/HTTPS, validates OpenAI IP addresses (production mode)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Isolates test data with "test_" prefix in database', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Controls which Stripe API keys are used (test vs live)', 'carticy-ai-checkout-for-woocommerce' ); ?></li>
				</ul>
				<?php if ( ! $is_test && ! is_ssl() ) : ?>
					<div style="margin-top: 10px; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
						<strong style="color: #721c24;">⚠️ <?php esc_html_e( 'Warning: SSL/HTTPS Not Detected', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
						<p style="margin: 5px 0 0 0; color: #721c24;">
							<?php esc_html_e( 'Test mode is disabled but your site is not using HTTPS. All API requests will be rejected. Enable test mode for local development or install an SSL certificate.', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</p>
					</div>
				<?php endif; ?>
				<p class="description" style="margin-top: 8px; color: #999;">
					<?php esc_html_e( 'You can also configure test mode in the Application Wizard (Step 6).', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'OpenAI Webhook URL', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<td>
					<input type="url" name="carticy_ai_checkout_webhook_url" class="regular-text"
						value="<?php echo esc_attr( get_option( 'carticy_ai_checkout_webhook_url', '' ) ); ?>">
					<p class="description">
						<?php esc_html_e( 'Unique webhook URL provided by OpenAI during merchant onboarding (merchant-specific, required for production).', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>

<!-- Security Tab -->
<div id="security" class="tab-content" style="display:none;">
	<h2><?php esc_html_e( 'Security Settings', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>

	<div class="security-dashboard">
		<h3><?php esc_html_e( 'Security Status', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Feature', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Description', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'SSL/HTTPS', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
					<td>
						<?php if ( is_ssl() ) : ?>
							<span class="security-status-enabled">
								<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
								<?php esc_html_e( 'Enabled', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</span>
						<?php else : ?>
							<span class="security-status-warning">
								<span class="dashicons dashicons-warning" style="color: #f0ad4e;"></span>
								<?php esc_html_e( 'Not Enabled', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</span>
						<?php endif; ?>
					</td>
					<td><?php esc_html_e( 'Required for production API requests', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Bearer Token Auth', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
					<td>
					<span class="security-status-enabled">
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
						<?php esc_html_e( 'Active', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</span>
				</td>
					<td><?php esc_html_e( 'All API requests require valid Bearer token', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Rate Limiting', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
					<td>
					<span class="security-status-enabled">
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
						<?php esc_html_e( 'Active', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</span>
				</td>
					<td><?php esc_html_e( 'Prevents API abuse with per-endpoint limits', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Idempotency', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
					<td>
					<span class="security-status-enabled">
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
						<?php esc_html_e( 'Active', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</span>
				</td>
					<td><?php esc_html_e( 'Prevents duplicate request processing', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'IP Allowlisting', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
					<td>
						<?php
						$ip_allowlist_enabled = 'yes' === get_option( 'carticy_ai_checkout_enable_ip_allowlist', 'no' );
						if ( $ip_allowlist_enabled ) :
							?>
							<span class="security-status-enabled">
								<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
								<?php esc_html_e( 'Enabled', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</span>
						<?php else : ?>
							<span class="security-status-disabled">
								<span class="dashicons dashicons-marker" style="color: #999999;"></span>
								<?php esc_html_e( 'Disabled', 'carticy-ai-checkout-for-woocommerce' ); ?>
							</span>
						<?php endif; ?>
					</td>
					<td><?php esc_html_e( 'Restricts API access to OpenAI IP ranges', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<form method="post" action="options.php" style="margin-top: 30px;">
		<?php
		settings_fields( 'carticy_ai_checkout_settings' );
		?>
		<h3><?php esc_html_e( 'IP Allowlisting Configuration', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable IP Allowlisting', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="carticy_ai_checkout_enable_ip_allowlist" value="yes"
							<?php checked( get_option( 'carticy_ai_checkout_enable_ip_allowlist', 'no' ), 'yes' ); ?>>
						<?php esc_html_e( 'Only allow requests from OpenAI IP addresses', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, only requests from verified OpenAI IP ranges will be accepted. Automatically bypassed in test mode.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'OpenAI IP Ranges', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
		<?php
		$last_updated = (int) get_option( 'carticy_ai_checkout_openai_ip_ranges_last_updated', 0 );
		$ip_ranges    = get_option( 'carticy_ai_checkout_openai_ip_ranges_backup', array() );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Current IP Ranges', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<td>
					<?php if ( ! empty( $ip_ranges ) ) : ?>
						<div class="ip-ranges-list">
							<?php foreach ( array_slice( $ip_ranges, 0, 10 ) as $range ) : ?>
								<code><?php echo esc_html( $range ); ?></code>
							<?php endforeach; ?>
							<?php if ( count( $ip_ranges ) > 10 ) : ?>
								<p class="description">
									<?php
									/* translators: %d: number of additional IP ranges */
									printf( esc_html__( '... and %d more ranges', 'carticy-ai-checkout-for-woocommerce' ), count( $ip_ranges ) - 10 );
									?>
								</p>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No IP ranges loaded yet. Click "Refresh IP Ranges" to fetch from OpenAI.', 'carticy-ai-checkout-for-woocommerce' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last Updated', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<td>
					<?php
					if ( $last_updated > 0 ) {
						/* translators: %s: human-readable time difference */
						printf( esc_html__( '%s ago', 'carticy-ai-checkout-for-woocommerce' ), esc_html( human_time_diff( $last_updated ) ) );
					} else {
						esc_html_e( 'Never', 'carticy-ai-checkout-for-woocommerce' );
					}
					?>
					<p>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=carticy_ai_checkout_refresh_ip_ranges' ), 'carticy_refresh_ips' ) ); ?>" class="button">
							<?php esc_html_e( 'Refresh IP Ranges Now', 'carticy-ai-checkout-for-woocommerce' ); ?>
						</a>
					</p>
					<p class="description">
						<?php esc_html_e( 'IP ranges are automatically updated hourly. Click to manually refresh now.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>

<!-- API Configuration Tab -->
<div id="api" class="tab-content" style="display:none;">
	<h2><?php esc_html_e( 'API Configuration', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>

	<!-- Authentication & Secrets Section -->
	<h3><?php esc_html_e( 'Authentication & Secrets', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'API Key (Bearer Token)', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
			<td>
				<input type="text" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" readonly id="api-key">
				<button type="button" class="button button-secondary copy-button" data-clipboard-target="#api-key">
					<?php esc_html_e( 'Copy', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-secondary regenerate-api-key">
					<?php esc_html_e( 'Regenerate', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'Include this key in Authorization header: Bearer {key}', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Webhook Secret (HMAC)', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
			<td>
				<input type="text" class="regular-text" value="<?php echo esc_attr( $webhook_secret ); ?>" readonly id="webhook-secret">
				<button type="button" class="button button-secondary copy-button" data-clipboard-target="#webhook-secret">
					<?php esc_html_e( 'Copy', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</button>
				<button type="button" class="button button-secondary regenerate-webhook-secret">
					<?php esc_html_e( 'Regenerate', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'Used to sign outgoing webhooks to OpenAI with HMAC-SHA256.', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<!-- API Endpoints Section -->
	<h3 style="margin-top: 30px;"><?php esc_html_e( 'Available Endpoints', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'All endpoints use base namespace:', 'carticy-ai-checkout-for-woocommerce' ); ?>
		<code><?php echo esc_html( rest_url( 'carticy-ai-checkout/v1/' ) ); ?></code>
	</p>

	<table class="widefat" style="margin-top: 15px;">
		<thead>
			<tr>
				<th style="width: 10%;"><?php esc_html_e( 'Method', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<th style="width: 40%;"><?php esc_html_e( 'Endpoint', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<th style="width: 50%;"><?php esc_html_e( 'Description', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<!-- Product Feed Endpoint -->
			<tr>
				<td><strong style="color: #2271b1;">GET</strong></td>
				<td>
					<code>/products</code>
					<br>
					<input type="text" class="regular-text" value="<?php echo esc_url( $product_feed_url ); ?>" readonly id="product-feed-url" style="margin-top: 5px;">
				</td>
				<td>
					<?php esc_html_e( 'Retrieve product feed for ChatGPT integration', 'carticy-ai-checkout-for-woocommerce' ); ?>
					<br>
					<button type="button" class="button button-secondary copy-button" data-clipboard-target="#product-feed-url" style="margin-top: 5px;">
						<?php esc_html_e( 'Copy', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</button>
					<a href="<?php echo esc_url( $product_feed_url ); ?>" target="_blank" class="button button-secondary" style="margin-top: 5px;">
						<?php esc_html_e( 'View', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</a>
				</td>
			</tr>

			<!-- Checkout Session Endpoints -->
			<tr style="background-color: #f9f9f9;">
				<td colspan="3" style="padding: 8px 10px; font-weight: 600;">
					<?php esc_html_e( 'Checkout Session Endpoints (ACP)', 'carticy-ai-checkout-for-woocommerce' ); ?>
				</td>
			</tr>
			<tr>
				<td><strong style="color: #d63638;">POST</strong></td>
				<td><code>/checkout_sessions</code></td>
				<td><?php esc_html_e( 'Create new checkout session with items and addresses', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
			</tr>
			<tr>
				<td><strong style="color: #2271b1;">GET</strong></td>
				<td><code>/checkout_sessions/{id}</code></td>
				<td><?php esc_html_e( 'Retrieve existing checkout session details', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
			</tr>
			<tr>
				<td><strong style="color: #d63638;">POST</strong></td>
				<td><code>/checkout_sessions/{id}</code></td>
				<td><?php esc_html_e( 'Update session (items, addresses, shipping method, coupons)', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
			</tr>
			<tr>
				<td><strong style="color: #d63638;">POST</strong></td>
				<td><code>/checkout_sessions/{id}/complete</code></td>
				<td><?php esc_html_e( 'Complete checkout with SharedPaymentToken and create order', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
			</tr>
			<tr>
				<td><strong style="color: #d63638;">POST</strong></td>
				<td><code>/checkout_sessions/{id}/cancel</code></td>
				<td><?php esc_html_e( 'Cancel checkout session and clear session data', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
			</tr>
		</tbody>
	</table>

	<!-- Required Headers Section -->
	<h3 style="margin-top: 30px;"><?php esc_html_e( 'Required Headers', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
	<table class="widefat">
		<thead>
			<tr>
				<th style="width: 30%;"><?php esc_html_e( 'Header', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<th style="width: 70%;"><?php esc_html_e( 'Description', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><code>Authorization</code></td>
				<td>
					<?php esc_html_e( 'Bearer token authentication (required for all endpoints)', 'carticy-ai-checkout-for-woocommerce' ); ?>
					<br>
					<code style="font-size: 11px;">Bearer <?php echo esc_html( substr( $api_key, 0, 20 ) . '...' ); ?></code>
				</td>
			</tr>
			<tr>
				<td><code>Idempotency-Key</code></td>
				<td><?php esc_html_e( 'Unique identifier to prevent duplicate processing (recommended for POST endpoints)', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
			</tr>
			<tr>
				<td><code>API-Version</code></td>
				<td>
					<?php esc_html_e( 'OpenAI ACP API version (optional, defaults to 2024-10-01)', 'carticy-ai-checkout-for-woocommerce' ); ?>
					<br>
					<code style="font-size: 11px;">2024-10-01</code>
				</td>
			</tr>
		</tbody>
	</table>

	<!-- Outgoing Webhooks Section -->
	<h3 style="margin-top: 30px;"><?php esc_html_e( 'Outgoing Webhooks', 'carticy-ai-checkout-for-woocommerce' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Webhook events sent to OpenAI for order status updates (ACP v1.0 compliant).', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>
	<table class="widefat" style="margin-top: 15px;">
		<thead>
			<tr>
				<th style="width: 25%;"><?php esc_html_e( 'Event Type', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<th style="width: 75%;"><?php esc_html_e( 'When Triggered', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><code>order_created</code></td>
				<td><?php esc_html_e( 'Sent immediately when order is created after successful payment', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
			</tr>
			<tr>
				<td><code>order_updated</code></td>
				<td><?php esc_html_e( 'Sent when order status changes (completed, cancelled, refunded, disputed)', 'carticy-ai-checkout-for-woocommerce' ); ?></td>
			</tr>
		</tbody>
	</table>
	<p class="description" style="margin-top: 10px;">
		<?php esc_html_e( 'All webhooks include HMAC-SHA256 signature in Merchant-Signature header.', 'carticy-ai-checkout-for-woocommerce' ); ?>
	</p>
</div>

<!-- Uninstall Tab -->
<div id="uninstall" class="tab-content" style="display:none;">
	<h2><?php esc_html_e( 'Uninstall Options', 'carticy-ai-checkout-for-woocommerce' ); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields( 'carticy_ai_checkout_settings' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Delete Data on Uninstall', 'carticy-ai-checkout-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="carticy_ai_checkout_delete_data_on_uninstall" value="yes"
							<?php checked( get_option( 'carticy_ai_checkout_delete_data_on_uninstall', 'no' ), 'yes' ); ?>>
						<?php esc_html_e( 'Remove all plugin data when uninstalling', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, all settings, transients, and metadata will be permanently deleted on uninstall.', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
