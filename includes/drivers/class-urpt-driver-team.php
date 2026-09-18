<?php
/**
 * URPT Team Membership Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Simulates team seat models (fixed, per-seat, tiered) and post allocation lifecycles.
 */
class URPT_Driver_Team {

	/**
	 * Computes total team order price according to the configured seat pricing model.
	 *
	 * @param string $model Pricing model ('fixed', 'per_seat', or 'tiered').
	 * @param float  $base_price Base or per-seat price.
	 * @param int    $seats Number of requested seats.
	 * @param array  $tiers Array of tier definitions: [['min' => 1, 'max' => 5, 'price' => 10], ...].
	 * @return array Calculated pricing breakdown.
	 */
	public function calculate_price( $model, $base_price, $seats, array $tiers = array() ) {
		$seats = max( 1, absint( $seats ) );
		$total = 0.0;

		switch ( $model ) {
			case 'fixed':
				// Fixed lump-sum price regardless of seat count.
				$total = (float) $base_price;
				break;

			case 'tiered':
				// Match seat range against configured tier bands.
				$matched_tier = null;
				foreach ( $tiers as $tier ) {
					if ( $seats >= $tier['min'] && ( empty( $tier['max'] ) || $seats <= $tier['max'] ) ) {
						$matched_tier = $tier;
						break;
					}
				}
				$total = $matched_tier ? (float) $matched_tier['price'] : ( (float) $base_price * $seats );
				break;

			case 'per_seat':
			default:
				// Linear seat multiplication.
				$total = (float) $base_price * $seats;
				break;
		}

		return array(
			'model'        => $model,
			'seats'        => $seats,
			'rate'         => (float) $base_price,
			'total_amount' => round( $total, 2 ),
		);
	}

	/**
	 * Simulates team order completion and asserts ur_membership_team post staging.
	 *
	 * @param int    $plan_id Membership post ID.
	 * @param int    $leader_id Team leader user ID.
	 * @param int    $seats Total seats purchased.
	 * @param string $model Pricing model.
	 * @param float  $rate Price per seat or base price.
	 * @return array Simulation result including team post ID and seat allocations.
	 */
	public function simulate_team_checkout( $plan_id, $leader_id, $seats = 5, $model = 'per_seat', $rate = 15.00 ) {
		global $wpdb;

		$pricing = $this->calculate_price( $model, $rate, $seats );
		$amount  = $pricing['total_amount'];

		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$subs_table   = $wpdb->prefix . 'ur_membership_subscriptions';
		$meta_table   = $wpdb->prefix . 'ur_membership_ordermeta';

		$start_date   = current_time( 'mysql' );
		$next_billing = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );

		// Insert subscription.
		$wpdb->insert(
			$subs_table,
			array(
				'item_id'           => $plan_id,
				'user_id'           => $leader_id,
				'start_date'        => $start_date,
				'expiry_date'       => $next_billing,
				'next_billing_date' => $next_billing,
				'billing_cycle'     => 'month',
				'billing_amount'    => $amount,
				'status'            => 'active',
				'subscription_id'   => 'team_sub_' . time(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
		$sub_id = $wpdb->insert_id;

		// Insert order.
		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $plan_id,
				'user_id'         => $leader_id,
				'subscription_id' => $sub_id,
				'created_by'      => $leader_id,
				'transaction_id'  => 'team_txn_' . time(),
				'payment_method'  => 'stripe',
				'total_amount'    => $amount,
				'status'          => 'completed',
				'order_type'      => 'subscription',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$order_id = $wpdb->insert_id;

		// Tag for purge.
		$wpdb->insert(
			$meta_table,
			array(
				'order_id'   => $order_id,
				'meta_key'   => 'urpt_simulated',
				'meta_value' => '1',
			),
			array( '%d', '%s', '%s' )
		);

		// Create ur_membership_team post.
		$team_id = wp_insert_post(
			array(
				'post_type'   => 'ur_membership_team',
				'post_title'  => 'Simulated Team #' . time(),
				'post_status' => 'publish',
			)
		);

		if ( $team_id && ! is_wp_error( $team_id ) ) {
			update_post_meta( $team_id, 'urm_team_seats', $seats );
			update_post_meta( $team_id, 'urm_used_seats', 1 );
			update_post_meta( $team_id, 'urm_order_id', $order_id );
			update_post_meta( $team_id, 'urm_subscription_id', $sub_id );
			update_post_meta( $team_id, 'urm_team_leader_id', $leader_id );
			update_post_meta( $team_id, 'urm_member_ids', array( $leader_id ) );
			update_post_meta( $team_id, 'urm_membership_id', $plan_id );

			// Associate with leader user meta.
			$existing_teams = (array) get_user_meta( $leader_id, 'urm_team_ids', true );
			$existing_teams[] = $team_id;
			update_user_meta( $leader_id, 'urm_team_ids', array_filter( array_unique( $existing_teams ) ) );
		}

		return array(
			'team_id'         => $team_id,
			'order_id'        => $order_id,
			'subscription_id' => $sub_id,
			'leader_id'       => $leader_id,
			'allocated_seats' => $seats,
			'used_seats'      => 1,
			'pricing'         => $pricing,
		);
	}
}
