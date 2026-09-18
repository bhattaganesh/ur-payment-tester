<?php
/**
 * URPT PayPal Simulation Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Simulates PayPal REST v2 Orders, v1 Subscriptions, buyer returns, and IPN listeners.
 */
class URPT_Driver_Paypal {

	/**
	 * Dispatches a simulated recurring renewal payment webhook.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_renewal_webhook( $subscription_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_subscriptions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$sub_code = ! empty( $row['subscription_id'] ) ? $row['subscription_id'] : 'I-URPT-SUB-' . $subscription_id;
		$amount   = ! empty( $row['billing_amount'] ) ? (float) $row['billing_amount'] : 10.00;

		$core = URPT_Core::instance();
		return $core->webhook_dispatcher->dispatch(
			'paypal',
			'PAYMENT.SALE.COMPLETED',
			array(
				'subscription_id' => $sub_code,
				'amount'          => $amount,
				'currency'        => 'USD',
			)
		);
	}

	/**
	 * Dispatches a simulated payment failure webhook.
	 *
	 * @param int $subscription_id Subscription row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_failed_payment_webhook( $subscription_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_subscriptions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$sub_code = ! empty( $row['subscription_id'] ) ? $row['subscription_id'] : 'I-URPT-SUB-' . $subscription_id;

		$core = URPT_Core::instance();
		return $core->webhook_dispatcher->dispatch(
			'paypal',
			'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
			array(
				'subscription_id' => $sub_code,
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

		$sub_code = ! empty( $row['subscription_id'] ) ? $row['subscription_id'] : 'I-URPT-SUB-' . $subscription_id;

		$core = URPT_Core::instance();
		return $core->webhook_dispatcher->dispatch(
			'paypal',
			'BILLING.SUBSCRIPTION.CANCELLED',
			array(
				'subscription_id' => $sub_code,
			)
		);
	}

	/**
	 * Dispatches a one-time order capture completed webhook.
	 *
	 * @param int $order_id Order row ID.
	 * @return array Dispatch response.
	 */
	public function dispatch_capture_webhook( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_orders';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", absint( $order_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$amount = ! empty( $row['total_amount'] ) ? (float) $row['total_amount'] : 10.00;

		$core = URPT_Core::instance();
		return $core->webhook_dispatcher->dispatch(
			'paypal',
			'PAYMENT.CAPTURE.COMPLETED',
			array(
				'order_id'  => 'ORD-URPT-' . $order_id,
				'amount'    => $amount,
				'currency'  => 'USD',
				'custom_id' => (string) $order_id,
			)
		);
	}

	/**
	 * Simulates the buyer return redirect from PayPal back to WordPress.
	 *
	 * @param int    $order_id Order row ID.
	 * @param string $token Simulated PayPal order token.
	 * @param string $payer_id Simulated PayPal payer ID.
	 * @return array
	 */
	public function simulate_buyer_return( $order_id, $token = '', $payer_id = 'URPT_PAYER_123' ) {
		$token = ! empty( $token ) ? $token : 'ORD-URPT-' . $order_id;
		$params = array(
			'order_id'       => $order_id,
			'payment_method' => 'paypal',
			'token'          => $token,
		);

		$encoded = base64_encode( wp_json_encode( $params ) );

		if ( class_exists( 'WPEverest\URMembership\Admin\Services\Paypal\NewPaypalService' ) ) {
			$service = new \WPEverest\URMembership\Admin\Services\Paypal\NewPaypalService();
			$service->handle_paypal_redirect_response( $params, $payer_id );
		}

		return array(
			'status'    => 'success',
			'order_id'  => $order_id,
			'token'     => $token,
			'payer_id'  => $payer_id,
			'query_arg' => $encoded,
		);
	}

	/**
	 * Simulates a legacy PayPal IPN POST request.
	 *
	 * @param array $ipn_args Custom IPN parameters.
	 * @return array
	 */
	public function simulate_ipn_post( array $ipn_args = array() ) {
		$defaults = array(
			'txn_type'       => 'subscr_payment',
			'payment_status' => 'Completed',
			'mc_gross'       => '10.00',
			'mc_currency'    => 'USD',
			'subscr_id'      => 'I-URPT-LEGACY-' . time(),
			'txn_id'         => 'TXN-URPT-' . time(),
		);

		$data = wp_parse_args( $ipn_args, $defaults );

		if ( class_exists( 'WPEverest\URMembership\Admin\Services\Paypal\PaypalService' ) ) {
			$legacy_service = new \WPEverest\URMembership\Admin\Services\Paypal\PaypalService();
			$legacy_service->handle_membership_paypal_ipn( $data );
		}

		return array(
			'status'   => 'dispatched',
			'ipn_data' => $data,
		);
	}
}
