<?php
/**
 * Analytics screen REST controller.
 *
 * Wraps AnalyticsService so the React Analytics screen can load (and re-load on
 * date-range change) the same numbers the server template used to render.
 *
 * @package Carticy\AiCheckout\Rest
 */

declare(strict_types=1);

namespace Carticy\AiCheckout\Rest;

use Carticy\AiCheckout\Services\AnalyticsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backs the React Analytics screen.
 */
final class AnalyticsController extends AbstractAdminController {

	/**
	 * Analytics data service.
	 *
	 * @var AnalyticsService
	 */
	private AnalyticsService $analytics;

	/**
	 * Constructor.
	 *
	 * @param AnalyticsService $analytics Analytics data service.
	 */
	public function __construct( AnalyticsService $analytics ) {
		$this->analytics = $analytics;
	}

	/**
	 * WooCommerce statuses mapped to Chip tones (presentation only); any other
	 * status renders as the neutral tone.
	 */
	private const CHIP_TONES = array(
		'completed'  => 'success',
		'paid'       => 'success',
		'pending'    => 'warning',
		'processing' => 'warning',
		'failed'     => 'critical',
		'cancelled'  => 'critical',
	);

	/**
	 * Route definitions for this controller.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function routes(): array {
		return array(
			array(
				'methods'  => 'GET',
				'route'    => '/analytics',
				'callback' => array( $this, 'get_analytics' ),
				'args'     => array(
					'days' => array(
						'sanitize_callback' => 'absint',
						'default'           => 30,
					),
				),
			),
		);
	}

	/**
	 * Return the analytics payload (KPIs, status distribution, recent orders)
	 * for the requested date range.
	 *
	 * @param \WP_REST_Request $request The REST request (reads the `days` param).
	 */
	public function get_analytics( \WP_REST_Request $request ): \WP_REST_Response {
		$days = (int) $request->get_param( 'days' );
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			$days = 30;
		}

		$chatgpt_orders  = $this->analytics->get_chatgpt_order_count( $days );
		$chatgpt_revenue = $this->analytics->get_chatgpt_revenue( $days );
		$regular_orders  = $this->analytics->get_regular_order_count( $days );
		$regular_revenue = $this->analytics->get_regular_revenue( $days );
		$total_orders    = $chatgpt_orders + $regular_orders;

		return $this->ok(
			array(
				'days'          => $days,
				'kpis'          => array(
					'chatgpt_orders'  => $chatgpt_orders,
					'chatgpt_revenue' => $this->money( $chatgpt_revenue ),
					'regular_orders'  => $regular_orders,
					'regular_revenue' => $this->money( $regular_revenue ),
					'conversion_rate' => round( $this->analytics->get_conversion_rate( $days ), 2 ),
					'total_orders'    => $total_orders,
					'chatgpt_aov'     => $this->money( $this->analytics->get_average_order_value( 'chatgpt', $days ) ),
					'regular_aov'     => $this->money( $this->analytics->get_average_order_value( 'regular', $days ) ),
				),
				'status_stats'  => $this->status_rows( $this->analytics->get_order_stats_by_status( $days ), $chatgpt_orders ),
				'recent_orders' => $this->recent_rows( $this->analytics->get_recent_chatgpt_orders( 10 ) ),
			)
		);
	}

	/**
	 * Build the status-distribution rows (skipping empty statuses).
	 *
	 * @param array<string, int> $stats          Status => count.
	 * @param int                $chatgpt_orders Denominator for the percentage.
	 * @return array<int, array<string, mixed>>
	 */
	private function status_rows( array $stats, int $chatgpt_orders ): array {
		$rows = array();
		foreach ( $stats as $status => $count ) {
			if ( $count <= 0 ) {
				continue;
			}
			$rows[] = array(
				'status' => (string) $status,
				'label'  => ucfirst( (string) $status ),
				'count'  => (int) $count,
				'pct'    => $chatgpt_orders > 0 ? round( ( $count / $chatgpt_orders ) * 100, 1 ) : 0.0,
				'tone'   => self::CHIP_TONES[ $status ] ?? 'neutral',
			);
		}

		return $rows;
	}

	/**
	 * Serialize recent ChatGPT orders into React-ready table rows.
	 *
	 * @param array<int, \WC_Order> $orders Recent ChatGPT orders.
	 * @return array<int, array<string, mixed>>
	 */
	private function recent_rows( array $orders ): array {
		$rows = array();
		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
				continue;
			}
			$date   = $order->get_date_created();
			$status = $order->get_status();
			$name   = $order->get_formatted_billing_full_name();
			$rows[] = array(
				'id'           => (int) $order->get_id(),
				'number'       => (string) $order->get_order_number(),
				'edit_url'     => (string) $order->get_edit_order_url(),
				'date'         => is_object( $date ) ? $date->date_i18n( wc_date_format() ) : '',
				'customer'     => '' !== $name ? $name : __( 'Guest', 'carticy-ai-checkout-for-woocommerce' ),
				'total'        => html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
				'status'       => $status,
				'status_label' => wc_get_order_status_name( $status ),
				'tone'         => self::CHIP_TONES[ $status ] ?? 'neutral',
			);
		}

		return $rows;
	}

	/**
	 * Format an amount as a plain price string for the React UI.
	 *
	 * @param float $amount The amount to format.
	 */
	private function money( float $amount ): string {
		// wc_price() returns HTML with the currency symbol as an entity (e.g.
		// &#36;); decode it so the plain string renders correctly in React.
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
}
