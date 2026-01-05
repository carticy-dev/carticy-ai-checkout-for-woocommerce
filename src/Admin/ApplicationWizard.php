<?php
/**
 * Application Wizard Admin Page
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Admin;

use Carticy\AiCheckout\Services\ApplicationWizardService;
use Carticy\AiCheckout\Services\ProductQualityChecker;
use Carticy\AiCheckout\Services\PrerequisitesChecker;

/**
 * Handles application wizard admin page
 */
final class ApplicationWizard {
	/**
	 * Wizard service
	 *
	 * @var ApplicationWizardService
	 */
	private ApplicationWizardService $wizard_service;

	/**
	 * Product quality checker
	 *
	 * @var ProductQualityChecker
	 */
	private ProductQualityChecker $quality_checker;

	/**
	 * Prerequisites checker
	 *
	 * @var PrerequisitesChecker
	 */
	private PrerequisitesChecker $prerequisites;

	/**
	 * Constructor
	 *
	 * @param ApplicationWizardService $wizard_service Wizard service instance.
	 * @param ProductQualityChecker    $quality_checker Product quality checker instance.
	 * @param PrerequisitesChecker     $prerequisites Prerequisites checker instance.
	 */
	public function __construct(
		ApplicationWizardService $wizard_service,
		ProductQualityChecker $quality_checker,
		PrerequisitesChecker $prerequisites
	) {
		$this->wizard_service  = $wizard_service;
		$this->quality_checker = $quality_checker;
		$this->prerequisites   = $prerequisites;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_post_carticy_ai_checkout_wizard_save_step', array( $this, 'handle_save_step' ) );
		add_action( 'admin_post_carticy_ai_checkout_wizard_navigate', array( $this, 'handle_navigation' ) );
		add_action( 'admin_post_carticy_ai_checkout_wizard_reset', array( $this, 'handle_reset' ) );

		// New test setup handlers.
		add_action( 'admin_post_carticy_ai_checkout_wizard_toggle_test_mode', array( $this, 'handle_toggle_test_mode' ) );
		add_action( 'admin_post_carticy_ai_checkout_wizard_save_test_webhook', array( $this, 'handle_save_test_webhook' ) );
		add_action( 'admin_post_carticy_ai_checkout_wizard_save_security', array( $this, 'handle_save_security' ) );

		// AJAX handler for running tests.
		add_action( 'wp_ajax_carticy_ai_checkout_run_conformance_tests', array( $this, 'ajax_run_conformance_tests' ) );

		// AJAX handler for quick fixes.
		add_action( 'wp_ajax_carticy_ai_checkout_enable_robots_filter', array( $this, 'ajax_enable_robots_filter' ) );

		// AJAX handler for saving test results.
		add_action( 'wp_ajax_carticy_ai_checkout_save_test_results', array( $this, 'ajax_save_test_results' ) );
	}

	/**
	 * Render wizard page
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_step = $this->wizard_service->get_current_step();
		$wizard_data  = $this->wizard_service->get_wizard_data();
		$total_steps  = $this->wizard_service->get_total_steps();
		$completion   = $this->wizard_service->get_completion_percentage();

		?>
		<div class="wrap carticy-ai-checkout">
			<div class="carticy-wizard-container">
				<!-- Wizard Header -->
				<div class="wizard-header">
					<h1><?php esc_html_e( 'OpenAI Application Wizard', 'carticy-ai-checkout-for-woocommerce' ); ?></h1>
					<p><?php esc_html_e( 'This wizard will guide you through preparing your application for OpenAI ChatGPT integration.', 'carticy-ai-checkout-for-woocommerce' ); ?></p>
				</div>

				<!-- Progress Bar -->
				<div class="wizard-progress">
					<div class="progress-bar">
						<div class="progress-fill" style="width: <?php echo esc_attr( $completion ); ?>%;"></div>
					</div>
					<div class="progress-steps">
						<?php for ( $i = 1; $i <= $total_steps; $i++ ) : ?>
							<?php
							$step_class = 'progress-step';
							if ( $i === $current_step ) {
								$step_class .= ' active';
							} elseif ( $this->wizard_service->is_step_completed( $i ) ) {
								$step_class .= ' completed';
							}
							?>
							<div class="<?php echo esc_attr( $step_class ); ?>">
								<?php echo esc_html( $this->get_step_title( $i ) ); ?>
							</div>
						<?php endfor; ?>
					</div>
				</div>

				<!-- Step Content -->
				<div class="wizard-content">
					<?php $this->render_step( $current_step ); ?>
				</div>
			</div>


		<!-- Wizard Information Notice -->
		<div class="notice notice-info inline" style="margin-top: 20px;">
			<p>
				<strong><?php esc_html_e( 'About This Wizard:', 'carticy-ai-checkout-for-woocommerce' ); ?></strong>
				<?php
				printf(
					/* translators: %s: URL to OpenAI merchant form */
					esc_html__( 'This wizard helps you prepare application data for OpenAI. Final submission is done manually through OpenAI\'s web form at %s - there is no automated submission API.', 'carticy-ai-checkout-for-woocommerce' ),
					'chatgpt.com/merchants'
				);
				?>
			</p>
		</div>

		<!-- Reset Button -->
		<div class="wizard-reset">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
					<?php wp_nonce_field( 'carticy_wizard_reset' ); ?>
					<input type="hidden" name="action" value="carticy_ai_checkout_wizard_reset">
					<button type="submit" class="button wizard-reset-button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to restart the wizard? All progress will be lost.', 'carticy-ai-checkout-for-woocommerce' ); ?>');">
						<?php esc_html_e( 'Restart Wizard', 'carticy-ai-checkout-for-woocommerce' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Get step title
	 *
	 * @param int $step Step number.
	 * @return string Step title.
	 */
	private function get_step_title( int $step ): string {
		return match ( $step ) {
			1 => __( 'Business Info', 'carticy-ai-checkout-for-woocommerce' ),
			2 => __( 'Product Feed', 'carticy-ai-checkout-for-woocommerce' ),
			3 => __( 'Policies', 'carticy-ai-checkout-for-woocommerce' ),
			4 => __( 'Data Quality', 'carticy-ai-checkout-for-woocommerce' ),
			5 => __( 'Prerequisites', 'carticy-ai-checkout-for-woocommerce' ),
			6 => __( 'Test Setup', 'carticy-ai-checkout-for-woocommerce' ),
			7 => __( 'Integration', 'carticy-ai-checkout-for-woocommerce' ),
			8 => __( 'Run Tests', 'carticy-ai-checkout-for-woocommerce' ),
			9 => __( 'Submit', 'carticy-ai-checkout-for-woocommerce' ),
			default => '',
		};
	}

	/**
	 * Render specific step
	 *
	 * @param int $step Step number.
	 * @return void
	 */
	private function render_step( int $step ): void {
		$wizard_data = $this->wizard_service->get_wizard_data();

		$template_map = array(
			1 => 'step-1-business-info.php',
			2 => 'step-2-product-feed.php',
			3 => 'step-3-policies.php',
			4 => 'step-4-data-quality.php',
			5 => 'step-5-prerequisites.php',
			6 => 'step-6-test-setup.php',
			7 => 'step-7-integration.php',
			8 => 'step-8-conformance.php',
			9 => 'step-9-submit.php',
		);

		$template = $template_map[ $step ] ?? '';
		if ( empty( $template ) ) {
			return;
		}

		$template_path = dirname( dirname( __DIR__ ) ) . '/templates/admin/wizard/' . $template;

		if ( file_exists( $template_path ) ) {
			// Pass data to template.
			$data            = $wizard_data;
			$wizard_service  = $this->wizard_service;
			$quality_checker = $this->quality_checker;
			$prerequisites   = $this->prerequisites;

			include $template_path;
		}
	}

	/**
	 * Handle step save
	 *
	 * @return void
	 */
	public function handle_save_step(): void {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'carticy_wizard_step' ) ) {
			wp_die( esc_html__( 'Security check failed', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		$step = isset( $_POST['step'] ) ? (int) $_POST['step'] : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array sanitized per-field below.
		$step_data = isset( $_POST['step_data'] ) ? (array) wp_unslash( $_POST['step_data'] ) : array();

		$errors = array();

		// Validate based on step.
		switch ( $step ) {
			case 1:
				$sanitized = array(
					'business_name'   => sanitize_text_field( $step_data['business_name'] ?? '' ),
					'business_entity' => sanitize_text_field( $step_data['business_entity'] ?? '' ),
					'contact_name'    => sanitize_text_field( $step_data['contact_name'] ?? '' ),
					'contact_email'   => sanitize_email( $step_data['contact_email'] ?? '' ),
					'store_url'       => esc_url_raw( $step_data['store_url'] ?? '' ),
				);

				$validation = $this->wizard_service->validate_business_info( $sanitized );
				if ( is_array( $validation ) ) {
					$errors = $validation;
				} else {
					$this->wizard_service->save_step_data( $step, $sanitized );
				}
				break;

			case 2:
				// Product feed configuration (saved via AJAX in step template).
				$this->wizard_service->save_step_data( $step, array() );
				break;

			case 3:
				$sanitized = array(
					'terms_of_service' => esc_url_raw( $step_data['terms_of_service'] ?? '' ),
					'privacy_policy'   => esc_url_raw( $step_data['privacy_policy'] ?? '' ),
					'return_policy'    => esc_url_raw( $step_data['return_policy'] ?? '' ),
				);

				$validation = $this->wizard_service->validate_policies( $sanitized );
				if ( is_array( $validation ) ) {
					$errors = $validation;
				} else {
					$this->wizard_service->save_step_data( $step, $sanitized );
				}
				break;

			case 4:
			case 5:
			case 6:
			case 7:
			case 8:
			case 9:
				// These steps are informational, auto-generated, or configuration-based.
				$this->wizard_service->save_step_data( $step, array() );
				break;
		}

		if ( ! empty( $errors ) ) {
			// Store errors in transient for field-level validation.
			set_transient( 'carticy_ai_checkout_wizard_errors_' . get_current_user_id(), $errors, 30 );

			// Add aggregated error message using WordPress settings errors system.
			$error_list = '<ul><li>' . implode( '</li><li>', array_values( $errors ) ) . '</li></ul>';
			add_settings_error(
				'carticy_ai_checkout_wizard',
				'validation_failed',
				'<strong>' . __( 'Please correct the following errors:', 'carticy-ai-checkout-for-woocommerce' ) . '</strong>' . $error_list,
				'error'
			);

			// Persist settings errors across redirect.
			set_transient( 'settings_errors', get_settings_errors(), 30 );

			$redirect = add_query_arg(
				array(
					'page'             => 'carticy-ai-checkout-application-wizard',
					'settings-updated' => 'error',
				),
				admin_url( 'admin.php' )
			);
		} else {
			// Move to next step.
			$next_step = min( $step + 1, $this->wizard_service->get_total_steps() );
			$this->wizard_service->set_current_step( $next_step );

			$redirect = add_query_arg(
				array(
					'page'  => 'carticy-ai-checkout-application-wizard',
					'saved' => '1',
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle wizard navigation
	 *
	 * @return void
	 */
	public function handle_navigation(): void {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'carticy_wizard_navigate' ) ) {
			wp_die( esc_html__( 'Security check failed', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		$direction    = isset( $_POST['direction'] ) ? sanitize_text_field( wp_unslash( $_POST['direction'] ) ) : '';
		$current_step = $this->wizard_service->get_current_step();

		if ( 'next' === $direction ) {
			$new_step = min( $current_step + 1, $this->wizard_service->get_total_steps() );
		} elseif ( 'prev' === $direction ) {
			$new_step = max( $current_step - 1, 1 );

			// When going backwards, remove current step and all future steps from completed_steps.
			$wizard_data                    = $this->wizard_service->get_wizard_data();
			$completed_steps                = $wizard_data['completed_steps'] ?? array();
			$completed_steps                = array_filter( $completed_steps, fn( $step ) => $step < $new_step );
			$wizard_data['completed_steps'] = array_values( $completed_steps );
			$this->wizard_service->save_wizard_data( $wizard_data );
		} else {
			$new_step = $current_step;
		}

		$this->wizard_service->set_current_step( $new_step );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'carticy-ai-checkout-application-wizard' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle wizard reset
	 *
	 * @return void
	 */
	public function handle_reset(): void {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'carticy_wizard_reset' ) ) {
			wp_die( esc_html__( 'Security check failed', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		$this->wizard_service->clear_wizard_data();

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'carticy-ai-checkout-application-wizard' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle toggle test mode
	 *
	 * @return void
	 */
	public function handle_toggle_test_mode(): void {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'carticy_wizard_test_setup' ) ) {
			wp_die( esc_html__( 'Security check failed', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		$current_mode = get_option( 'carticy_ai_checkout_test_mode', 'yes' );
		$new_mode     = ( 'yes' === $current_mode ) ? 'no' : 'yes';
		update_option( 'carticy_ai_checkout_test_mode', $new_mode );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'carticy-ai-checkout-application-wizard',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle save test webhook URL
	 *
	 * @return void
	 */
	public function handle_save_test_webhook(): void {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'carticy_wizard_test_setup' ) ) {
			wp_die( esc_html__( 'Security check failed', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		$test_webhook_url = isset( $_POST['test_webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['test_webhook_url'] ) ) : '';
		update_option( 'carticy_ai_checkout_test_webhook_url', $test_webhook_url );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'carticy-ai-checkout-application-wizard',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle save security settings
	 *
	 * @return void
	 */
	public function handle_save_security(): void {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'carticy_wizard_integration' ) ) {
			wp_die( esc_html__( 'Security check failed', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) );
		}

		$enable_ip_allowlist = isset( $_POST['enable_ip_allowlist'] ) && 'yes' === $_POST['enable_ip_allowlist'] ? 'yes' : 'no';
		update_option( 'carticy_ai_checkout_enable_ip_allowlist', $enable_ip_allowlist );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'carticy-ai-checkout-application-wizard',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX handler for running conformance tests
	 *
	 * @return void
	 */
	public function ajax_run_conformance_tests(): void {
		// Verify nonce.
		check_ajax_referer( 'carticy_wizard_tests', 'nonce' );

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) ),
				403
			);
		}

		// Get conformance test service.
		$init = \Carticy\AiCheckout\Init::get_instance();

		if ( ! $init ) {
			wp_send_json_error( array( 'message' => 'Plugin not initialized' ), 500 );
		}

		$conformance_service = $init->get_service( 'conformance_test' );

		if ( ! $conformance_service ) {
			wp_send_json_error( array( 'message' => 'Conformance test service not available' ), 500 );
		}

		// Check if running single test or getting test list.
		$action = isset( $_POST['test_action'] ) ? sanitize_text_field( wp_unslash( $_POST['test_action'] ) ) : 'list';

		// Handle different actions.
		switch ( $action ) {
			case 'list':
				// Return list of all tests.
				wp_send_json_success(
					array(
						'tests' => $conformance_service->get_test_list(),
					)
				);
				break;

			case 'run':
				// Run single test.
				$test_id = isset( $_POST['test_id'] ) ? sanitize_text_field( wp_unslash( $_POST['test_id'] ) ) : '';

				if ( empty( $test_id ) ) {
					wp_send_json_error( array( 'message' => 'Test ID required' ), 400 );
				}

				try {
					$result = $conformance_service->run_single_test( $test_id );

					if ( ! $result['success'] ) {
						wp_send_json_error(
							array(
								'message' => $result['error'] ?? 'Test failed',
								'test_id' => $test_id,
							),
							500
						);
					}

					wp_send_json_success(
						array(
							'test_id' => $test_id,
							'result'  => $result['result'],
						)
					);
				} catch ( \Exception $e ) {
					wp_send_json_error(
						array(
							'message' => $e->getMessage(),
							'test_id' => $test_id,
						),
						500
					);
				}
				break;

			case 'cleanup':
				// Cleanup test artifacts.
				try {
					$deleted = $conformance_service->cleanup_test_artifacts();
					wp_send_json_success(
						array(
							'message' => sprintf( 'Cleaned up %d test products', $deleted ),
							'deleted' => $deleted,
						)
					);
				} catch ( \Exception $e ) {
					wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
				}
				break;

			default:
				wp_send_json_error( array( 'message' => 'Invalid action' ), 400 );
		}
	}

	/**
	 * AJAX handler for enabling robots.txt filter
	 *
	 * @return void
	 */
	public function ajax_enable_robots_filter(): void {
		// Verify nonce.
		check_ajax_referer( 'carticy_wizard_tests', 'nonce' );

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) ),
				403
			);
		}

		// Enable the robots.txt filter.
		update_option( 'carticy_ai_checkout_enable_openai_robots', 'yes' );

		wp_send_json_success(
			array(
				'message' => __( 'Robots.txt filter enabled successfully', 'carticy-ai-checkout-for-woocommerce' ),
			)
		);
	}

	/**
	 * AJAX handler for saving test results
	 *
	 * @return void
	 */
	public function ajax_save_test_results(): void {
		// Verify nonce.
		check_ajax_referer( 'carticy_wizard_tests', 'nonce' );

		// Verify permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized', 'carticy-ai-checkout-for-woocommerce' ) ),
				403
			);
		}

		// Get results from POST data.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string decoded and validated below.
		$results_json = isset( $_POST['results'] ) ? sanitize_text_field( wp_unslash( $_POST['results'] ) ) : '';
		$results      = json_decode( $results_json, true );

		if ( ! $results || ! is_array( $results ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid results data', 'carticy-ai-checkout-for-woocommerce' ) ), 400 );
		}

		// Validate required structure.
		if ( ! isset( $results['summary'] ) || ! is_array( $results['summary'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid results structure: missing summary', 'carticy-ai-checkout-for-woocommerce' ) ), 400 );
		}

		if ( ! isset( $results['tests'] ) || ! is_array( $results['tests'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid results structure: missing tests', 'carticy-ai-checkout-for-woocommerce' ) ), 400 );
		}

		// Save to transient (1 hour expiry).
		set_transient( 'carticy_ai_checkout_wizard_test_results', $results, HOUR_IN_SECONDS );

		wp_send_json_success(
			array(
				'message' => __( 'Test results saved successfully', 'carticy-ai-checkout-for-woocommerce' ),
			)
		);
	}
}
