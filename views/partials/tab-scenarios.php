<?php
/**
 * URPT Scenario Recipes Partial View
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

$scenarios = array(
	array(
		'id'          => 1,
		'title'       => __( 'Scenario 1: Happy Path 3-Cycle Subscription', 'ur-payment-tester' ),
		'badge'       => 'Stripe & PayPal',
		'description' => __( 'Simulates initial signup and fast-forwards through 3 sequential 30-day billing intervals. Asserts that 3 distinct orders are created, billing dates increment correctly, and membership stays active.', 'ur-payment-tester' ),
		'flow'        => 'Signup -> Cycle 1 ($19.99) -> +30 Days -> Cycle 2 ($19.99) -> +30 Days -> Cycle 3 ($19.99) -> 6-Point Verification',
	),
	array(
		'id'          => 2,
		'title'       => __( 'Scenario 2: Failed Payment & Retry Exhaustion', 'ur-payment-tester' ),
		'badge'       => 'Grace Period',
		'description' => __( 'Dispatches invoice.payment_failed to put an active subscription into retry state. Runs native payment retry crons 3 times to exhaust grace attempts and verifies final cancellation and access demotion.', 'ur-payment-tester' ),
		'flow'        => 'Active Sub -> Dispatch Failure -> Enter Retry Queue -> Run Cron x3 -> Max Retries Exceeded -> Status Cancelled',
	),
	array(
		'id'          => 3,
		'title'       => __( 'Scenario 3: Cancellation at Period End', 'ur-payment-tester' ),
		'badge'       => 'Lifecycle',
		'description' => __( 'Processes a customer cancellation event, verifies status moves to canceled while retaining access through the paid term, then fast-forwards past expiry and runs expiration checks.', 'ur-payment-tester' ),
		'flow'        => 'Active Sub -> Dispatch customer.subscription.deleted -> Status Canceled -> Time-Travel to Expiry -> Run Expiration Cron -> Status Expired',
	),
	array(
		'id'          => 4,
		'title'       => __( 'Scenario 4: Prorated Tier Upgrade', 'ur-payment-tester' ),
		'badge'       => 'Upgrades Addon',
		'description' => __( 'Tests UpgradeMembershipService by advancing 15 days into a $10/mo Basic Plan and upgrading to a $30/mo Pro Plan. Asserts prorated calculation: $30 - ($10 - ($10/30 * 15)) = $25 chargeable delta.', 'ur-payment-tester' ),
		'flow'        => 'Basic Plan ($10/mo) -> 15 Days Elapsed -> Upgrade to Pro ($30/mo) -> Calculate ($25 Charge) -> Upgrade Order Logged',
	),
	array(
		'id'          => 5,
		'title'       => __( 'Scenario 5: 100% Coupon Delayed Subscription (UR-4386)', 'ur-payment-tester' ),
		'badge'       => 'UR-4386 Hotfix',
		'description' => __( 'Validates that a 100% discount coupon generates a $0 first order without triggering Stripe zero-amount gateway exceptions. Advances 30 days and verifies the scheduled full recurring charge.', 'ur-payment-tester' ),
		'flow'        => 'Apply 100% Coupon -> $0 Order Staged -> Delayed Schedule Generated -> +30 Days -> Full Recurring Cycle Dispatched',
	),
	array(
		'id'          => 6,
		'title'       => __( 'Scenario 6: Direct Bank Transfer Quarantine & Approval', 'ur-payment-tester' ),
		'badge'       => 'Offline BACS',
		'description' => __( 'Simulates a Direct Bank Transfer signup. Confirms the order remains pending and user login is quarantined (ur_user_status = 0). Executes administrator manual approval and asserts login gate unlock.', 'ur-payment-tester' ),
		'flow'        => 'Bank Signup -> Order Pending -> User Login Locked -> Admin Clicks Approve -> Order Completed -> User Unlocked (Status: 1)',
	),
);
?>

<div class="urpt-scenarios-grid">
	<?php foreach ( $scenarios as $sc ) : ?>
		<div class="urpt-card urpt-scenario-card">
			<div class="urpt-scenario-header">
				<div>
					<h3><?php echo esc_html( $sc['title'] ); ?></h3>
					<span class="urpt-pill urpt-pill-scenario"><?php echo esc_html( $sc['badge'] ); ?></span>
				</div>
				<button type="button" class="button button-primary urpt-run-scenario-btn" data-scenario="<?php echo esc_attr( $sc['id'] ); ?>">
					<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run Recipe', 'ur-payment-tester' ); ?>
				</button>
			</div>
			<div class="urpt-card-body">
				<p><?php echo esc_html( $sc['description'] ); ?></p>
				<div class="urpt-recipe-flow">
					<strong><?php esc_html_e( 'Sequence:', 'ur-payment-tester' ); ?></strong>
					<code><?php echo esc_html( $sc['flow'] ); ?></code>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>
