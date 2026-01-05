<?php
/**
 * Analytics Service
 *
 * Provides order metrics and statistics for ChatGPT checkout analytics.
 *
 * @package Carticy\AiCheckout
 */

namespace Carticy\AiCheckout\Services;

/**
 * Analytics service for order metrics and statistics
 */
final class AnalyticsService {
	/**
	 * Get ChatGPT order count
	 *
	 * @param int $days Number of days to look back.
	 * @return int Number of ChatGPT orders.
	 */
	public function get_chatgpt_order_count( int $days = 30 ): int {
		$date_after = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$result = wc_get_orders(
			array(
				'date_created' => '>' . $date_after,
				'meta_key'     => '_chatgpt_checkout',
				'meta_value'   => 'yes',
				'meta_compare' => '=',
				'paginate'     => true,
			)
		);

		return (int) $result->total;
	}

	/**
	 * Get ChatGPT order revenue
	 *
	 * @param int $days Number of days to look back.
	 * @return float Total revenue from ChatGPT orders.
	 */
	public function get_chatgpt_revenue( int $days = 30 ): float {
		$date_after = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'date_created' => '>' . $date_after,
				'status'       => array( 'wc-completed', 'wc-processing' ),
				'meta_key'     => '_chatgpt_checkout',
				'meta_value'   => 'yes',
				'meta_compare' => '=',
			)
		);

		$total = 0.0;
		foreach ( $orders as $order ) {
			$total += (float) $order->get_total();
		}

		return $total;
	}

	/**
	 * Get regular (non-ChatGPT) order count
	 *
	 * @param int $days Number of days to look back.
	 * @return int Number of regular orders.
	 */
	public function get_regular_order_count( int $days = 30 ): int {
		$date_after = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Get all orders count.
		$all_result = wc_get_orders(
			array(
				'date_created' => '>' . $date_after,
				'paginate'     => true,
			)
		);

		// Get ChatGPT orders count.
		$chatgpt_count = $this->get_chatgpt_order_count( $days );

		return max( 0, (int) $all_result->total - $chatgpt_count );
	}

	/**
	 * Get regular (non-ChatGPT) order revenue
	 *
	 * @param int $days Number of days to look back.
	 * @return float Total revenue from regular orders.
	 */
	public function get_regular_revenue( int $days = 30 ): float {
		$date_after = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Get all orders revenue.
		$all_orders = wc_get_orders(
			array(
				'limit'        => -1,
				'date_created' => '>' . $date_after,
				'status'       => array( 'wc-completed', 'wc-processing' ),
			)
		);

		$all_total = 0.0;
		foreach ( $all_orders as $order ) {
			$all_total += (float) $order->get_total();
		}

		// Subtract ChatGPT revenue.
		$chatgpt_revenue = $this->get_chatgpt_revenue( $days );

		return max( 0.0, $all_total - $chatgpt_revenue );
	}

	/**
	 * Get conversion rate (ChatGPT orders vs regular orders)
	 *
	 * @param int $days Number of days to look back.
	 * @return float Conversion rate as percentage.
	 */
	public function get_conversion_rate( int $days = 30 ): float {
		$chatgpt_count = $this->get_chatgpt_order_count( $days );
		$total_count   = $chatgpt_count + $this->get_regular_order_count( $days );

		if ( 0 === $total_count ) {
			return 0.0;
		}

		return ( $chatgpt_count / $total_count ) * 100;
	}

	/**
	 * Get completed ChatGPT order count
	 *
	 * @param int $days Number of days to look back.
	 * @return int Number of completed ChatGPT orders.
	 */
	public function get_completed_chatgpt_count( int $days = 30 ): int {
		$date_after = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$result = wc_get_orders(
			array(
				'date_created' => '>' . $date_after,
				'status'       => array( 'wc-completed', 'wc-processing' ),
				'meta_key'     => '_chatgpt_checkout',
				'meta_value'   => 'yes',
				'meta_compare' => '=',
				'paginate'     => true,
			)
		);

		return (int) $result->total;
	}

	/**
	 * Get completed regular order count
	 *
	 * @param int $days Number of days to look back.
	 * @return int Number of completed regular orders.
	 */
	public function get_completed_regular_count( int $days = 30 ): int {
		$date_after = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Get all completed orders count.
		$all_result = wc_get_orders(
			array(
				'date_created' => '>' . $date_after,
				'status'       => array( 'wc-completed', 'wc-processing' ),
				'paginate'     => true,
			)
		);

		// Get completed ChatGPT orders count.
		$chatgpt_count = $this->get_completed_chatgpt_count( $days );

		return max( 0, (int) $all_result->total - $chatgpt_count );
	}

	/**
	 * Get average order value
	 *
	 * Uses completed orders only for accurate revenue-based AOV calculation.
	 *
	 * @param string $type Order type: 'chatgpt' or 'regular'.
	 * @param int    $days Number of days to look back.
	 * @return float Average order value.
	 */
	public function get_average_order_value( string $type, int $days = 30 ): float {
		if ( 'chatgpt' === $type ) {
			$revenue = $this->get_chatgpt_revenue( $days );
			$count   = $this->get_completed_chatgpt_count( $days );
		} else {
			$revenue = $this->get_regular_revenue( $days );
			$count   = $this->get_completed_regular_count( $days );
		}

		if ( 0 === $count ) {
			return 0.0;
		}

		return $revenue / $count;
	}

	/**
	 * Get recent ChatGPT orders
	 *
	 * @param int $limit Number of orders to retrieve.
	 * @return array Array of WC_Order objects.
	 */
	public function get_recent_chatgpt_orders( int $limit = 10 ): array {
		$orders = wc_get_orders(
			array(
				'limit'        => $limit,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'meta_key'     => '_chatgpt_checkout',
				'meta_value'   => 'yes',
				'meta_compare' => '=',
			)
		);

		return $orders;
	}

	/**
	 * Get ChatGPT order statistics by status
	 *
	 * @param int $days Number of days to look back.
	 * @return array Order counts grouped by status.
	 */
	public function get_order_stats_by_status( int $days = 30 ): array {
		$date_after = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$statuses = array( 'completed', 'processing', 'on-hold', 'cancelled', 'refunded', 'failed', 'pending' );
		$stats    = array();

		foreach ( $statuses as $status ) {
			$result = wc_get_orders(
				array(
					'date_created' => '>' . $date_after,
					'status'       => 'wc-' . $status,
					'meta_key'     => '_chatgpt_checkout',
					'meta_value'   => 'yes',
					'meta_compare' => '=',
					'paginate'     => true,
				)
			);

			$stats[ $status ] = (int) $result->total;
		}

		return $stats;
	}
}
