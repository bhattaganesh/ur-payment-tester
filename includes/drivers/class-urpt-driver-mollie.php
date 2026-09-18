<?php
/**
 * URPT Mollie Simulation Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Simulates Mollie Payments API (iDEAL, SEPA, Credit Card), webhooks, renewals, and refunds.
 */
class URPT_Driver_Mollie {

	/**
	 * Stages a pending Mollie order and subscription.
	 *
	 * @param int   $plan_id Membership plan post ID.
	 * @param int   $user_id User ID.
	 * @param float $amount Total order amount.
	 * @return array Staged order and subscription IDs.
	 */
	public function stage_pending_order( $plan_id, $user_id, $amount = 25.00 ) {
		global $wpdb;

		$subs_table   = $wpdb->prefix . 'ur_membership_subscriptions';
		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$meta_table   = $wpdb->prefix . 'ur_membership_ordermeta';

		$start_date   = current_time( 'mysql' );
		$next_billing = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
		$mollie_tr    = 'tr_urpt_' . time();

		// Record pending subscription with simulated Mollie identifier.
		$wpdb->insert(
			$subs_table,
			array(
				'item_id'           => $plan_id,
				'user_id'           => $user_id,
				'start_date'        => $start_date,
				'expiry_date'       => $next_billing,
				'next_billing_date' => $next_billing,
				'billing_cycle'     => 'month',
				'billing_amount'    => (float) $amount,
				'status'            => 'pending',
				'subscription_id'   => 'sub_mollie_' . time(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
		$sub_id = $wpdb->insert_id;

		// Record initial pending order.
		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $plan_id,
				'user_id'         => $user_id,
				'subscription_id' => $sub_id,
				'created_by'      => $user_id,
				'transaction_id'  => $mollie_tr,
				'payment_method'  => 'mollie',
				'total_amount'    => (float) $amount,
				'status'          => 'pending',
				'order_type'      => 'subscription',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$order_id = $wpdb->insert_id;

		// Tag with simulation meta to ensure safe cleanup during purge.
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
			'subscription_id' => (int) $sub_id,
			'order_id'        => (int) $order_id,
			'transaction_id'  => $mollie_tr,
			'status'          => 'pending',
		);
	}

	/**
	 * Transitions a pending Mollie order and subscription to completed and active.
	 *
	 * @param int $order_id Order row ID.
	 * @return array Updated order and subscription details.
	 */
	public function simulate_payment_success( $order_id ) {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$subs_table   = $wpdb->prefix . 'ur_membership_subscriptions';

		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE ID = %d", absint( $order_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $order ) {
			return array(
				'status_code' => 404,
				'message'     => 'Order not found',
			);
		}

		$wpdb->update(
			$orders_table,
			array( 'status' => 'completed' ),
			array( 'ID' => absint( $order_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		if ( ! empty( $order['subscription_id'] ) ) {
			$wpdb->update(
				$subs_table,
				array( 'status' => 'active' ),
				array( 'ID' => absint( $order['subscription_id'] ) ),
				array( '%s' ),
				array( '%d' )
			);
		}

		update_user_meta( $order['user_id'], 'ur_user_status', 1 );

		return array(
			'order_id'        => (int) $order_id,
			'subscription_id' => (int) $order['subscription_id'],
			'status'          => 'completed',
			'user_status'     => 1,
		);
	}

	/**
	 * Dispatches a simulated recurring Mollie renewal cycle.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_renewal_webhook( $subscription_id ) {
		global $wpdb;

		$subs_table   = $wpdb->prefix . 'ur_membership_subscriptions';
		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$meta_table   = $wpdb->prefix . 'ur_membership_ordermeta';

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$subs_table} WHERE ID = %d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			$latest_id = (int) $wpdb->get_var( "SELECT ID FROM {$subs_table} ORDER BY ID DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $latest_id ) {
				$subscription_id = $latest_id;
				$row             = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$subs_table} WHERE ID = %d", $subscription_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				$staged          = $this->stage_pending_order( 101, 1 );
				$subscription_id = $staged['subscription_id'];
				$row             = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$subs_table} WHERE ID = %d", $subscription_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$amount = ! empty( $row['billing_amount'] ) ? (float) $row['billing_amount'] : 25.00;

		// Insert cycle renewal order.
		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $row['item_id'],
				'user_id'         => $row['user_id'],
				'subscription_id' => $subscription_id,
				'created_by'      => $row['user_id'],
				'transaction_id'  => 'tr_urpt_renew_' . time(),
				'payment_method'  => 'mollie',
				'total_amount'    => $amount,
				'status'          => 'completed',
				'order_type'      => 'subscription',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$renewal_order_id = $wpdb->insert_id;

		$wpdb->insert(
			$meta_table,
			array(
				'order_id'   => $renewal_order_id,
				'meta_key'   => 'urpt_simulated',
				'meta_value' => '1',
			),
			array( '%d', '%s', '%s' )
		);

		// Advance subscription expiry and billing dates by 30 days.
		$core = URPT_Core::instance();
		$core->time_travel->shift_subscription_dates( $subscription_id, 30 );

		return array(
			'gateway'          => 'mollie',
			'event'            => 'payment.paid',
			'status_code'      => 200,
			'renewal_order_id' => $renewal_order_id,
			'subscription_id'  => $subscription_id,
		);
	}

	/**
	 * Dispatches a simulated recurring Mollie payment failure notification.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_failed_payment_webhook( $subscription_id ) {
		global $wpdb;

		$subs_table      = $wpdb->prefix . 'ur_membership_subscriptions';
		$subscription_id = absint( $subscription_id );
		if ( ! $subscription_id ) {
			$subscription_id = (int) $wpdb->get_var( "SELECT ID FROM {$subs_table} ORDER BY ID DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( $subscription_id ) {
			$wpdb->update(
				$subs_table,
				array( 'status' => 'pending' ),
				array( 'ID' => $subscription_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return array(
			'gateway'         => 'mollie',
			'event'           => 'payment.failed',
			'status_code'     => 200,
			'subscription_id' => $subscription_id,
		);
	}

	/**
	 * Dispatches a simulated Mollie subscription cancellation.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_cancellation_webhook( $subscription_id ) {
		global $wpdb;

		$subs_table      = $wpdb->prefix . 'ur_membership_subscriptions';
		$subscription_id = absint( $subscription_id );
		if ( ! $subscription_id ) {
			$subscription_id = (int) $wpdb->get_var( "SELECT ID FROM {$subs_table} ORDER BY ID DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( $subscription_id ) {
			$wpdb->update(
				$subs_table,
				array( 'status' => 'canceled' ),
				array( 'ID' => $subscription_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return array(
			'gateway'         => 'mollie',
			'event'           => 'subscription.canceled',
			'status_code'     => 200,
			'subscription_id' => $subscription_id,
		);
	}

	/**
	 * Dispatches a simulated refund or chargeback on a Mollie order.
	 *
	 * @param int $order_id Order row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_refund_webhook( $order_id ) {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$order_id     = absint( $order_id );
		if ( ! $order_id ) {
			$order_id = (int) $wpdb->get_var( "SELECT ID FROM {$orders_table} ORDER BY ID DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( $order_id ) {
			$wpdb->update(
				$orders_table,
				array( 'status' => 'refunded' ),
				array( 'ID' => $order_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return array(
			'gateway'     => 'mollie',
			'event'       => 'refund.created',
			'status_code' => 200,
			'order_id'    => $order_id,
		);
	}
}
