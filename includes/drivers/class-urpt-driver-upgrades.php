<?php
/**
 * URPT Membership Upgrades Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Simulates UpgradeMembershipService logic and verifies prorated daily discount calculations.
 */
class URPT_Driver_Upgrades {

	/**
	 * Calculates the prorated chargeable amount for an upgrade based on elapsed days.
	 *
	 * Mirrors UpgradeMembershipService formula: price_per_day * days_passed.
	 *
	 * @param float $current_amount Previous plan recurring price.
	 * @param float $new_amount New plan recurring price.
	 * @param int   $cycle_days Total days in billing interval (e.g. 30).
	 * @param int   $days_passed Elapsed days into current cycle.
	 * @return array Calculated pricing breakdown.
	 */
	public function calculate_prorated_delta( $current_amount, $new_amount, $cycle_days = 30, $days_passed = 15 ) {
		$days_passed   = min( $cycle_days, max( 0, $days_passed ) );
		$price_per_day = $cycle_days > 0 ? ( $current_amount / $cycle_days ) : 0;

		// Unused value credited toward new plan.
		$prorate_discount = round( $current_amount - ( $price_per_day * $days_passed ), 2 );
		$chargeable       = max( 0, round( $new_amount - $prorate_discount, 2 ) );

		return array(
			'current_amount'    => (float) $current_amount,
			'new_amount'        => (float) $new_amount,
			'cycle_days'        => $cycle_days,
			'days_passed'       => $days_passed,
			'price_per_day'     => round( $price_per_day, 4 ),
			'prorate_discount'  => $prorate_discount,
			'chargeable_amount' => $chargeable,
		);
	}

	/**
	 * Simulates an end-to-end plan upgrade for an active subscription.
	 *
	 * @param int   $subscription_id Subscription row ID.
	 * @param int   $new_plan_id New membership post ID.
	 * @param float $new_plan_amount New plan price.
	 * @param int   $days_passed Number of days into billing cycle.
	 * @return array Upgrade simulation results.
	 */
	public function simulate_upgrade( $subscription_id, $new_plan_id, $new_plan_amount, $days_passed = 15 ) {
		global $wpdb;

		$subs_table   = $wpdb->prefix . 'ur_membership_subscriptions';
		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$meta_table   = $wpdb->prefix . 'ur_membership_ordermeta';

		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$subs_table} WHERE ID = %d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $sub ) {
			throw new \InvalidArgumentException( __( 'Subscription not found.', 'ur-payment-tester' ) );
		}

		$current_amount = (float) $sub['billing_amount'];
		$delta          = $this->calculate_prorated_delta( $current_amount, $new_plan_amount, 30, $days_passed );

		// Update subscription to new plan and new recurring price.
		$wpdb->update(
			$subs_table,
			array(
				'item_id'        => $new_plan_id,
				'billing_amount' => $new_plan_amount,
			),
			array( 'ID' => $subscription_id ),
			array( '%d', '%f' ),
			array( '%d' )
		);

		// Record upgrade order for the prorated delta.
		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $new_plan_id,
				'user_id'         => $sub['user_id'],
				'subscription_id' => $subscription_id,
				'created_by'      => $sub['user_id'],
				'transaction_id'  => 'upgrade_' . time(),
				'payment_method'  => 'stripe',
				'total_amount'    => $delta['chargeable_amount'],
				'status'          => 'completed',
				'order_type'      => 'subscription',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$order_id = $wpdb->insert_id;

		// Tag order for safe purge.
		$wpdb->insert(
			$meta_table,
			array(
				'order_id'   => $order_id,
				'meta_key'   => 'urpt_simulated',
				'meta_value' => '1',
			),
			array( '%d', '%s', '%s' )
		);

		return array(
			'subscription_id' => $subscription_id,
			'upgrade_order_id'=> $order_id,
			'proration'       => $delta,
		);
	}
}
