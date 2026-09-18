<?php
/**
 * URPT WP-CLI Integration
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Headless CLI commands for payment simulation, time travel, and automated testing.
 */
class URPT_CLI {

	/**
	 * Runs a pre-packaged end-to-end scenario recipe.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Scenario ID (1 through 7).
	 *
	 * ## EXAMPLES
	 *
	 *     wp urpt scenario 1
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative flags.
	 * @return void
	 */
	public function scenario( $args, $assoc_args ) {
		$scenario_id = ! empty( $args[0] ) ? absint( $args[0] ) : 1;
		WP_CLI::log( "Executing Scenario #{$scenario_id}..." );

		try {
			$core   = URPT_Core::instance();
			$result = $core->scenario_runner->run( $scenario_id );

			foreach ( $result['steps'] as $step ) {
				$status_tag = $step['passed'] ? WP_CLI::colorize( '%G[PASS]%n' ) : WP_CLI::colorize( '%R[FAIL]%n' );
				WP_CLI::log( "{$status_tag} Step {$step['step']}: {$step['name']} - {$step['details']}" );
			}

			if ( $result['all_passed'] ) {
				WP_CLI::success( "{$result['title']} passed all assertions." );
			} else {
				WP_CLI::error( "{$result['title']} failed one or more assertions." );
			}
		} catch ( \Throwable $e ) {
			WP_CLI::error( sprintf( '%s in %s:%d', $e->getMessage() ?: get_class( $e ), $e->getFile(), $e->getLine() ) );
		}
	}

	/**
	 * Fast-forwards or rewinds dates on a subscription record.
	 *
	 * ## OPTIONS
	 *
	 * --subscription=<id>
	 * : Subscription row ID.
	 *
	 * [--days=<n>]
	 * : Number of days to advance or rewind (default: 30).
	 *
	 * ## EXAMPLES
	 *
	 *     wp urpt time-travel --subscription=5 --days=30
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative flags.
	 * @return void
	 */
	public function time_travel( $args, $assoc_args ) {
		$sub_id = absint( $assoc_args['subscription'] ?? 0 );
		$days   = intval( $assoc_args['days'] ?? 30 );

		if ( ! $sub_id ) {
			WP_CLI::error( 'Please specify --subscription=<id>.' );
		}

		try {
			$core = URPT_Core::instance();
			$res  = $core->time_travel->shift_subscription_dates( $sub_id, $days );
			WP_CLI::success( "Shifted subscription #{$sub_id} by {$days} days. New billing date: {$res['new_billing_date']}." );
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Dispatches an authentic synthesized gateway webhook internally.
	 *
	 * ## OPTIONS
	 *
	 * --gateway=<stripe|paypal>
	 * : Target gateway.
	 *
	 * --event=<name>
	 * : Event identifier (e.g. invoice.payment_succeeded, PAYMENT.CAPTURE.COMPLETED).
	 *
	 * ## EXAMPLES
	 *
	 *     wp urpt webhook --gateway=stripe --event=invoice.payment_succeeded
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative flags.
	 * @return void
	 */
	public function webhook( $args, $assoc_args ) {
		$gateway = sanitize_text_field( $assoc_args['gateway'] ?? 'stripe' );
		$event   = sanitize_text_field( $assoc_args['event'] ?? 'invoice.payment_succeeded' );

		$core = URPT_Core::instance();
		$res  = $core->webhook_dispatcher->dispatch( $gateway, $event );

		if ( 200 === ( $res['status_code'] ?? 0 ) ) {
			WP_CLI::success( "Webhook '{$event}' dispatched successfully (HTTP 200, {$res['duration_ms']}ms)." );
		} else {
			WP_CLI::warning( "Webhook '{$event}' returned HTTP {$res['status_code']}." );
		}
	}

	/**
	 * Purges all simulated test orders, subscriptions, events, and test users.
	 *
	 * ## EXAMPLES
	 *
	 *     wp urpt purge
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative flags.
	 * @return void
	 */
	public function purge( $args, $assoc_args ) {
		$core    = URPT_Core::instance();
		$summary = $core->purge_test_data();

		WP_CLI::success(
			sprintf(
				'Purge complete: %d orders, %d subscriptions, %d events, and %d test users removed.',
				$summary['orders'],
				$summary['subscriptions'],
				$summary['events'],
				$summary['users']
			)
		);
	}
}

WP_CLI::add_command( 'urpt', 'URPT_CLI' );
