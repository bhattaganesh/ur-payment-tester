<?php
/**
 * URPT Direct Bank Transfer Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages staging, quarantine, and manual approval/rejection of Direct Bank Transfer orders.
 */
class URPT_Driver_Bank {

	/**
	 * Stages a pending Direct Bank Transfer order with login quarantine.
	 *
	 * @param int   $plan_id Membership plan ID.
	 * @param int   $user_id Member user ID.
	 * @param float $amount Total order amount.
	 * @return array Staged order and subscription IDs.
	 */
	public function stage_pending_bank_order( $plan_id, $user_id, $amount = 50.00 ) {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$subs_table   = $wpdb->prefix . 'ur_membership_subscriptions';
		$meta_table   = $wpdb->prefix . 'ur_membership_ordermeta';

		$start_date   = current_time( 'mysql' );
		$next_billing = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );

		// Insert pending subscription.
		$wpdb->insert(
			$subs_table,
			array(
				'item_id'           => $plan_id,
				'user_id'           => $user_id,
				'start_date'        => $start_date,
				'expiry_date'       => $next_billing,
				'next_billing_date' => $next_billing,
				'billing_cycle'     => 'month',
				'billing_amount'    => $amount,
				'status'            => 'pending',
				'subscription_id'   => 'bank_sub_' . time(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
		$sub_id = $wpdb->insert_id;

		// Insert pending order.
		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $plan_id,
				'user_id'         => $user_id,
				'subscription_id' => $sub_id,
				'created_by'      => $user_id,
				'transaction_id'  => 'bacs_' . time(),
				'payment_method'  => 'bank',
				'total_amount'    => $amount,
				'status'          => 'pending',
				'order_type'      => 'subscription',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$order_id = $wpdb->insert_id;

		// Tag with simulation meta for clean teardown.
		$wpdb->insert(
			$meta_table,
			array(
				'order_id'   => $order_id,
				'meta_key'   => 'urpt_simulated',
				'meta_value' => '1',
			),
			array( '%d', '%s', '%s' )
		);

		// Lock user login gate until funds clear.
		update_user_meta( $user_id, 'ur_user_status', 0 );

		return array(
			'order_id'        => $order_id,
			'subscription_id' => $sub_id,
			'user_id'         => $user_id,
			'amount'          => $amount,
			'status'          => 'pending',
			'login_gate'      => 'locked',
		);
	}

	/**
	 * Simulates administrative confirmation and approval of a bank transfer.
	 *
	 * Advances order to completed, subscription to active, and unlocks member login gate.
	 *
	 * @param int $order_id Order row ID.
	 * @return array Approval result.
	 * @throws \InvalidArgumentException If order not found.
	 */
	public function approve_payment( $order_id ) {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$subs_table   = $wpdb->prefix . 'ur_membership_subscriptions';
		$events_table = $wpdb->prefix . 'ur_membership_subscription_events';

		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE ID = %d", absint( $order_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $order ) {
			throw new \InvalidArgumentException( __( 'Order not found for approval.', 'ur-payment-tester' ) );
		}

		$user_id = absint( $order['user_id'] );
		$sub_id  = absint( $order['subscription_id'] );

		// Mark order completed.
		$wpdb->update(
			$orders_table,
			array( 'status' => 'completed' ),
			array( 'ID' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Activate subscription.
		if ( $sub_id ) {
			$wpdb->update(
				$subs_table,
				array( 'status' => 'active' ),
				array( 'ID' => $sub_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// Unlock user login gate.
		update_user_meta( $user_id, 'ur_user_status', 1 );

		// Record audit event in events table.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) ) === $events_table ) {
			$wpdb->insert(
				$events_table,
				array(
					'subscription_id' => $sub_id,
					'user_id'         => $user_id,
					'event_type'      => 'bank_transfer_approved',
					'event_status'    => 'completed',
					'title'           => 'Bank Transfer Approved by Administrator',
					'reference_id'    => $order_id,
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		// Fire core activation hook to trigger welcome emails and PDF generation.
		do_action( 'urm_member_registered', $user_id, $order_id );

		return array(
			'order_id'        => $order_id,
			'subscription_id' => $sub_id,
			'user_id'         => $user_id,
			'order_status'    => 'completed',
			'sub_status'      => 'active',
			'login_gate'      => 'unlocked',
		);
	}

	/**
	 * Rejects a pending Direct Bank Transfer order.
	 *
	 * @param int $order_id Order row ID.
	 * @return array Rejection result.
	 */
	public function reject_payment( $order_id ) {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$subs_table   = $wpdb->prefix . 'ur_membership_subscriptions';

		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE ID = %d", absint( $order_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $order ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Order not found.', 'ur-payment-tester' ),
			);
		}

		$wpdb->update( $orders_table, array( 'status' => 'failed' ), array( 'ID' => $order_id ), array( '%s' ), array( '%d' ) );
		if ( ! empty( $order['subscription_id'] ) ) {
			$wpdb->update( $subs_table, array( 'status' => 'canceled' ), array( 'ID' => $order['subscription_id'] ), array( '%s' ), array( '%d' ) );
		}

		return array(
			'order_id'     => $order_id,
			'order_status' => 'failed',
			'sub_status'   => 'canceled',
		);
	}
}
