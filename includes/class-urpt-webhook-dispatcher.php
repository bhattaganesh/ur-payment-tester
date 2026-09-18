<?php
/**
 * URPT Webhook Synthesizer & Internal Dispatcher
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Constructs authentic gateway webhook payloads and dispatches them to URM REST endpoints.
 */
class URPT_Webhook_Dispatcher {

	/**
	 * Dispatches a synthesized webhook payload to WordPress REST API.
	 *
	 * @param string $gateway Target gateway ('stripe' or 'paypal').
	 * @param string $event_name Gateway event identifier.
	 * @param array  $context Custom subscription or order context.
	 * @return array Dispatch result containing HTTP code, response body, and duration.
	 */
	public function dispatch( $gateway, $event_name, array $context = array() ) {
		if ( 'authorize' === $gateway ) {
			$start_time = microtime( true );
			$core       = URPT_Core::instance();
			$sub_id     = absint( $context['subscription_id'] ?? 1 );
			$order_id   = absint( $context['order_id'] ?? 1 );

			if ( false !== strpos( $event_name, 'failed' ) ) {
				$res = $core->driver_authorize->dispatch_failed_payment_webhook( $sub_id );
			} elseif ( false !== strpos( $event_name, 'cancel' ) ) {
				$res = $core->driver_authorize->dispatch_cancellation_webhook( $sub_id );
			} elseif ( false !== strpos( $event_name, 'refund' ) ) {
				$res = $core->driver_authorize->dispatch_refund_webhook( $order_id );
			} else {
				$res = $core->driver_authorize->dispatch_renewal_webhook( $sub_id );
			}

			$duration = round( ( microtime( true ) - $start_time ) * 1000, 2 );
			return array(
				'gateway'     => 'authorize',
				'event'       => $event_name,
				'status_code' => $res['status_code'] ?? 200,
				'response'    => $res,
				'duration_ms' => $duration,
				'payload'     => $res,
			);
		}

		if ( 'mollie' === $gateway ) {
			$start_time = microtime( true );
			$core       = URPT_Core::instance();
			$sub_id     = absint( $context['subscription_id'] ?? 1 );
			$order_id   = absint( $context['order_id'] ?? 1 );

			if ( false !== strpos( $event_name, 'failed' ) ) {
				$res = $core->driver_mollie->dispatch_failed_payment_webhook( $sub_id );
			} elseif ( false !== strpos( $event_name, 'cancel' ) ) {
				$res = $core->driver_mollie->dispatch_cancellation_webhook( $sub_id );
			} elseif ( false !== strpos( $event_name, 'refund' ) ) {
				$res = $core->driver_mollie->dispatch_refund_webhook( $order_id );
			} else {
				$res = $core->driver_mollie->dispatch_renewal_webhook( $sub_id );
			}

			$duration = round( ( microtime( true ) - $start_time ) * 1000, 2 );
			return array(
				'gateway'     => 'mollie',
				'event'       => $event_name,
				'status_code' => $res['status_code'] ?? 200,
				'response'    => $res,
				'duration_ms' => $duration,
				'payload'     => $res,
			);
		}

		$payload = $this->build_payload( $gateway, $event_name, $context );
		$body    = wp_json_encode( $payload );

		$route   = 'stripe' === $gateway ? '/user-registration/stripe-webhook' : '/user-registration/paypal-webhook';
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_body( $body );
		$request->set_header( 'Content-Type', 'application/json' );

		// Attach authentic signature headers.
		if ( 'stripe' === $gateway ) {
			if ( ! class_exists( 'URPT_Stripe_Mock_Client' ) && ( class_exists( 'Stripe\HttpClient\ClientInterface' ) || interface_exists( 'Stripe\HttpClient\ClientInterface' ) ) ) {
				require_once URPT_PLUGIN_DIR . 'includes/class-urpt-stripe-mock-client.php';
			}
			if ( class_exists( 'URPT_Stripe_Mock_Client' ) ) {
				URPT_Stripe_Mock_Client::$events[ $payload['id'] ] = $payload;
				if ( class_exists( 'Stripe\ApiRequestor' ) ) {
					\Stripe\ApiRequestor::setHttpClient( new URPT_Stripe_Mock_Client() );
				}
				if ( class_exists( 'Stripe\Stripe' ) ) {
					\Stripe\Stripe::setApiKey( 'sk_test_urpt_simulated_secret_key' );
				}
			}
			$timestamp = time();
			$secret    = URPT_HTTP_Interceptor::STRIPE_TEST_SECRET;
			$signed    = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
			$request->set_header( 'stripe-signature', "t={$timestamp},v1={$signed}" );
		} else {
			$request->set_header( 'paypal-transmission-id', wp_generate_uuid4() );
			$request->set_header( 'paypal-transmission-time', gmdate( 'Y-m-d\TH:i:s\Z' ) );
			$request->set_header( 'paypal-cert-url', 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-SIMULATED' );
			$request->set_header( 'paypal-auth-algo', 'SHA256withRSA' );
			$request->set_header( 'paypal-transmission-sig', base64_encode( 'URPT_SIMULATED_TRANSMISSION_SIGNATURE' ) );
		}

		$start_time = microtime( true );
		$response   = rest_do_request( $request );
		$duration   = round( ( microtime( true ) - $start_time ) * 1000, 2 );

		return array(
			'gateway'     => $gateway,
			'event'       => $event_name,
			'status_code' => $response->get_status(),
			'response'    => $response->get_data(),
			'duration_ms' => $duration,
			'payload'     => $payload,
		);
	}

	/**
	 * Builds an authentic JSON payload schema based on gateway and event.
	 *
	 * @param string $gateway Gateway ('stripe' or 'paypal').
	 * @param string $event Event identifier.
	 * @param array  $context Context data (subscription_id, customer_id, amount).
	 * @return array Structured JSON-ready payload.
	 */
	public function build_payload( $gateway, $event, array $context = array() ) {
		if ( 'stripe' === $gateway ) {
			return $this->build_stripe_payload( $event, $context );
		}
		return $this->build_paypal_payload( $event, $context );
	}

	/**
	 * Constructs Stripe webhook event payloads.
	 *
	 * @param string $event Stripe event type.
	 * @param array  $ctx Context overrides.
	 * @return array
	 */
	protected function build_stripe_payload( $event, array $ctx ) {
		$event_id = 'evt_urpt_' . time();
		$sub_id   = $ctx['subscription_id'] ?? 'sub_urpt_' . time();
		$cus_id   = $ctx['customer_id'] ?? 'cus_urpt_' . time();
		$pi_id    = $ctx['payment_intent'] ?? 'pi_urpt_' . time();
		$inv_id   = $ctx['invoice_id'] ?? 'in_urpt_' . time();
		$amount   = (int) ( ( $ctx['amount'] ?? 10.00 ) * 100 );

		$object_data = array();

		switch ( $event ) {
			case 'invoice.payment_succeeded':
				$object_data = array(
					'id'                 => $inv_id,
					'object'             => 'invoice',
					'customer'           => $cus_id,
					'subscription'       => $sub_id,
					'payment_intent'     => $pi_id,
					'amount_paid'        => $amount,
					'amount_due'         => $amount,
					'status'             => 'paid',
					'billing_reason'     => $ctx['billing_reason'] ?? 'subscription_cycle',
					'lines'              => array(
						'data' => array(
							array(
								'id'           => 'il_urpt_' . time(),
								'subscription' => $sub_id,
								'amount'       => $amount,
								'period'       => array(
									'start' => time(),
									'end'   => time() + 2592000,
								),
							),
						),
					),
				);
				break;

			case 'invoice.payment_failed':
				$object_data = array(
					'id'             => $inv_id,
					'object'         => 'invoice',
					'customer'       => $cus_id,
					'subscription'   => $sub_id,
					'payment_intent' => $pi_id,
					'amount_due'     => $amount,
					'status'         => 'open',
					'billing_reason' => 'subscription_cycle',
				);
				break;

			case 'customer.subscription.deleted':
				$object_data = array(
					'id'       => $sub_id,
					'object'   => 'subscription',
					'customer' => $cus_id,
					'status'   => 'canceled',
				);
				break;

			case 'charge.refunded':
				$object_data = array(
					'id'             => 'ch_urpt_' . time(),
					'object'         => 'charge',
					'customer'       => $cus_id,
					'payment_intent' => $pi_id,
					'amount'         => $amount,
					'amount_refunded'=> $amount,
					'refunded'       => true,
					'status'         => 'succeeded',
				);
				break;

			case 'customer.subscription.created':
				$object_data = array(
					'id'       => $sub_id,
					'object'   => 'subscription',
					'customer' => $cus_id,
					'status'   => 'active',
				);
				break;

			case 'payment_intent.payment_failed':
				$object_data = array(
					'id'             => $pi_id,
					'object'         => 'payment_intent',
					'customer'       => $cus_id,
					'status'         => 'requires_payment_method',
					'last_payment_error' => array(
						'message' => 'Your card was declined.',
						'code'    => 'card_declined',
					),
				);
				break;
		}

		return array(
			'id'          => $event_id,
			'object'      => 'event',
			'api_version' => '2023-10-16',
			'created'     => time(),
			'type'        => $event,
			'data'        => array(
				'object' => $object_data,
			),
			'livemode'    => false,
		);
	}

	/**
	 * Constructs PayPal REST webhook event payloads.
	 *
	 * @param string $event PayPal REST event type.
	 * @param array  $ctx Context overrides.
	 * @return array
	 */
	protected function build_paypal_payload( $event, array $ctx ) {
		$event_id = 'WH-' . wp_generate_password( 18, false );
		$sub_id   = $ctx['subscription_id'] ?? 'I-URPT-SUB-' . time();
		$order_id = $ctx['order_id'] ?? 'ORD-URPT-' . time();
		$amount   = number_format( (float) ( $ctx['amount'] ?? 10.00 ), 2, '.', '' );

		$resource = array();

		switch ( $event ) {
			case 'PAYMENT.CAPTURE.COMPLETED':
				$resource = array(
					'id'     => 'CAP-URPT-' . time(),
					'status' => 'COMPLETED',
					'amount' => array(
						'currency_code' => $ctx['currency'] ?? 'USD',
						'value'         => $amount,
					),
					'custom_id' => $ctx['custom_id'] ?? $order_id,
				);
				break;

			case 'BILLING.SUBSCRIPTION.ACTIVATED':
				$resource = array(
					'id'           => $sub_id,
					'status'       => 'ACTIVE',
					'plan_id'      => 'P-URPT-PLAN-1',
					'start_time'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'billing_info' => array(
						'next_billing_time' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+1 month' ) ),
					),
				);
				break;

			case 'PAYMENT.SALE.COMPLETED':
				$resource = array(
					'id'              => 'SALE-URPT-' . time(),
					'state'           => 'completed',
					'billing_agreement_id' => $sub_id,
					'amount'          => array(
						'currency' => $ctx['currency'] ?? 'USD',
						'total'    => $amount,
					),
				);
				break;

			case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
				$resource = array(
					'id'              => $sub_id,
					'status'          => 'ACTIVE',
					'plan_id'         => 'P-URPT-PLAN-1',
					'failure_reason'  => 'Card expired or insufficient funds.',
				);
				break;

			case 'BILLING.SUBSCRIPTION.CANCELLED':
				$resource = array(
					'id'     => $sub_id,
					'status' => 'CANCELLED',
				);
				break;
		}

		return array(
			'id'            => $event_id,
			'event_version' => '1.0',
			'create_time'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'resource_type' => false !== strpos( $event, 'SUBSCRIPTION' ) ? 'subscription' : 'capture',
			'event_type'    => $event,
			'summary'       => "URPT Simulated Event: {$event}",
			'resource'      => $resource,
		);
	}
}
