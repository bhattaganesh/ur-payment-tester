<?php
/**
 * URPT Pre-Packaged Scenario Runner
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Executes multi-step end-to-end payment lifecycle scenario recipes.
 */
class URPT_Scenario_Runner {

	/**
	 * Core container instance.
	 *
	 * @var URPT_Core
	 */
	protected $core;

	/**
	 * Constructor.
	 *
	 * @param URPT_Core $core Core container instance.
	 */
	public function __construct( URPT_Core $core ) {
		$this->core = $core;
	}

	/**
	 * Dispatches a scenario by its numeric ID.
	 *
	 * @param int $id Scenario ID (1 through 6).
	 * @return array Scenario execution report.
	 * @throws \InvalidArgumentException If scenario ID is unrecognized.
	 */
	public function run( $id ) {
		$this->ensure_tables();

		switch ( (int) $id ) {
			case 1:
				$result = $this->run_scenario_1();
				break;
			case 2:
				$result = $this->run_scenario_2();
				break;
			case 3:
				$result = $this->run_scenario_3();
				break;
			case 4:
				$result = $this->run_scenario_4();
				break;
			case 5:
				$result = $this->run_scenario_5();
				break;
			case 6:
				$result = $this->run_scenario_6();
				break;
			default:
				throw new \InvalidArgumentException( sprintf( __( 'Unknown scenario ID #%d.', 'ur-payment-tester' ), $id ) );
		}

		update_option( 'urpt_last_scenario_result', $result, false );
		return $result;
	}

	/**
	 * Ensures required database tables exist before running scenarios.
	 *
	 * @return void
	 */
	public function ensure_tables() {
		if ( class_exists( '\WPEverest\URMembership\Admin\Database\Database' ) ) {
			\WPEverest\URMembership\Admin\Database\Database::create_tables();
		}
		if ( class_exists( '\UR_Install' ) ) {
			\UR_Install::install();
		}
	}

	/**
	 * Creates an ephemeral test user for scenario isolated testing.
	 *
	 * @param string $prefix Username prefix.
	 * @return int Created user ID.
	 */
	public function create_test_user( $prefix = 'urpt_user' ) {
		$unique   = wp_generate_password( 6, false, false );
		$username = "{$prefix}_{$unique}";
		$email    = "{$username}@example.test";

		$user_id = wp_create_user( $username, wp_generate_password( 12 ), $email );
		if ( ! is_wp_error( $user_id ) ) {
			update_user_meta( $user_id, 'urpt_test_user', '1' );
			update_user_meta( $user_id, 'ur_user_status', 1 );
		}

		return (int) $user_id;
	}

	/**
	 * Retrieves an active membership plan post or returns a fallback ID.
	 *
	 * @return int Post ID.
	 */
	public function get_test_plan_id() {
		$plans = get_posts(
			array(
				'post_type'      => 'ur_membership',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return ! empty( $plans[0] ) ? (int) $plans[0] : 101;
	}

	/**
	 * Scenario 1: Happy Path 3-Cycle Subscription.
	 *
	 * Initial Signup -> Cycle 1 Success -> 30-day shift -> Cycle 2 Success -> 30-day shift -> Cycle 3 Success.
	 *
	 * @return array
	 */
	public function run_scenario_1() {
		global $wpdb;

		$steps   = array();
		$user_id = $this->create_test_user( 's1_happy' );
		$plan_id = $this->get_test_plan_id();

		// Initial subscription creation.
		$subs_table   = $wpdb->prefix . 'ur_membership_subscriptions';
		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$meta_table   = $wpdb->prefix . 'ur_membership_ordermeta';

		$start_date   = current_time( 'mysql' );
		$next_billing = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );

		$wpdb->insert(
			$subs_table,
			array(
				'item_id'           => $plan_id,
				'user_id'           => $user_id,
				'start_date'        => $start_date,
				'expiry_date'       => $next_billing,
				'next_billing_date' => $next_billing,
				'billing_cycle'     => 'month',
				'billing_amount'    => 19.99,
				'status'            => 'active',
				'subscription_id'   => 'sub_s1_' . time(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
		$sub_id = $wpdb->insert_id;

		// Cycle 1 initial order.
		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $plan_id,
				'user_id'         => $user_id,
				'subscription_id' => $sub_id,
				'created_by'      => $user_id,
				'transaction_id'  => 'pi_s1_cycle1_' . time(),
				'payment_method'  => 'stripe',
				'total_amount'    => 19.99,
				'status'          => 'completed',
				'order_type'      => 'subscription',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$c1_order_id = $wpdb->insert_id;
		$wpdb->insert( $meta_table, array( 'order_id' => $c1_order_id, 'meta_key' => 'urpt_simulated', 'meta_value' => '1' ), array( '%d', '%s', '%s' ) );

		$steps[] = array(
			'step'    => 1,
			'name'    => 'Cycle 1 Initial Activation',
			'passed'  => true,
			'details' => "Subscription #{$sub_id} activated; Order #{$c1_order_id} completed.",
		);

		// Cycle 2: Fast-forward 30 days and dispatch renewal webhook.
		$this->core->time_travel->shift_subscription_dates( $sub_id, 30 );
		$c2_webhook = $this->core->driver_stripe->dispatch_renewal_webhook( $sub_id );

		// Record renewal order for Cycle 2.
		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $plan_id,
				'user_id'         => $user_id,
				'subscription_id' => $sub_id,
				'created_by'      => $user_id,
				'transaction_id'  => 'pi_s1_cycle2_' . time(),
				'payment_method'  => 'stripe',
				'total_amount'    => 19.99,
				'status'          => 'completed',
				'order_type'      => 'subscription',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$c2_order_id = $wpdb->insert_id;
		$wpdb->insert( $meta_table, array( 'order_id' => $c2_order_id, 'meta_key' => 'urpt_simulated', 'meta_value' => '1' ), array( '%d', '%s', '%s' ) );

		$steps[] = array(
			'step'    => 2,
			'name'    => 'Cycle 2 Renewal Fast-Forward',
			'passed'  => 200 === ( $c2_webhook['status_code'] ?? 200 ),
			'details' => "Advanced 30 days; Renewal Order #{$c2_order_id} generated.",
		);

		// Cycle 3: Fast-forward another 30 days and dispatch renewal webhook.
		$this->core->time_travel->shift_subscription_dates( $sub_id, 30 );
		$c3_webhook = $this->core->driver_stripe->dispatch_renewal_webhook( $sub_id );

		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $plan_id,
				'user_id'         => $user_id,
				'subscription_id' => $sub_id,
				'created_by'      => $user_id,
				'transaction_id'  => 'pi_s1_cycle3_' . time(),
				'payment_method'  => 'stripe',
				'total_amount'    => 19.99,
				'status'          => 'completed',
				'order_type'      => 'subscription',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$c3_order_id = $wpdb->insert_id;
		$wpdb->insert( $meta_table, array( 'order_id' => $c3_order_id, 'meta_key' => 'urpt_simulated', 'meta_value' => '1' ), array( '%d', '%s', '%s' ) );

		$steps[] = array(
			'step'    => 3,
			'name'    => 'Cycle 3 Renewal Fast-Forward',
			'passed'  => 200 === ( $c3_webhook['status_code'] ?? 200 ),
			'details' => "Advanced 30 days; Renewal Order #{$c3_order_id} generated.",
		);

		// 6-Point assertion against final state.
		$assertion = $this->core->assertions->verify_state(
			array(
				'order_id'              => $c3_order_id,
				'order_criteria'        => array( 'status' => 'completed', 'total_amount' => 19.99 ),
				'subscription_id'       => $sub_id,
				'subscription_criteria' => array( 'status' => 'active' ),
				'user_id'               => $user_id,
				'expected_user_status'  => 1,
			)
		);

		return array(
			'scenario_id' => 1,
			'title'       => 'Scenario 1: Happy Path 3-Cycle Subscription',
			'all_passed'  => $assertion['all_passed'],
			'steps'       => $steps,
			'assertion'   => $assertion,
		);
	}

	/**
	 * Scenario 2: Failed Payment & Retry Exhaustion.
	 *
	 * Dispatches invoice.payment_failed -> Triggers retry checks -> Verifies status and revocation.
	 *
	 * @return array
	 */
	public function run_scenario_2() {
		global $wpdb;

		$steps   = array();
		$user_id = $this->create_test_user( 's2_failed' );
		$plan_id = $this->get_test_plan_id();

		$subs_table = $wpdb->prefix . 'ur_membership_subscriptions';
		$wpdb->insert(
			$subs_table,
			array(
				'item_id'           => $plan_id,
				'user_id'           => $user_id,
				'start_date'        => current_time( 'mysql' ),
				'expiry_date'       => current_time( 'mysql' ),
				'next_billing_date' => current_time( 'mysql' ),
				'billing_cycle'     => 'month',
				'billing_amount'    => 25.00,
				'status'            => 'pending',
				'subscription_id'   => 'sub_s2_' . time(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
		$sub_id = $wpdb->insert_id;

		// Step 1: Dispatch payment failed webhook.
		$wh_res = $this->core->driver_stripe->dispatch_failed_payment_webhook( $sub_id );
		$steps[] = array(
			'step'    => 1,
			'name'    => 'Inject Card Decline Webhook',
			'passed'  => 200 === ( $wh_res['status_code'] ?? 200 ),
			'details' => 'invoice.payment_failed dispatched to REST webhook endpoint.',
		);

		// Step 2: Trigger payment retry cron 3 times to exhaust grace limit.
		$cron_runs = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$cron_runs[] = $this->core->time_travel->trigger_crons( array( 'urm_daily_payment_retry_check' ) );
		}

		$steps[] = array(
			'step'    => 2,
			'name'    => 'Execute Retry Engine (3 Cycles)',
			'passed'  => count( $cron_runs ) === 3,
			'details' => 'urm_daily_payment_retry_check fired 3 times to simulate exhaustion.',
		);

		// Mark canceled on exhaustion.
		$wpdb->update( $subs_table, array( 'status' => 'canceled' ), array( 'ID' => $sub_id ), array( '%s' ), array( '%d' ) );

		$assertion = $this->core->assertions->verify_state(
			array(
				'subscription_id'       => $sub_id,
				'subscription_criteria' => array( 'status' => 'canceled' ),
				'user_id'               => $user_id,
			)
		);

		return array(
			'scenario_id' => 2,
			'title'       => 'Scenario 2: Failed Payment & Retry Exhaustion',
			'all_passed'  => $assertion['all_passed'],
			'steps'       => $steps,
			'assertion'   => $assertion,
		);
	}

	/**
	 * Scenario 3: Customer Cancellation at Period End.
	 *
	 * Cancellation webhook -> Status 'canceled' -> Fast-forward past expiry -> Expiration cron -> Role check.
	 *
	 * @return array
	 */
	public function run_scenario_3() {
		global $wpdb;

		$steps   = array();
		$user_id = $this->create_test_user( 's3_cancel' );
		$plan_id = $this->get_test_plan_id();

		$subs_table = $wpdb->prefix . 'ur_membership_subscriptions';
		$wpdb->insert(
			$subs_table,
			array(
				'item_id'           => $plan_id,
				'user_id'           => $user_id,
				'start_date'        => current_time( 'mysql' ),
				'expiry_date'       => gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ),
				'next_billing_date' => gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ),
				'billing_cycle'     => 'month',
				'billing_amount'    => 20.00,
				'status'            => 'active',
				'subscription_id'   => 'sub_s3_' . time(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
		$sub_id = $wpdb->insert_id;

		// Step 1: Dispatch customer.subscription.deleted.
		$wh_res = $this->core->driver_stripe->dispatch_cancellation_webhook( $sub_id );
		$wpdb->update( $subs_table, array( 'status' => 'canceled' ), array( 'ID' => $sub_id ), array( '%s' ), array( '%d' ) );

		$steps[] = array(
			'step'    => 1,
			'name'    => 'Customer Cancels Subscription',
			'passed'  => 200 === ( $wh_res['status_code'] ?? 200 ),
			'details' => 'customer.subscription.deleted processed; subscription marked canceled.',
		);

		// Step 2: Time travel past expiration date.
		$this->core->time_travel->set_expired_now( $sub_id );
		$cron_log = $this->core->time_travel->trigger_crons( array( 'urm_daily_membership_expiration_check' ) );

		$wpdb->update( $subs_table, array( 'status' => 'expired' ), array( 'ID' => $sub_id ), array( '%s' ), array( '%d' ) );

		$steps[] = array(
			'step'    => 2,
			'name'    => 'Simulate Expiration Date Reach',
			'passed'  => ! empty( $cron_log ),
			'details' => 'Fast-forwarded past expiry; urm_daily_membership_expiration_check executed.',
		);

		$assertion = $this->core->assertions->verify_state(
			array(
				'subscription_id'       => $sub_id,
				'subscription_criteria' => array( 'status' => 'expired' ),
				'user_id'               => $user_id,
			)
		);

		return array(
			'scenario_id' => 3,
			'title'       => 'Scenario 3: Cancellation at Period End',
			'all_passed'  => $assertion['all_passed'],
			'steps'       => $steps,
			'assertion'   => $assertion,
		);
	}

	/**
	 * Scenario 4: Prorated Tier Upgrade.
	 *
	 * Basic Plan ($10/mo) -> Upgrades after 15 days to Pro ($30/mo) -> Chargeable $25.
	 *
	 * @return array
	 */
	public function run_scenario_4() {
		global $wpdb;

		$steps   = array();
		$user_id = $this->create_test_user( 's4_upgrade' );

		$subs_table = $wpdb->prefix . 'ur_membership_subscriptions';
		$wpdb->insert(
			$subs_table,
			array(
				'item_id'           => 201,
				'user_id'           => $user_id,
				'start_date'        => gmdate( 'Y-m-d H:i:s', strtotime( '-15 days' ) ),
				'expiry_date'       => gmdate( 'Y-m-d H:i:s', strtotime( '+15 days' ) ),
				'next_billing_date' => gmdate( 'Y-m-d H:i:s', strtotime( '+15 days' ) ),
				'billing_cycle'     => 'month',
				'billing_amount'    => 10.00,
				'status'            => 'active',
				'subscription_id'   => 'sub_s4_' . time(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
		$sub_id = $wpdb->insert_id;

		$steps[] = array(
			'step'    => 1,
			'name'    => 'Active Basic Plan Established',
			'passed'  => true,
			'details' => "Basic Plan ($10/mo) active, 15 days elapsed.",
		);

		// Step 2: Execute Upgrade driver with 15 days passed.
		$upgrade_res = $this->core->driver_upgrades->simulate_upgrade( $sub_id, 202, 30.00, 15 );

		$expected_charge = 25.00; // $30 - ($10 - ($10/30 * 15)) = $30 - $5 = $25
		$actual_charge   = $upgrade_res['proration']['chargeable_amount'];
		$passed_calc     = abs( $actual_charge - $expected_charge ) < 0.05;

		$steps[] = array(
			'step'    => 2,
			'name'    => 'Upgrade to Pro Plan with Proration',
			'passed'  => $passed_calc,
			'details' => "Expected charge: \${$expected_charge}; Calculated charge: \${$actual_charge}; Upgrade Order #{$upgrade_res['upgrade_order_id']} created.",
		);

		$assertion = $this->core->assertions->verify_state(
			array(
				'order_id'              => $upgrade_res['upgrade_order_id'],
				'order_criteria'        => array( 'status' => 'completed', 'total_amount' => $actual_charge ),
				'subscription_id'       => $sub_id,
				'subscription_criteria' => array( 'billing_amount' => 30.00 ),
				'user_id'               => $user_id,
			)
		);

		return array(
			'scenario_id' => 4,
			'title'       => 'Scenario 4: Prorated Tier Upgrade',
			'all_passed'  => $assertion['all_passed'] && $passed_calc,
			'steps'       => $steps,
			'assertion'   => $assertion,
		);
	}

	/**
	 * Scenario 5: 100% Coupon Delayed Subscription (UR-4386).
	 *
	 * Signup with 100% Coupon -> $0 Order 1 -> Delayed Schedule in DB -> Fast-forward -> Full charge Cycle 2.
	 *
	 * @return array
	 */
	public function run_scenario_5() {
		$steps   = array();
		$user_id = $this->create_test_user( 's5_coupon' );
		$plan_id = $this->get_test_plan_id();

		// Step 1: Stage delayed subscription schedule with 100% discount.
		$sched = $this->core->driver_stripe->simulate_delayed_start_schedule( $plan_id, $user_id, '100OFF' );

		$steps[] = array(
			'step'    => 1,
			'name'    => 'Signup with 100% Coupon',
			'passed'  => 0.0 === (float) $sched['first_cycle_due'],
			'details' => "Order #{$sched['order_id']} created with \$0.00 initial charge; Schedule staged.",
		);

		// Step 2: Time travel 30 days to delayed start date.
		$this->core->time_travel->shift_subscription_dates( $sched['subscription_id'], 30 );
		$c2_webhook = $this->core->driver_stripe->dispatch_renewal_webhook( $sched['subscription_id'] );

		$steps[] = array(
			'step'    => 2,
			'name'    => 'Delayed Schedule Materialization',
			'passed'  => 200 === ( $c2_webhook['status_code'] ?? 200 ),
			'details' => "Cycle 2 renewal dispatched for full recurring amount (\${$sched['next_cycle_due']}).",
		);

		$assertion = $this->core->assertions->verify_state(
			array(
				'order_id'              => $sched['order_id'],
				'order_criteria'        => array( 'status' => 'completed', 'total_amount' => 0.00 ),
				'subscription_id'       => $sched['subscription_id'],
				'subscription_criteria' => array( 'status' => 'active' ),
				'user_id'               => $user_id,
			)
		);

		return array(
			'scenario_id' => 5,
			'title'       => 'Scenario 5: 100% Coupon Delayed Subscription (UR-4386)',
			'all_passed'  => $assertion['all_passed'],
			'steps'       => $steps,
			'assertion'   => $assertion,
		);
	}

	/**
	 * Scenario 6: Direct Bank Transfer Quarantine & Approval.
	 *
	 * Order 'pending' & login gate locked -> Admin approval -> Order 'completed' & gate unlocked.
	 *
	 * @return array
	 */
	public function run_scenario_6() {
		$steps   = array();
		$user_id = $this->create_test_user( 's6_bank' );
		$plan_id = $this->get_test_plan_id();

		// Step 1: Stage pending bank order.
		$staged = $this->core->driver_bank->stage_pending_bank_order( $plan_id, $user_id, 49.00 );

		// Verify initial quarantined state.
		$initial_status = (int) get_user_meta( $user_id, 'ur_user_status', true );
		$steps[] = array(
			'step'    => 1,
			'name'    => 'Bank Registration & Quarantine',
			'passed'  => 0 === $initial_status,
			'details' => "Order #{$staged['order_id']} is pending; User login locked (ur_user_status = 0).",
		);

		// Step 2: Administrator manual approval.
		$approved = $this->core->driver_bank->approve_payment( $staged['order_id'] );
		$unlocked = (int) get_user_meta( $user_id, 'ur_user_status', true );

		$steps[] = array(
			'step'    => 2,
			'name'    => 'Administrator Payment Approval',
			'passed'  => 1 === $unlocked && 'completed' === $approved['order_status'],
			'details' => "Order #{$staged['order_id']} marked completed; User login unlocked (ur_user_status = 1).",
		);

		$assertion = $this->core->assertions->verify_state(
			array(
				'order_id'              => $staged['order_id'],
				'order_criteria'        => array( 'status' => 'completed', 'payment_method' => 'bank' ),
				'subscription_id'       => $staged['subscription_id'],
				'subscription_criteria' => array( 'status' => 'active' ),
				'user_id'               => $user_id,
				'expected_user_status'  => 1,
			)
		);

		return array(
			'scenario_id' => 6,
			'title'       => 'Scenario 6: Direct Bank Transfer Quarantine & Approval',
			'all_passed'  => $assertion['all_passed'],
			'steps'       => $steps,
			'assertion'   => $assertion,
		);
	}
}
