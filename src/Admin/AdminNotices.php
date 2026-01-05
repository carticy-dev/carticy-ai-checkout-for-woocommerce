<?php
/**
 * Admin Notices
 *
 * Displays admin notices and warnings
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Admin;

use Carticy\AiCheckout\Services\ErrorHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminNotices
 *
 * Handles display of admin notices and warnings
 */
final class AdminNotices {

	/**
	 * Error handler instance.
	 *
	 * @var ErrorHandler
	 */
	private ErrorHandler $error_handler;

	/**
	 * Constructor.
	 *
	 * @param ErrorHandler $error_handler Error handler instance.
	 */
	public function __construct( ErrorHandler $error_handler ) {
		$this->error_handler = $error_handler;

		add_action( 'admin_notices', array( $this, 'display_notices' ) );
		add_action( 'admin_init', array( $this, 'handle_notice_dismissal' ) );
	}

	/**
	 * Display admin notices
	 */
	public function display_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Only show notices on plugin pages or dashboard.
		if ( ! $this->should_show_notices( $screen->id ) ) {
			return;
		}

		// Show error notices.
		$this->show_error_notices();

		// Show configuration warnings.
		$this->show_configuration_warnings();
	}

	/**
	 * Check if notices should be shown on current screen
	 *
	 * @param string $screen_id Current screen ID.
	 * @return bool True if notices should be shown.
	 */
	private function should_show_notices( string $screen_id ): bool {
		$allowed_screens = array(
			'dashboard',
			'toplevel_page_ai-checkout',
			'ai-checkout_page_ai-checkout-settings',
			'ai-checkout_page_ai-checkout-products',
			'ai-checkout_page_ai-checkout-analytics',
			'ai-checkout_page_ai-checkout-wizard',
			'ai-checkout_page_ai-checkout-testing',
			'ai-checkout_page_ai-checkout-logs',
		);

		return in_array( $screen_id, $allowed_screens, true );
	}

	/**
	 * Show error notices from error handler
	 */
	private function show_error_notices(): void {
		$notices = $this->error_handler->get_admin_notices( false );

		if ( empty( $notices ) ) {
			return;
		}

		// Group notices by error code to avoid duplicates.
		$grouped_notices = array();
		foreach ( $notices as $index => $notice ) {
			$code = $notice['code'];
			if ( ! isset( $grouped_notices[ $code ] ) ) {
				$grouped_notices[ $code ] = array(
					'notice' => $notice,
					'index'  => $index,
					'count'  => 1,
				);
			} else {
				++$grouped_notices[ $code ]['count'];
			}
		}

		// Display grouped notices.
		foreach ( $grouped_notices as $code => $data ) {
			$notice      = $data['notice'];
			$count       = $data['count'];
			$notice_html = sprintf(
				'<strong>%s</strong>',
				esc_html( $notice['message'] )
			);

			if ( $count > 1 ) {
				$notice_html .= sprintf(
					' <em>(occurred %d times)</em>',
					$count
				);
			}

			if ( current_user_can( 'manage_woocommerce' ) && ! empty( $notice['technical'] ) ) {
				$notice_html .= sprintf(
					'<br><small style="color: #666;">Technical: %s</small>',
					esc_html( $notice['technical'] )
				);
			}

			$this->render_notice(
				'error',
				'AI Checkout Error',
				$notice_html,
				true,
				$data['index']
			);
		}
	}

	/**
	 * Show configuration warnings
	 */
	private function show_configuration_warnings(): void {
		// Check if API key is set.
		$api_key = get_option( 'carticy_ai_checkout_api_key', '' );
		if ( empty( $api_key ) && $this->is_plugin_page() ) {
			$this->render_notice(
				'warning',
				'API Key Not Configured',
				'Please generate an API key in <a href="' . admin_url( 'admin.php?page=ai-checkout-settings' ) . '">Settings</a> to enable ChatGPT Instant Checkout.',
				true
			);
		}

		// Check if in test mode.
		$test_mode = get_option( 'carticy_ai_checkout_test_mode', 'yes' );
		if ( 'yes' === $test_mode && $this->is_plugin_page() ) {
			$this->render_notice(
				'info',
				'Test Mode Active',
				'ChatGPT Instant Checkout is running in <strong>test mode</strong>. Disable test mode in <a href="' . admin_url( 'admin.php?page=ai-checkout-settings' ) . '">Settings</a> when ready for production.',
				true
			);
		}
	}

	/**
	 * Render a notice
	 *
	 * @param string   $type Notice type (error, warning, info, success).
	 * @param string   $title Notice title.
	 * @param string   $message Notice message (can contain HTML).
	 * @param bool     $dismissible Whether notice is dismissible.
	 * @param int|null $notice_index Notice index for dismissal.
	 */
	private function render_notice(
		string $type,
		string $title,
		string $message,
		bool $dismissible = false,
		?int $notice_index = null
	): void {
		$class = 'notice notice-' . $type;

		if ( $dismissible ) {
			$class .= ' is-dismissible';
		}

		$dismiss_url = '';
		if ( $dismissible && null !== $notice_index ) {
			$dismiss_url = add_query_arg(
				array(
					'action'       => 'carticy_dismiss_notice',
					'notice_index' => $notice_index,
					'_wpnonce'     => wp_create_nonce( 'carticy_dismiss_notice' ),
				),
				admin_url( 'admin.php' )
			);
		}

		?>
		<div class="<?php echo esc_attr( $class ); ?>" <?php echo $dismissible && null !== $notice_index ? 'data-dismiss-url="' . esc_url( $dismiss_url ) . '"' : ''; ?>>
			<p>
				<strong><?php echo esc_html( $title ); ?></strong><br>
				<?php echo wp_kses_post( $message ); ?>
			</p>
			<?php if ( $dismissible && null !== $notice_index ) : ?>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="notice-dismiss" style="text-decoration: none;">
					<span class="screen-reader-text">Dismiss this notice.</span>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle notice dismissal
	 */
	public function handle_notice_dismissal(): void {
		if ( ! isset( $_GET['action'] ) || 'carticy_dismiss_notice' !== $_GET['action'] ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'carticy_dismiss_notice' ) ) {
			return;
		}

		if ( ! isset( $_GET['notice_index'] ) ) {
			return;
		}

		$notice_index = absint( $_GET['notice_index'] );
		$this->error_handler->dismiss_notice( $notice_index );

		// Redirect back without query params.
		$redirect_url = remove_query_arg( array( 'action', 'notice_index', '_wpnonce' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Check if we're on a plugin page
	 *
	 * @return bool True if on plugin page.
	 */
	private function is_plugin_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'ai-checkout' ) === 0;
	}

	/**
	 * Add a success notice
	 *
	 * @param string $message Success message.
	 */
	public static function add_success_notice( string $message ): void {
		add_settings_error(
			'carticy_ai_checkout_notices',
			'carticy_success',
			$message,
			'success'
		);
	}

	/**
	 * Add an error notice
	 *
	 * @param string $message Error message.
	 */
	public static function add_error_notice( string $message ): void {
		add_settings_error(
			'carticy_ai_checkout_notices',
			'carticy_error',
			$message,
			'error'
		);
	}

	/**
	 * Add a warning notice
	 *
	 * @param string $message Warning message.
	 */
	public static function add_warning_notice( string $message ): void {
		add_settings_error(
			'carticy_ai_checkout_notices',
			'carticy_warning',
			$message,
			'warning'
		);
	}
}
