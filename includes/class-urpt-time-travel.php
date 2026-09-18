<?php
/**
 * URPT Time-Travel & Accelerated Renewal Engine
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages database date manipulation and programmatic cron execution.
 */
class URPT_Time_Travel {

	/**
	 * Advances or rewinds a subscription's billing dates in the database.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @param int $days Number of days to advance (positive) or rewind (negative).
	 * @return array Updated dates and status.
	 * @throws \InvalidArgumentException If subscription ID is invalid.
	 */
	public function shift_subscription_dates( $subscription_id, $days ) {
		global $wpdb;

		$sub_id = absint( $subscription_id );
		if ( ! $sub_id ) {
			throw new \InvalidArgumentException( __( 'Invalid subscription ID provided for time-travel.', 'ur-payment-tester' ) );
		}

		$table = $wpdb->prefix . 'ur_membership_subscriptions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", $sub_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row ) {
			throw new \InvalidArgumentException( __( 'Subscription record not found in database.', 'ur-payment-tester' ) );
		}

		$current_billing = ! empty( $row['next_billing_date'] ) ? $row['next_billing_date'] : current_time( 'mysql' );
		$current_expiry  = ! empty( $row['expiry_date'] ) ? $row['expiry_date'] : current_time( 'mysql' );

		$new_billing = gmdate( 'Y-m-d H:i:s', strtotime( "{$days} days", strtotime( $current_billing ) ) );
		$new_expiry  = gmdate( 'Y-m-d H:i:s', strtotime( "{$days} days", strtotime( $current_expiry ) ) );

		$wpdb->update(
			$table,
			array(
				'next_billing_date' => $new_billing,
				'expiry_date'       => $new_expiry,
			),
			array( 'ID' => $sub_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'subscription_id'   => $sub_id,
			'days_shifted'      => $days,
			'prev_billing_date' => $current_billing,
			'new_billing_date'  => $new_billing,
			'new_expiry_date'   => $new_expiry,
		);
	}

	/**
	 * Sets next_billing_date to the past to make a subscription immediately due for renewal.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array
	 */
	public function set_renewal_due_now( $subscription_id ) {
		global $wpdb;

		$sub_id = absint( $subscription_id );
		$table  = $wpdb->prefix . 'ur_membership_subscriptions';

		$past_date = gmdate( 'Y-m-d H:i:s', time() - 60 );
		$wpdb->update(
			$table,
			array( 'next_billing_date' => $past_date ),
			array( 'ID' => $sub_id ),
			array( '%s' ),
			array( '%d' )
		);

		return array(
			'subscription_id'   => $sub_id,
			'next_billing_date' => $past_date,
			'status'            => 'due_now',
		);
	}

	/**
	 * Sets expiry_date to the past to simulate subscription expiration.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array
	 */
	public function set_expired_now( $subscription_id ) {
		global $wpdb;

		$sub_id = absint( $subscription_id );
		$table  = $wpdb->prefix . 'ur_membership_subscriptions';

		// Set past expiry beyond default grace window.
		$past_date = gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) );
		$wpdb->update(
			$table,
			array(
				'expiry_date'       => $past_date,
				'next_billing_date' => $past_date,
			),
			array( 'ID' => $sub_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'subscription_id' => $sub_id,
			'expiry_date'     => $past_date,
			'status'          => 'expired_in_past',
		);
	}

	/**
	 * Programmatically executes native URM cron actions without waiting for schedule.
	 *
	 * @param array $crons List of cron action hooks to execute.
	 * @return array Execution log.
	 */
	public function trigger_crons( array $crons = array() ) {
		if ( empty( $crons ) ) {
			$crons = array(
				'urm_daily_membership_renewal_check',
				'urm_daily_payment_retry_check',
				'urm_daily_membership_expiration_check',
				'urm_missed_payment_events_check',
				'urm_run_delayed_subscription',
			);
		}

		$log = array();

		// Zero out grace hours during testing so expiration fires immediately.
		add_filter(
			'urm_expiry_grace_hours',
			function () {
				return 0;
			}
		);

		foreach ( $crons as $cron_hook ) {
			$start_time = microtime( true );
			do_action( $cron_hook );
			$elapsed = round( ( microtime( true ) - $start_time ) * 1000, 2 );

			$log[] = array(
				'hook'          => $cron_hook,
				'status'        => 'executed',
				'duration_ms'   => $elapsed,
				'executed_time' => current_time( 'mysql' ),
			);
		}

		return $log;
	}

	/**
	 * Executes sequential renewal cycles for a subscription.
	 *
	 * Fast-forwards time and simulates renewal events in a rapid loop.
	 *
	 * @param int    $subscription_id Subscription row ID.
	 * @param int    $cycles Total renewal cycles to execute (default: 3).
	 * @param string $gateway Payment gateway ('stripe' or 'paypal').
	 * @return array Detailed log of each executed cycle.
	 */
	public function run_renewal_cycles( $subscription_id, $cycles = 3, $gateway = 'stripe' ) {
		global $wpdb;

		$core    = URPT_Core::instance();
		$sub_id  = absint( $subscription_id );
		$table   = $wpdb->prefix . 'ur_membership_subscriptions';
		$results = array();

		for ( $i = 1; $i <= $cycles; $i++ ) {
			// Step 1: Make renewal due now.
			$this->set_renewal_due_now( $sub_id );

			// Step 2: Dispatch gateway renewal webhook.
			if ( 'paypal' === $gateway ) {
				$webhook_res = $core->driver_paypal->dispatch_renewal_webhook( $sub_id );
			} else {
				$webhook_res = $core->driver_stripe->dispatch_renewal_webhook( $sub_id );
			}

			// Step 3: Trigger renewal crons to process lifecycle updates.
			$cron_log = $this->trigger_crons( array( 'urm_daily_membership_renewal_check', 'urm_missed_payment_events_check' ) );

			// Step 4: Fetch latest subscription state.
			$updated_sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", $sub_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$results[] = array(
				'cycle'             => $i,
				'webhook_result'    => $webhook_res,
				'cron_log'          => $cron_log,
				'next_billing_date' => $updated_sub['next_billing_date'] ?? '',
				'status'            => $updated_sub['status'] ?? '',
			);
		}

		return $results;
	}
}
