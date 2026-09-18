<?php
/**
 * URPT HTTP Request Interceptor & Gateway Mocking Engine
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Intercepts external payment gateway HTTP requests to enable Zero-Credentials Simulation Mode.
 */
class URPT_HTTP_Interceptor {

	/**
	 * Core container instance.
	 *
	 * @var URPT_Core
	 */
	protected $core;

	/**
	 * Default test secret for Stripe HMAC verification.
	 */
	const STRIPE_TEST_SECRET = 'whsec_urpt_simulation_secret_key_12345';

	/**
	 * Constructor.
	 *
	 * @param URPT_Core $core Core container.
	 */
	public function __construct( URPT_Core $core ) {
		$this->core = $core;

		add_filter( 'pre_http_request', array( $this, 'intercept_outgoing_requests' ), 10, 3 );
		$this->register_dynamic_option_fallbacks();
		$this->setup_stripe_mock_client();
	}

	/**
	 * Determines whether Zero-Credentials Simulation Mode is active.
	 *
	 * @return bool True if simulated mode, false for hybrid mode.
	 */
	public function is_simulation_mode() {
		return 'simulated' === get_option( 'urpt_operating_mode', 'simulated' );
	}

	/**
	 * Registers pre_option filters so forms don't reject checkout due to missing API keys.
	 *
	 * @return void
	 */
	private function register_dynamic_option_fallbacks() {
		if ( ! $this->is_simulation_mode() ) {
			return;
		}

		// Stripe credential fallbacks.
		add_filter(
			'pre_option_user_registration_stripe_test_mode',
			function ( $val ) {
				return ! empty( $val ) ? $val : 1;
			}
		);

		add_filter(
			'pre_option_user_registration_stripe_enabled',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'yes';
			}
		);

		add_filter(
			'pre_option_user_registration_stripe_test_publishable_key',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'pk_test_urpt_simulated_publishable_key';
			}
		);

		add_filter(
			'pre_option_user_registration_stripe_test_secret_key',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'sk_test_urpt_simulated_secret_key';
			}
		);

		add_filter(
			'pre_option_user_registration_stripe_live_publishable_key',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'pk_test_urpt_simulated_publishable_key';
			}
		);

		add_filter(
			'pre_option_user_registration_stripe_live_secret_key',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'sk_test_urpt_simulated_secret_key';
			}
		);

		add_filter(
			'pre_option_user_registration_stripe_webhook_secret',
			function ( $val ) {
				return ! empty( $val ) ? $val : self::STRIPE_TEST_SECRET;
			}
		);

		add_filter(
			'pre_option_user_registration_stripe_webhook_secret_test',
			function ( $val ) {
				return ! empty( $val ) ? $val : self::STRIPE_TEST_SECRET;
			}
		);

		add_filter(
			'user_registration_stripe_webhook_secret',
			function ( $val ) {
				return ! empty( $val ) ? $val : self::STRIPE_TEST_SECRET;
			}
		);

		// PayPal credential fallbacks.
		add_filter(
			'pre_option_user_registration_global_paypal_mode',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'test';
			}
		);

		add_filter(
			'pre_option_user_registration_global_paypal_test_client_id',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'URPT_SIMULATED_PAYPAL_CLIENT_ID';
			}
		);

		add_filter(
			'pre_option_user_registration_global_paypal_test_client_secret',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'URPT_SIMULATED_PAYPAL_CLIENT_SECRET';
			}
		);

		add_filter(
			'pre_option_user_registration_global_paypal_test_webhook_id',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'WH-URPT-SIMULATED-PAYPAL-ID';
			}
		);

		add_filter(
			'pre_option_user_registration_global_paypal_live_client_id',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'URPT_SIMULATED_PAYPAL_CLIENT_ID';
			}
		);

		add_filter(
			'pre_option_user_registration_global_paypal_live_client_secret',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'URPT_SIMULATED_PAYPAL_CLIENT_SECRET';
			}
		);

		add_filter(
			'pre_option_user_registration_global_paypal_live_webhook_id',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'WH-URPT-SIMULATED-PAYPAL-ID';
			}
		);

		add_filter(
			'pre_option_user_registration_global_paypal_sandbox_client_id',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'URPT_SIMULATED_PAYPAL_CLIENT_ID';
			}
		);

		add_filter(
			'pre_option_user_registration_global_paypal_sandbox_client_secret',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'URPT_SIMULATED_PAYPAL_CLIENT_SECRET';
			}
		);

		add_filter(
			'pre_option_user_registration_global_paypal_sandbox_webhook_id',
			function ( $val ) {
				return ! empty( $val ) ? $val : 'WH-URPT-SIMULATED-PAYPAL-ID';
			}
		);

		// Fallback for PayPal webhook processing when no matching database record exists.
		add_filter(
			'user_registration_paypal_webhook_event_fallback',
			function ( $handled, $event_data ) {
				return true;
			},
			10,
			2
		);
	}

	/**
	 * Intercepts outgoing WordPress HTTP requests to Stripe, PayPal, and Authorize.Net.
	 *
	 * @param false|array|WP_Error $preempt Whether to preempt an HTTP request's return value.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url The request URL.
	 * @return false|array Preempted response array or false to allow normal request.
	 */
	public function intercept_outgoing_requests( $preempt, $parsed_args, $url ) {
		// Always intercept webhook verification regardless of mode to ensure test webhooks pass.
		if ( false !== strpos( $url, '/v1/notifications/verify-webhook-signature' ) ) {
			return $this->mock_response(
				200,
				array(
					'verification_status' => 'SUCCESS',
				)
			);
		}

		if ( ! $this->is_simulation_mode() ) {
			return $preempt;
		}

		// Intercept PayPal OAuth token requests.
		if ( false !== strpos( $url, '/v1/oauth2/token' ) ) {
			return $this->mock_response(
				200,
				array(
					'scope'        => 'https://api.paypal.com/v1/payments/.*',
					'access_token' => 'mock_access_token_urpt_' . wp_generate_password( 32, false ),
					'token_type'   => 'Bearer',
					'app_id'       => 'APP-URPT-SIMULATION',
					'expires_in'   => 36000,
					'nonce'        => wp_generate_uuid4(),
				)
			);
		}

		// Intercept PayPal Catalog Products creation.
		if ( false !== strpos( $url, '/v1/catalogs/products' ) ) {
			return $this->mock_response(
				201,
				array(
					'id'          => 'PROD-URPT-' . time(),
					'name'        => 'Simulated Membership Product',
					'type'        => 'SERVICE',
					'category'    => 'SOFTWARE',
					'create_time' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				)
			);
		}

		// Intercept PayPal Billing Plans creation.
		if ( false !== strpos( $url, '/v1/billing/plans' ) ) {
			return $this->mock_response(
				201,
				array(
					'id'          => 'P-URPT-PLAN-' . time(),
					'product_id'  => 'PROD-URPT-1',
					'name'        => 'Simulated Membership Plan',
					'status'      => 'ACTIVE',
					'create_time' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				)
			);
		}

		// Intercept PayPal Subscriptions creation.
		if ( false !== strpos( $url, '/v1/billing/subscriptions' ) && 'POST' === ( $parsed_args['method'] ?? 'GET' ) ) {
			$sub_id = 'I-URPT-SUB-' . time();
			return $this->mock_response(
				201,
				array(
					'id'          => $sub_id,
					'status'      => 'APPROVAL_PENDING',
					'create_time' => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'links'       => array(
						array(
							'href'   => admin_url( 'admin.php?page=ur-payment-tester&tab=bench&simulated_paypal=1&subscription_id=' . $sub_id ),
							'rel'    => 'approve',
							'method' => 'GET',
						),
					),
				)
			);
		}

		// Intercept PayPal Subscriptions retrieval / actions.
		if ( preg_match( '#/v1/billing/subscriptions/([^/]+)#', $url, $matches ) ) {
			$sub_id = $matches[1];
			return $this->mock_response(
				200,
				array(
					'id'           => $sub_id,
					'status'       => 'ACTIVE',
					'billing_info' => array(
						'next_billing_time' => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+1 month' ) ),
					),
				)
			);
		}

		// Intercept PayPal v2 Checkout Orders creation.
		if ( false !== strpos( $url, '/v2/checkout/orders' ) && 'POST' === ( $parsed_args['method'] ?? 'GET' ) && ! preg_match( '#/capture$#', $url ) ) {
			$order_id = 'ORD-URPT-' . time();
			return $this->mock_response(
				201,
				array(
					'id'     => $order_id,
					'status' => 'CREATED',
					'intent' => 'CAPTURE',
					'links'  => array(
						array(
							'href'   => admin_url( 'admin.php?page=ur-payment-tester&tab=bench&simulated_paypal=1&token=' . $order_id ),
							'rel'    => 'approve',
							'method' => 'GET',
						),
					),
				)
			);
		}

		// Intercept PayPal v2 Orders capture.
		if ( preg_match( '#/v2/checkout/orders/([^/]+)/capture#', $url, $matches ) ) {
			$order_id   = $matches[1];
			$capture_id = 'CAP-URPT-' . time();
			return $this->mock_response(
				201,
				array(
					'id'             => $order_id,
					'status'         => 'COMPLETED',
					'purchase_units' => array(
						array(
							'payments' => array(
								'captures' => array(
									array(
										'id'     => $capture_id,
										'status' => 'COMPLETED',
										'amount' => array(
											'currency_code' => 'USD',
											'value'         => '10.00',
										),
									),
								),
							),
						),
					),
				)
			);
		}

		// Intercept Authorize.Net API requests.
		if ( false !== strpos( $url, 'apitest.authorize.net' ) || false !== strpos( $url, 'api.authorize.net' ) ) {
			$raw_body = $parsed_args['body'] ?? '';
			$is_arb   = false !== strpos( (string) $raw_body, 'ARBCreateSubscriptionRequest' );
			$is_cancel = false !== strpos( (string) $raw_body, 'ARBCancelSubscriptionRequest' );

			if ( $is_arb ) {
				return $this->mock_response(
					200,
					array(
						'messages'       => array(
							'resultCode' => 'Ok',
							'message'    => array(
								array(
									'code' => 'I00001',
									'text' => 'Successful.',
								),
							),
						),
						'subscriptionId' => 'URPT-ARB-' . time(),
					)
				);
			}

			if ( $is_cancel ) {
				return $this->mock_response(
					200,
					array(
						'messages' => array(
							'resultCode' => 'Ok',
							'message'    => array(
								array(
									'code' => 'I00001',
									'text' => 'Successful.',
								),
							),
						),
					)
				);
			}

			return $this->mock_response(
				200,
				array(
					'messages'            => array(
						'resultCode' => 'Ok',
						'message'    => array(
							array(
								'code' => 'I00001',
								'text' => 'Successful.',
							),
						),
					),
					'transactionResponse' => array(
						'responseCode'  => '1',
						'transId'       => 'URPT-AUTHNET-' . time(),
						'authCode'      => '123456',
						'accountNumber' => 'XXXX1111',
						'accountType'   => 'Visa',
						'messages'      => array(
							array(
								'code'        => '1',
								'description' => 'This transaction has been approved.',
							),
						),
					),
				)
			);
		}

		// Intercept Mollie API requests.
		if ( false !== strpos( $url, 'api.mollie.com' ) ) {
			$url_path = wp_parse_url( $url, PHP_URL_PATH );
			$id_seed  = time();

			// Payment creation.
			if ( false !== strpos( $url_path, '/v2/payments' ) && 'POST' === ( $parsed_args['method'] ?? 'GET' ) ) {
				$body_req = json_decode( $parsed_args['body'] ?? '{}', true );
				$amount   = $body_req['amount'] ?? array( 'currency' => 'EUR', 'value' => '10.00' );
				$redirect = $body_req['redirectUrl'] ?? home_url();

				return $this->mock_response(
					201,
					array(
						'resource'    => 'payment',
						'id'          => 'tr_urpt_' . $id_seed,
						'mode'        => 'test',
						'status'      => 'open',
						'isCancelable'=> true,
						'amount'      => $amount,
						'description' => $body_req['description'] ?? 'Membership payment',
						'method'      => $body_req['method'] ?? 'ideal',
						'_links'      => array(
							'checkout' => array(
								'href' => add_query_arg( array( 'urpt_mollie_paid' => 1, 'tr' => 'tr_urpt_' . $id_seed ), $redirect ),
								'type' => 'text/html',
							),
							'self'     => array(
								'href' => "https://api.mollie.com/v2/payments/tr_urpt_{$id_seed}",
								'type' => 'application/hal+json',
							),
						),
					)
				);
			}

			// Payment retrieval (verification).
			if ( false !== strpos( $url_path, '/v2/payments/' ) && 'GET' === ( $parsed_args['method'] ?? 'GET' ) ) {
				$payment_id = basename( $url_path );
				return $this->mock_response(
					200,
					array(
						'resource'     => 'payment',
						'id'           => $payment_id,
						'mode'         => 'test',
						'status'       => 'paid',
						'isCancelable' => false,
						'paidAt'       => gmdate( 'c' ),
						'amount'       => array(
							'currency' => 'EUR',
							'value'    => '10.00',
						),
						'details'      => array(
							'consumerName' => 'Simulated Test Customer',
						),
					)
				);
			}

			// Customer creation.
			if ( false !== strpos( $url_path, '/v2/customers' ) && 'POST' === ( $parsed_args['method'] ?? 'GET' ) ) {
				$body_req = json_decode( $parsed_args['body'] ?? '{}', true );
				return $this->mock_response(
					201,
					array(
						'resource' => 'customer',
						'id'       => 'cst_urpt_' . $id_seed,
						'mode'     => 'test',
						'name'     => $body_req['name'] ?? 'Test Customer',
						'email'    => $body_req['email'] ?? 'customer@example.test',
					)
				);
			}

			// Mandate creation.
			if ( false !== strpos( $url_path, '/mandates' ) && 'POST' === ( $parsed_args['method'] ?? 'GET' ) ) {
				return $this->mock_response(
					201,
					array(
						'resource' => 'mandate',
						'id'       => 'mdt_urpt_' . $id_seed,
						'mode'     => 'test',
						'status'   => 'valid',
						'method'   => 'directdebit',
						'details'  => array(
							'consumerName' => 'Test Customer',
							'consumerAccount' => 'NL00BANK0123456789',
						),
					)
				);
			}

			// Subscription creation.
			if ( false !== strpos( $url_path, '/subscriptions' ) && 'POST' === ( $parsed_args['method'] ?? 'GET' ) ) {
				$body_req = json_decode( $parsed_args['body'] ?? '{}', true );
				return $this->mock_response(
					201,
					array(
						'resource'        => 'subscription',
						'id'              => 'sub_urpt_' . $id_seed,
						'mode'            => 'test',
						'status'          => 'active',
						'amount'          => $body_req['amount'] ?? array( 'currency' => 'EUR', 'value' => '10.00' ),
						'times'           => $body_req['times'] ?? null,
						'interval'        => $body_req['interval'] ?? '1 month',
						'description'     => $body_req['description'] ?? 'Membership recurring',
						'nextPaymentDate' => gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
					)
				);
			}

			// Subscriptions cancellation.
			if ( false !== strpos( $url_path, '/subscriptions/' ) && 'DELETE' === ( $parsed_args['method'] ?? 'GET' ) ) {
				return $this->mock_response(
					200,
					array(
						'resource'   => 'subscription',
						'id'         => basename( $url_path ),
						'status'     => 'canceled',
						'canceledAt' => gmdate( 'c' ),
					)
				);
			}

			// Supported payment methods list.
			if ( false !== strpos( $url_path, '/v2/methods' ) ) {
				return $this->mock_response(
					200,
					array(
						'count'    => 5,
						'_embedded'=> array(
							'methods' => array(
								array( 'id' => 'ideal', 'description' => 'iDEAL' ),
								array( 'id' => 'creditcard', 'description' => 'Credit card' ),
								array( 'id' => 'bancontact', 'description' => 'Bancontact' ),
								array( 'id' => 'directdebit', 'description' => 'SEPA Direct Debit' ),
								array( 'id' => 'paypal', 'description' => 'PayPal' ),
							),
						),
					)
				);
			}
		}

		return $preempt;
	}

	/**
	 * Formats a standard WordPress HTTP response array.
	 *
	 * @param int   $code HTTP status code.
	 * @param array $body Response payload.
	 * @return array
	 */
	private function mock_response( $code, array $body ) {
		return array(
			'response' => array(
				'code'    => $code,
				'message' => 200 === $code || 201 === $code ? 'OK' : 'Error',
			),
			'headers'  => array(
				'content-type' => 'application/json; charset=utf-8',
			),
			'body'     => wp_json_encode( $body ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Configures in-memory mock client for Stripe PHP SDK when in simulation mode.
	 *
	 * Injects an HTTP client implementing \Stripe\HttpClient\ClientInterface so Stripe calls don't hit cURL.
	 *
	 * @return void
	 */
	public function setup_stripe_mock_client() {
		if ( ! $this->is_simulation_mode() ) {
			return;
		}

		$apply_mock = function () {
			if ( ! class_exists( 'Stripe\Stripe' ) ) {
				$stripe_path = WP_PLUGIN_DIR . '/user-registration-pro/vendor/stripe/stripe-php/init.php';
				if ( file_exists( $stripe_path ) ) {
					require_once $stripe_path;
				}
			}

			// In PHP, interface_exists is required when checking interfaces.
			if ( class_exists( 'Stripe\ApiRequestor' ) && ( class_exists( 'Stripe\HttpClient\ClientInterface' ) || interface_exists( 'Stripe\HttpClient\ClientInterface' ) ) ) {
				require_once URPT_PLUGIN_DIR . 'includes/class-urpt-stripe-mock-client.php';
				\Stripe\ApiRequestor::setHttpClient( new URPT_Stripe_Mock_Client() );
			}
			if ( class_exists( 'Stripe\Stripe' ) ) {
				\Stripe\Stripe::setApiKey( 'sk_test_urpt_simulated_secret_key' );
			}
		};

		$apply_mock();
		add_action( 'init', $apply_mock, 1 );
	}
}
