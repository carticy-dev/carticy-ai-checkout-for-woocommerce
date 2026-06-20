<?php
/**
 * Application Wizard screen REST controller.
 *
 * Wraps the wizard services (ApplicationWizardService, ConformanceTestService,
 * ProductQualityChecker, PrerequisitesChecker, ApiKeyService) so the React
 * Wizard screen can load the full nine-step state, save and validate steps,
 * navigate, persist the wizard's option-backed settings, drive the 17-test
 * conformance runner, and regenerate the API credentials. It replaces the
 * legacy admin-post + AJAX handlers (wizard_save_step, wizard_navigate,
 * wizard_reset, wizard_toggle_test_mode, wizard_save_test_webhook,
 * wizard_save_security, run_conformance_tests, save_test_results,
 * enable_robots_filter) one-for-one.
 *
 * @package Carticy\AiCheckout\Rest
 */

declare(strict_types=1);

namespace Carticy\AiCheckout\Rest;

use Carticy\AiCheckout\Services\ApplicationWizardService;
use Carticy\AiCheckout\Services\ConformanceTestService;
use Carticy\AiCheckout\Services\ProductQualityChecker;
use Carticy\AiCheckout\Services\PrerequisitesChecker;
use Carticy\AiCheckout\Services\ApiKeyService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backs the React Application Wizard screen.
 */
final class WizardController extends AbstractAdminController {

	/**
	 * Wizard state + business logic service.
	 *
	 * @var ApplicationWizardService
	 */
	private ApplicationWizardService $wizard;

	/**
	 * ACP conformance test runner.
	 *
	 * @var ConformanceTestService
	 */
	private ConformanceTestService $conformance;

	/**
	 * Product quality scoring service (step 4).
	 *
	 * @var ProductQualityChecker
	 */
	private ProductQualityChecker $quality;

	/**
	 * System prerequisites checker (step 5).
	 *
	 * @var PrerequisitesChecker
	 */
	private PrerequisitesChecker $prerequisites;

	/**
	 * API key + webhook secret service (step 7).
	 *
	 * @var ApiKeyService
	 */
	private ApiKeyService $api_key;

	/**
	 * Per-step key map, mirroring ApplicationWizardService::save_step_data().
	 *
	 * @var array<int, string>
	 */
	private const STEP_KEYS = array(
		1 => 'business_info',
		2 => 'product_config',
		3 => 'policies',
		4 => 'quality_review',
		5 => 'prerequisites',
		6 => 'test_setup',
		7 => 'integration',
		8 => 'conformance',
		9 => 'submit',
	);

	/**
	 * Per-step titles, mirroring ApplicationWizard::get_step_title().
	 *
	 * @var array<int, string>
	 */
	private const STEP_TITLES = array(
		1 => 'Business Info',
		2 => 'Product Feed',
		3 => 'Policies',
		4 => 'Data Quality',
		5 => 'Prerequisites',
		6 => 'Test Setup',
		7 => 'Integration',
		8 => 'Run Tests',
		9 => 'Submit',
	);

	/**
	 * Constructor.
	 *
	 * @param ApplicationWizardService $wizard        Wizard state + business logic service.
	 * @param ConformanceTestService   $conformance   ACP conformance test runner.
	 * @param ProductQualityChecker    $quality       Product quality scoring service.
	 * @param PrerequisitesChecker     $prerequisites System prerequisites checker.
	 * @param ApiKeyService            $api_key       API key + webhook secret service.
	 */
	public function __construct(
		ApplicationWizardService $wizard,
		ConformanceTestService $conformance,
		ProductQualityChecker $quality,
		PrerequisitesChecker $prerequisites,
		ApiKeyService $api_key
	) {
		$this->wizard        = $wizard;
		$this->conformance   = $conformance;
		$this->quality       = $quality;
		$this->prerequisites = $prerequisites;
		$this->api_key       = $api_key;
	}

	/**
	 * Route definitions for this controller.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function routes(): array {
		return array(
			array(
				'methods'  => 'GET',
				'route'    => '/wizard',
				'callback' => array( $this, 'get_wizard' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/wizard/step',
				'callback' => array( $this, 'save_step' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/wizard/navigate',
				'callback' => array( $this, 'navigate' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/wizard/reset',
				'callback' => array( $this, 'reset' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/wizard/settings',
				'callback' => array( $this, 'save_settings' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/wizard/conformance',
				'callback' => array( $this, 'conformance' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/wizard/save-results',
				'callback' => array( $this, 'save_results' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/wizard/regenerate-api-key',
				'callback' => array( $this, 'regenerate_api_key' ),
			),
			array(
				'methods'  => 'POST',
				'route'    => '/wizard/regenerate-webhook-secret',
				'callback' => array( $this, 'regenerate_webhook_secret' ),
			),
		);
	}

	/**
	 * Return the full wizard payload the React screen renders from.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_wizard(): \WP_REST_Response {
		return $this->ok( $this->build_payload() );
	}

	/**
	 * Validate (where applicable) and persist a single step's data.
	 *
	 * Mirrors the legacy handle_save_step(): step 1 (business_info) and step 3
	 * (policies) are validated; the remaining steps are informational and saved
	 * as empty data so they register as completed.
	 *
	 * @param \WP_REST_Request $request The REST request (reads `step` and `step_data`).
	 * @return \WP_REST_Response
	 */
	public function save_step( \WP_REST_Request $request ): \WP_REST_Response {
		$body      = $this->body( $request );
		$step      = isset( $body['step'] ) ? (int) $body['step'] : 0;
		$step_data = isset( $body['step_data'] ) && is_array( $body['step_data'] ) ? $body['step_data'] : array();

		if ( ! isset( self::STEP_KEYS[ $step ] ) ) {
			return $this->ok(
				array(
					'ok'     => false,
					'errors' => array( 'step' => __( 'Invalid step.', 'carticy-ai-checkout-for-woocommerce' ) ),
				)
			);
		}

		if ( 1 === $step ) {
			$sanitized  = array(
				'business_name'   => sanitize_text_field( (string) ( $step_data['business_name'] ?? '' ) ),
				'business_entity' => sanitize_text_field( (string) ( $step_data['business_entity'] ?? '' ) ),
				'contact_name'    => sanitize_text_field( (string) ( $step_data['contact_name'] ?? '' ) ),
				'contact_email'   => sanitize_email( (string) ( $step_data['contact_email'] ?? '' ) ),
				'store_url'       => esc_url_raw( (string) ( $step_data['store_url'] ?? '' ) ),
			);
			$validation = $this->wizard->validate_business_info( $sanitized );

			if ( is_array( $validation ) ) {
				return $this->ok(
					array(
						'ok'     => false,
						'errors' => $validation,
					)
				);
			}

			$this->wizard->save_step_data( $step, $sanitized );

			return $this->ok( array( 'ok' => true ) );
		}

		if ( 3 === $step ) {
			$sanitized  = array(
				'terms_of_service' => esc_url_raw( (string) ( $step_data['terms_of_service'] ?? '' ) ),
				'privacy_policy'   => esc_url_raw( (string) ( $step_data['privacy_policy'] ?? '' ) ),
				'return_policy'    => esc_url_raw( (string) ( $step_data['return_policy'] ?? '' ) ),
			);
			$validation = $this->wizard->validate_policies( $sanitized );

			if ( is_array( $validation ) ) {
				return $this->ok(
					array(
						'ok'     => false,
						'errors' => $validation,
					)
				);
			}

			$this->wizard->save_step_data( $step, $sanitized );

			return $this->ok( array( 'ok' => true ) );
		}

		// Steps 2, 4-9 are informational / auto-generated / config-based.
		$this->wizard->save_step_data( $step, array() );

		return $this->ok( array( 'ok' => true ) );
	}

	/**
	 * Move the wizard one step forward or back, then return the fresh payload.
	 *
	 * Going backwards drops the current and future steps from completed_steps so
	 * the stepper reflects the reverted position (mirrors handle_navigation()).
	 *
	 * @param \WP_REST_Request $request The REST request (reads `direction`).
	 * @return \WP_REST_Response
	 */
	public function navigate( \WP_REST_Request $request ): \WP_REST_Response {
		$body      = $this->body( $request );
		$direction = isset( $body['direction'] ) ? sanitize_text_field( (string) $body['direction'] ) : '';
		$current   = $this->wizard->get_current_step();
		$total     = $this->wizard->get_total_steps();

		if ( 'next' === $direction ) {
			$new_step = min( $current + 1, $total );
		} elseif ( 'prev' === $direction ) {
			$new_step = max( $current - 1, 1 );

			$wizard_data                    = $this->wizard->get_wizard_data();
			$completed                      = $wizard_data['completed_steps'] ?? array();
			$completed                      = array_filter( $completed, static fn( $step ) => $step < $new_step );
			$wizard_data['completed_steps'] = array_values( $completed );
			$this->wizard->save_wizard_data( $wizard_data );
		} else {
			$new_step = $current;
		}

		$this->wizard->set_current_step( $new_step );

		return $this->ok( $this->build_payload() );
	}

	/**
	 * Clear all wizard state, then return the fresh (reset) payload.
	 *
	 * @return \WP_REST_Response
	 */
	public function reset(): \WP_REST_Response {
		$this->wizard->clear_wizard_data();

		return $this->ok( $this->build_payload() );
	}

	/**
	 * Persist the wizard's option-backed settings (test mode, test webhook URL,
	 * IP allowlist, OpenAI robots filter). Only options present in the request
	 * body are written, mirroring the legacy per-form handlers.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $this->body( $request );

		if ( array_key_exists( 'test_mode', $body ) ) {
			update_option( 'carticy_ai_checkout_test_mode', $this->yes_no( $body['test_mode'] ) );
		}

		if ( array_key_exists( 'test_webhook_url', $body ) ) {
			update_option( 'carticy_ai_checkout_test_webhook_url', esc_url_raw( (string) $body['test_webhook_url'] ) );
		}

		if ( array_key_exists( 'enable_ip_allowlist', $body ) ) {
			update_option( 'carticy_ai_checkout_enable_ip_allowlist', $this->yes_no( $body['enable_ip_allowlist'] ) );
		}

		if ( array_key_exists( 'robots_enabled', $body ) ) {
			update_option( 'carticy_ai_checkout_enable_openai_robots', $this->yes_no( $body['robots_enabled'] ) );
		}

		return $this->ok( array( 'ok' => true ) );
	}

	/**
	 * Drive the conformance test runner.
	 *
	 * Action 'list' returns { tests } (id => name map); action 'run' returns
	 * { test_id, result }; action 'cleanup' returns { message }.
	 *
	 * @param \WP_REST_Request $request The REST request (reads `action`, `test_id`).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function conformance( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body   = $this->body( $request );
		$action = isset( $body['action'] ) ? sanitize_text_field( (string) $body['action'] ) : 'list';

		switch ( $action ) {
			case 'list':
				return $this->ok( array( 'tests' => $this->conformance->get_test_list() ) );

			case 'run':
				$test_id = isset( $body['test_id'] ) ? sanitize_text_field( (string) $body['test_id'] ) : '';

				if ( '' === $test_id ) {
					return $this->fail(
						'missing_test_id',
						__( 'A test ID is required.', 'carticy-ai-checkout-for-woocommerce' ),
						400
					);
				}

				$result = $this->conformance->run_single_test( $test_id );

				if ( empty( $result['success'] ) ) {
					return $this->fail(
						'test_failed',
						(string) ( $result['error'] ?? __( 'Test could not be run.', 'carticy-ai-checkout-for-woocommerce' ) ),
						500,
						array( 'test_id' => $test_id )
					);
				}

				return $this->ok(
					array(
						'test_id' => $test_id,
						'result'  => $this->jsonify( $result['result'] ?? array() ),
					)
				);

			case 'cleanup':
				$deleted = $this->conformance->cleanup_test_artifacts();

				return $this->ok(
					array(
						'deleted' => $deleted,
						'message' => sprintf(
							/* translators: %d: number of temporary products removed. */
							_n( 'Cleaned up %d test product.', 'Cleaned up %d test products.', $deleted, 'carticy-ai-checkout-for-woocommerce' ),
							$deleted
						),
					)
				);

			default:
				return $this->fail(
					'invalid_action',
					__( 'Invalid conformance action.', 'carticy-ai-checkout-for-woocommerce' ),
					400
				);
		}
	}

	/**
	 * Persist the assembled conformance results (summary + tests) to the
	 * one-hour transient the legacy ajax_save_test_results handler used.
	 *
	 * @param \WP_REST_Request $request The REST request (reads `results`).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_results( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body    = $this->body( $request );
		$results = isset( $body['results'] ) && is_array( $body['results'] ) ? $body['results'] : array();

		if ( ! isset( $results['summary'] ) || ! is_array( $results['summary'] ) ) {
			return $this->fail(
				'invalid_results',
				__( 'Invalid results structure: missing summary.', 'carticy-ai-checkout-for-woocommerce' ),
				400
			);
		}

		if ( ! isset( $results['tests'] ) || ! is_array( $results['tests'] ) ) {
			return $this->fail(
				'invalid_results',
				__( 'Invalid results structure: missing tests.', 'carticy-ai-checkout-for-woocommerce' ),
				400
			);
		}

		set_transient( 'carticy_ai_checkout_wizard_test_results', $results, HOUR_IN_SECONDS );

		return $this->ok( array( 'ok' => true ) );
	}

	/**
	 * Regenerate the API bearer token and return the new value.
	 *
	 * @return \WP_REST_Response
	 */
	public function regenerate_api_key(): \WP_REST_Response {
		return $this->ok( array( 'api_key' => $this->api_key->regenerate_api_key() ) );
	}

	/**
	 * Regenerate the webhook HMAC secret and return the new value.
	 *
	 * @return \WP_REST_Response
	 */
	public function regenerate_webhook_secret(): \WP_REST_Response {
		return $this->ok( array( 'webhook_secret' => $this->api_key->regenerate_webhook_secret() ) );
	}

	/**
	 * Assemble the full wizard payload the React screen renders from.
	 *
	 * @return array<string, mixed>
	 */
	private function build_payload(): array {
		$wizard_data  = $this->wizard->get_wizard_data();
		$current_step = $this->wizard->get_current_step();
		$total_steps  = $this->wizard->get_total_steps();

		return array(
			'current_step'      => $current_step,
			'total_steps'       => $total_steps,
			'completion'        => $this->wizard->get_completion_percentage(),
			'steps'             => $this->step_rows(),
			'data'              => array(
				'business_info'  => (array) ( $wizard_data['business_info'] ?? array() ),
				'product_config' => (array) ( $wizard_data['product_config'] ?? array() ),
				'policies'       => (array) ( $wizard_data['policies'] ?? array() ),
				'test_setup'     => (array) ( $wizard_data['test_setup'] ?? array() ),
				'integration'    => (array) ( $wizard_data['integration'] ?? array() ),
			),
			'api_key'           => (string) ( $this->api_key->get_api_key() ?? '' ),
			'webhook_secret'    => (string) ( $this->api_key->get_webhook_secret() ?? '' ),
			'application_json'  => $this->jsonify( $this->wizard->generate_application_data() ),
			'prerequisites'     => $this->prerequisite_rows(),
			'quality'           => $this->quality_summary(),
			'product_feed_slug' => 'carticy-ai-checkout-product-feed',
			'test_mode'         => (bool) get_option( 'carticy_ai_checkout_test_mode', 'yes' ),
			'robots_enabled'    => 'yes' === get_option( 'carticy_ai_checkout_enable_openai_robots', 'no' ),
		);
	}

	/**
	 * Build the stepper rows (number, key, title, completed flag).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function step_rows(): array {
		$rows = array();
		foreach ( self::STEP_KEYS as $n => $key ) {
			$rows[] = array(
				'n'         => $n,
				'key'       => $key,
				'title'     => $this->step_title( $n ),
				'completed' => $this->wizard->is_step_completed( $n ),
			);
		}

		return $rows;
	}

	/**
	 * Translate a step title for the given step number.
	 *
	 * @param int $n Step number.
	 * @return string Localized step title.
	 */
	private function step_title( int $n ): string {
		$title = self::STEP_TITLES[ $n ] ?? '';

		// translator domain matches the rest of the plugin; titles live in a const.
		return '' !== $title ? __( $title, 'carticy-ai-checkout-for-woocommerce' ) : ''; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
	}

	/**
	 * Serialize the prerequisite checks into React-ready rows (step 5).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function prerequisite_rows(): array {
		$rows = array();
		foreach ( $this->prerequisites->check_all() as $key => $check ) {
			$passed = ! empty( $check['passed'] );
			$action = (string) ( $check['action'] ?? '' );

			$rows[] = array(
				'key'        => (string) $key,
				'label'      => ucwords( str_replace( '_', ' ', (string) $key ) ),
				'passed'     => $passed,
				'message'    => (string) ( $check['message'] ?? '' ),
				'action_url' => '' !== $action ? esc_url_raw( $action ) : '',
			);
		}

		return $rows;
	}

	/**
	 * Aggregate ChatGPT-enabled product quality into a screen-ready summary.
	 *
	 * Averages the per-product quality scores and collects the distinct issue
	 * messages across enabled products for step 4.
	 *
	 * @return array{score: int, issues: array<int, string>}
	 */
	private function quality_summary(): array {
		$products = wc_get_products(
			array(
				'limit'      => -1,
				'status'     => 'publish',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_carticy_ai_checkout_enabled',
						'value'   => 'yes',
						'compare' => '=',
					),
				),
			)
		);

		if ( empty( $products ) ) {
			return array(
				'score'  => 0,
				'issues' => array( __( 'No products are enabled for ChatGPT checkout yet.', 'carticy-ai-checkout-for-woocommerce' ) ),
			);
		}

		$total_score = 0;
		$issues      = array();

		foreach ( $products as $product ) {
			$result       = $this->quality->check_product_quality( $product );
			$total_score += (int) ( $result['score'] ?? 0 );

			foreach ( (array) ( $result['issues'] ?? array() ) as $issue ) {
				$issue = (string) $issue;
				if ( '' !== $issue && ! in_array( $issue, $issues, true ) ) {
					$issues[] = $issue;
				}
			}
		}

		return array(
			'score'  => (int) round( $total_score / count( $products ) ),
			'issues' => $issues,
		);
	}

	/**
	 * Normalize an incoming boolean-ish value to the stored 'yes'/'no' string.
	 *
	 * @param mixed $value Raw value from the request body.
	 * @return string 'yes' or 'no'.
	 */
	private function yes_no( mixed $value ): string {
		return in_array( $value, array( true, 'yes', '1', 1 ), true ) ? 'yes' : 'no';
	}

	/**
	 * Round-trip a value through JSON so the response is guaranteed to be
	 * plain, JSON-serializable scalars/arrays (conformance results and the
	 * application data can contain nested WP objects otherwise).
	 *
	 * @param mixed $value Arbitrary value.
	 * @return mixed JSON-safe value.
	 */
	private function jsonify( mixed $value ): mixed {
		$encoded = wp_json_encode( $value );

		if ( false === $encoded ) {
			return array();
		}

		return json_decode( $encoded, true );
	}
}
