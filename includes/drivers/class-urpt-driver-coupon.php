<?php
/**
 * URPT Coupon & Discount Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates and simulates fixed, percentage, and 100% discount coupons across one-time and recurring orders.
 */
class URPT_Driver_Coupon {

	/**
	 * Computes discount amounts and final payable total for a given coupon configuration.
	 *
	 * Supports fixed, percentage, and 100% delayed-start coupons (UR-4386).
	 *
	 * @param float  $subtotal Original plan price or order subtotal.
	 * @param string $type Coupon type ('fixed', 'percent', or '100percent').
	 * @param float  $value Discount value (e.g. 15 for $15 off or 20 for 20% off).
	 * @param string $code Coupon code identifier.
	 * @return array Calculated discount breakdown.
	 */
	public function apply_coupon( $subtotal, $type, $value, $code = 'TESTCOUPON' ) {
		$subtotal = max( 0.0, (float) $subtotal );
		$discount = 0.0;

		switch ( $type ) {
			case 'percent':
				$percent  = min( 100.0, max( 0.0, (float) $value ) );
				$discount = round( $subtotal * ( $percent / 100 ), 2 );
				break;

			case '100percent':
				$discount = $subtotal;
				break;

			case 'fixed':
			default:
				$discount = min( $subtotal, max( 0.0, (float) $value ) );
				break;
		}

		$total = max( 0.0, round( $subtotal - $discount, 2 ) );

		return array(
			'code'             => strtoupper( sanitize_text_field( $code ) ),
			'type'             => $type,
			'value'            => (float) $value,
			'subtotal'         => $subtotal,
			'discount_amount'  => $discount,
			'final_total'      => $total,
			'is_delayed_sched' => 0.0 === $total,
		);
	}

	/**
	 * Simulates an order with an applied coupon and records metadata for assertion.
	 *
	 * @param int    $plan_id Membership plan post ID.
	 * @param int    $user_id User ID.
	 * @param float  $subtotal Order base subtotal.
	 * @param string $coupon_code Coupon code.
	 * @param string $type Coupon type ('fixed', 'percent', '100percent').
	 * @param float  $value Discount amount or percentage.
	 * @return array Staged order details.
	 */
	public function simulate_coupon_order( $plan_id, $user_id, $subtotal = 50.00, $coupon_code = 'SAVE20', $type = 'percent', $value = 20.0 ) {
		global $wpdb;

		$calc = $this->apply_coupon( $subtotal, $type, $value, $coupon_code );

		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$meta_table   = $wpdb->prefix . 'ur_membership_ordermeta';

		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $plan_id,
				'user_id'         => $user_id,
				'subscription_id' => 0,
				'created_by'      => $user_id,
				'transaction_id'  => 'coupon_' . time(),
				'payment_method'  => 'stripe',
				'total_amount'    => $calc['final_total'],
				'status'          => 'completed',
				'order_type'      => 'paid',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$order_id = $wpdb->insert_id;

		// Record financial breakdown metadata.
		$metadata = array(
			'urpt_simulated'  => '1',
			'coupon_code'     => $calc['code'],
			'coupon_type'     => $calc['type'],
			'coupon_discount' => (string) $calc['discount_amount'],
			'subtotal'        => (string) $calc['subtotal'],
		);

		foreach ( $metadata as $k => $v ) {
			$wpdb->insert(
				$meta_table,
				array(
					'order_id'   => $order_id,
					'meta_key'   => $k,
					'meta_value' => $v,
				),
				array( '%d', '%s', '%s' )
			);
		}

		return array(
			'order_id'         => $order_id,
			'coupon_breakdown' => $calc,
		);
	}
}
