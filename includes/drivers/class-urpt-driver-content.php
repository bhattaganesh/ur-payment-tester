<?php
/**
 * URPT Content Restriction & Access Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates post/page content restriction gates and download limits against subscriber status.
 */
class URPT_Driver_Content {

	/**
	 * Evaluates whether a user currently has access to a restricted post or capability.
	 *
	 * @param int $user_id User ID.
	 * @param int $plan_id Required membership plan ID.
	 * @return array Access evaluation result.
	 */
	public function evaluate_access( $user_id, $plan_id ) {
		global $wpdb;

		$subs_table = $wpdb->prefix . 'ur_membership_subscriptions';
		$active_sub = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$subs_table} WHERE user_id = %d AND item_id = %d AND status = 'active' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $user_id ),
				absint( $plan_id )
			),
			ARRAY_A
		);

		$has_access = ! empty( $active_sub );

		return array(
			'user_id'            => absint( $user_id ),
			'plan_id'            => absint( $plan_id ),
			'has_access'         => $has_access,
			'access_gate_status' => $has_access ? 'unlocked' : 'locked',
			'active_sub_id'      => $active_sub['ID'] ?? null,
		);
	}

	/**
	 * Simulates instant access lockout when a subscription expires or is canceled.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array Lockout simulation report.
	 */
	public function simulate_lockout( $subscription_id ) {
		global $wpdb;

		$subs_table = $wpdb->prefix . 'ur_membership_subscriptions';
		$sub        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$subs_table} WHERE ID = %d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $sub ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Subscription record not found.', 'ur-payment-tester' ),
			);
		}

		$user_id = absint( $sub['user_id'] );
		$plan_id = absint( $sub['item_id'] );

		// Check access prior to expiration.
		$before = $this->evaluate_access( $user_id, $plan_id );

		// Fast-forward to expired.
		$wpdb->update(
			$subs_table,
			array( 'status' => 'expired' ),
			array( 'ID' => $subscription_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Check access after expiration.
		$after = $this->evaluate_access( $user_id, $plan_id );

		return array(
			'subscription_id' => $subscription_id,
			'user_id'         => $user_id,
			'before_lockout'  => $before,
			'after_lockout'   => $after,
			'lockout_success' => $before['has_access'] && ! $after['has_access'],
		);
	}
}
