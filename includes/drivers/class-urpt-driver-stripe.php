<?php
/**
 * URPT Stripe Simulation Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Simulates Stripe Elements checkout, subscription schedules, and lifecycle webhooks.
 */
class URPT_Driver_Stripe {

	/**
	 * Dispatches a simulated renewal webhook for a subscription.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_renewal_webhook( $subscription_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_subscriptions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$sub_code = ! empty( $row['subscription_id'] ) ? $row['subscription_id'] : 'sub_urpt_' . $subscription_id;
		$amount   = ! empty( $row['billing_amount'] ) ? (float) $row['billing_amount'] : 10.00;

		$core = URPT_Core::instance();
		return $core->webhook_dispatcher->dispatch(
			'stripe',
			'invoice.payment_succeeded',
			array(
				'subscription_id' => $sub_code,
				'customer_id'     => 'cus_urpt_' . ( $row['user_id'] ?? '1' ),
				'amount'          => $amount,
				'billing_reason'  => 'subscription_cycle',
			)
		);
	}

	/**
	 * Dispatches a payment failure webhook for a subscription.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_failed_payment_webhook( $subscription_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_subscriptions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$sub_code = ! empty( $row['subscription_id'] ) ? $row['subscription_id'] : 'sub_urpt_' . $subscription_id;
		$amount   = ! empty( $row['billing_amount'] ) ? (float) $row['billing_amount'] : 10.00;

		$core = URPT_Core::instance();
		return $core->webhook_dispatcher->dispatch(
			'stripe',
			'invoice.payment_failed',
			array(
				'subscription_id' => $sub_code,
				'customer_id'     => 'cus_urpt_' . ( $row['user_id'] ?? '1' ),
				'amount'          => $amount,
			)
		);
	}

	/**
	 * Dispatches a subscription cancellation webhook.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_cancellation_webhook( $subscription_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_subscriptions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$sub_code = ! empty( $row['subscription_id'] ) ? $row['subscription_id'] : 'sub_urpt_' . $subscription_id;

		$core = URPT_Core::instance();
		return $core->webhook_dispatcher->dispatch(
			'stripe',
			'customer.subscription.deleted',
			array(
				'subscription_id' => $sub_code,
				'customer_id'     => 'cus_urpt_' . ( $row['user_id'] ?? '1' ),
			)
		);
	}

	/**
	 * Dispatches a charge refund webhook for an order.
	 *
	 * @param int $order_id Order row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_refund_webhook( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_orders';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", absint( $order_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$txn_id = ! empty( $row['transaction_id'] ) ? $row['transaction_id'] : 'pi_urpt_' . $order_id;
		$amount = ! empty( $row['total_amount'] ) ? (float) $row['total_amount'] : 10.00;

		$core = URPT_Core::instance();
		return $core->webhook_dispatcher->dispatch(
			'stripe',
			'charge.refunded',
			array(
				'payment_intent' => $txn_id,
				'amount'         => $amount,
				'customer_id'    => 'cus_urpt_' . ( $row['user_id'] ?? '1' ),
			)
		);
	}

	/**
	 * Simulates UR-4386: delayed-start subscription schedule for 100% coupon.
	 *
	 * Prevents zero-amount payment intent errors by verifying delayed schedule logic.
	 *
	 * @param int    $plan_id Membership plan post ID.
	 * @param int    $user_id User ID.
	 * @param string $coupon_code Applied 100% coupon code.
	 * @return array Result of delayed schedule staging.
	 */
	public function simulate_delayed_start_schedule( $plan_id, $user_id, $coupon_code = '100OFF' ) {
		global $wpdb;

		$sub_table   = $wpdb->prefix . 'ur_membership_subscriptions';
		$order_table = $wpdb->prefix . 'ur_membership_orders';
		$meta_table  = $wpdb->prefix . 'ur_membership_ordermeta';

		$start_date   = current_time( 'mysql' );
		$next_billing = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );

		// Insert subscription with delayed schedule identifier.
		$wpdb->insert(
			$sub_table,
			array(
				'item_id'           => $plan_id,
				'user_id'           => $user_id,
				'start_date'        => $start_date,
				'expiry_date'       => $next_billing,
				'next_billing_date' => $next_billing,
				'billing_cycle'     => 'month',
				'billing_amount'    => 29.00,
				'status'            => 'active',
				'coupon'            => $coupon_code,
				'subscription_id'   => 'sub_sched_urpt_' . time(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$sub_id = $wpdb->insert_id;

		// Insert $0 first order.
		$wpdb->insert(
			$order_table,
			array(
				'item_id'         => $plan_id,
				'user_id'         => $user_id,
				'subscription_id' => $sub_id,
				'created_by'      => $user_id,
				'transaction_id'  => 'txn_free_urpt_' . time(),
				'payment_method'  => 'stripe',
				'total_amount'    => 0.00,
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
			'subscription_id' => $sub_id,
			'order_id'        => $order_id,
			'user_id'         => $user_id,
			'first_cycle_due' => 0.00,
			'next_cycle_due'  => 29.00,
			'schedule_id'     => 'sub_sched_urpt_' . time(),
		);
	}
}
