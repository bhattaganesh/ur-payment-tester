<?php
/**
 * URPT Stripe Mock HTTP Client
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

use Stripe\HttpClient\ClientInterface;

/**
 * Mock HTTP client implementing Stripe's ClientInterface for 0ms, zero-credential simulation.
 */
class URPT_Stripe_Mock_Client implements ClientInterface {

	/**
	 * Stored event payloads mapped by event ID for retrieval.
	 *
	 * @var array
	 */
	public static $events = array();

	/**
	 * Handles outgoing Stripe API requests in memory without network calls.
	 *
	 * @param string   $method HTTP method ('get', 'post', 'delete').
	 * @param string   $absUrl The full Stripe API URL.
	 * @param array    $headers Raw HTTP headers.
	 * @param array    $params Request payload parameters.
	 * @param bool     $hasFile Whether request contains file upload.
	 * @param string   $apiMode API mode ('v1' or 'v2').
	 * @param int|null $maxNetworkRetries Retry limit.
	 * @return array Tuple: [raw body string, HTTP status code, headers array].
	 */
	public function request( $method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null ) {
		$url_path  = wp_parse_url( $absUrl, PHP_URL_PATH );
		$id_suffix = time();

		// Default response array.
		$response_data = array(
			'id'       => 'urpt_mock_' . $id_suffix,
			'object'   => 'generic',
			'livemode' => false,
		);
		$status_code   = 200;

		// Mock events retrieval for webhook verification in StripeService.
		if ( false !== strpos( $url_path, '/v1/events' ) ) {
			$event_id = basename( $url_path );
			if ( isset( self::$events[ $event_id ] ) ) {
				$response_data = self::$events[ $event_id ];
			} else {
				$response_data = array(
					'id'          => $event_id,
					'object'      => 'event',
					'type'        => 'invoice.payment_succeeded',
					'data'        => array(
						'object' => array(
							'id'             => 'in_urpt_' . $id_suffix,
							'object'         => 'invoice',
							'customer'       => 'cus_urpt_' . $id_suffix,
							'subscription'   => 'sub_urpt_' . $id_suffix,
							'payment_intent' => 'pi_urpt_' . $id_suffix,
							'amount_paid'    => 1000,
							'amount_due'     => 1000,
							'status'         => 'paid',
						),
					),
					'livemode'    => false,
				);
			}
		} elseif ( false !== strpos( $url_path, '/v1/payment_methods' ) ) {
			$pm_id = basename( $url_path );
			$response_data = array(
				'id'        => ! empty( $pm_id ) && 'payment_methods' !== $pm_id ? $pm_id : 'pm_urpt_' . $id_suffix,
				'object'    => 'payment_method',
				'type'      => 'card',
				'livemode'  => false,
				'card'      => array(
					'brand'     => 'visa',
					'last4'     => '4242',
					'exp_month' => 12,
					'exp_year'  => 2030,
					'country'   => 'US',
				),
				'customer'  => 'cus_urpt_' . $id_suffix,
			);
		} elseif ( false !== strpos( $url_path, '/v1/customers' ) ) {
			$cus_id = basename( $url_path );
			$response_data = array(
				'id'        => ! empty( $cus_id ) && 'customers' !== $cus_id ? $cus_id : 'cus_urpt_' . $id_suffix,
				'object'    => 'customer',
				'email'     => $params['email'] ?? 'tester@example.com',
				'livemode'  => false,
			);
		} elseif ( false !== strpos( $url_path, '/v1/payment_intents' ) ) {
			$pi_id = basename( $url_path );
			$pi_id = ! empty( $pi_id ) && 'payment_intents' !== $pi_id ? $pi_id : 'pi_urpt_' . $id_suffix;
			$response_data = array(
				'id'            => $pi_id,
				'object'        => 'payment_intent',
				'amount'        => $params['amount'] ?? 1000,
				'currency'      => $params['currency'] ?? 'usd',
				'status'        => 'succeeded',
				'client_secret' => $pi_id . '_secret_' . wp_generate_password( 24, false ),
				'livemode'      => false,
			);
		} elseif ( false !== strpos( $url_path, '/v1/subscription_schedules' ) ) {
			// UR-4386: delayed-start subscription schedule for 100% coupon.
			$sub_id = 'sub_urpt_' . $id_suffix;
			$response_data = array(
				'id'           => 'sub_sched_urpt_' . $id_suffix,
				'object'       => 'subscription_schedule',
				'status'       => 'active',
				'customer'     => $params['customer'] ?? 'cus_urpt_' . $id_suffix,
				'subscription' => $sub_id,
				'livemode'     => false,
			);
		} elseif ( false !== strpos( $url_path, '/v1/subscriptions' ) ) {
			$sub_id = basename( $url_path );
			$sub_id = ! empty( $sub_id ) && 'subscriptions' !== $sub_id ? $sub_id : 'sub_urpt_' . $id_suffix;
			$pi_id  = 'pi_urpt_' . $id_suffix;
			$response_data = array(
				'id'                 => $sub_id,
				'object'             => 'subscription',
				'status'             => 'active',
				'customer'           => $params['customer'] ?? 'cus_urpt_' . $id_suffix,
				'current_period_end' => time() + 2592000,
				'latest_invoice'     => array(
					'id'             => 'in_urpt_' . $id_suffix,
					'status'         => 'paid',
					'payment_intent' => array(
						'id'            => $pi_id,
						'status'        => 'succeeded',
						'client_secret' => $pi_id . '_secret_' . wp_generate_password( 24, false ),
					),
				),
				'livemode'           => false,
			);
		} elseif ( false !== strpos( $url_path, '/v1/products' ) ) {
			$prod_id = basename( $url_path );
			$response_data = array(
				'id'       => ! empty( $prod_id ) && 'products' !== $prod_id ? $prod_id : 'prod_urpt_' . $id_suffix,
				'object'   => 'product',
				'name'     => $params['name'] ?? 'Membership Plan',
				'active'   => true,
				'livemode' => false,
			);
		} elseif ( false !== strpos( $url_path, '/v1/webhook_endpoints' ) ) {
			$response_data = array(
				'id'       => 'we_urpt_' . $id_suffix,
				'object'   => 'webhook_endpoint',
				'secret'   => URPT_HTTP_Interceptor::STRIPE_TEST_SECRET,
				'status'   => 'enabled',
				'livemode' => false,
			);
		} elseif ( false !== strpos( $url_path, '/v1/coupons' ) ) {
			$response_data = array(
				'id'          => 'coupon_urpt_' . $id_suffix,
				'object'      => 'coupon',
				'percent_off' => $params['percent_off'] ?? 100,
				'valid'       => true,
				'livemode'    => false,
			);
		}

		$raw_body = wp_json_encode( $response_data );
		return array( $raw_body, $status_code, array( 'Content-Type: application/json' ) );
	}
}
