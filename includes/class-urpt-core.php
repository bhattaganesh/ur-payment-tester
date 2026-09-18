<?php
/**
 * URPT Core Service Container
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main service container and coordinator for the testing suite.
 */
class URPT_Core {

	/**
	 * Singleton instance.
	 *
	 * @var URPT_Core|null
	 */
	private static $instance = null;

	/**
	 * HTTP Interceptor instance.
	 *
	 * @var URPT_HTTP_Interceptor
	 */
	public $http_interceptor;

	/**
	 * Time travel engine instance.
	 *
	 * @var URPT_Time_Travel
	 */
	public $time_travel;

	/**
	 * Webhook dispatcher instance.
	 *
	 * @var URPT_Webhook_Dispatcher
	 */
	public $webhook_dispatcher;

	/**
	 * Scenario runner instance.
	 *
	 * @var URPT_Scenario_Runner
	 */
	public $scenario_runner;

	/**
	 * Assertions engine instance.
	 *
	 * @var URPT_Assertions
	 */
	public $assertions;

	/**
	 * Mail trap instance.
	 *
	 * @var URPT_Mail_Trap
	 */
	public $mail_trap;

	/**
	 * Admin controller instance.
	 *
	 * @var URPT_Admin
	 */
	public $admin;

	/**
	 * Stripe simulation driver.
	 *
	 * @var URPT_Driver_Stripe
	 */
	public $driver_stripe;

	/**
	 * PayPal simulation driver.
	 *
	 * @var URPT_Driver_Paypal
	 */
	public $driver_paypal;

	/**
	 * Direct bank transfer simulation driver.
	 *
	 * @var URPT_Driver_Bank
	 */
	public $driver_bank;

	/**
	 * Upgrades simulation driver.
	 *
	 * @var URPT_Driver_Upgrades
	 */
	public $driver_upgrades;

	/**
	 * Team membership simulation driver.
	 *
	 * @var URPT_Driver_Team
	 */
	public $driver_team;

	/**
	 * Local currency and tax simulation driver.
	 *
	 * @var URPT_Driver_Currency
	 */
	public $driver_currency;

	/**
	 * Coupon simulation driver.
	 *
	 * @var URPT_Driver_Coupon
	 */
	public $driver_coupon;

	/**
	 * Content restriction simulation driver.
	 *
	 * @var URPT_Driver_Content
	 */
	public $driver_content;

	/**
	 * PDF invoice simulation driver.
	 *
	 * @var URPT_Driver_Invoice
	 */
	public $driver_invoice;

	/**
	 * Authorize.Net simulation driver.
	 *
	 * @var URPT_Driver_Authorize
	 */
	public $driver_authorize;

	/**
	 * Mollie simulation driver.
	 *
	 * @var URPT_Driver_Mollie
	 */
	public $driver_mollie;

	/**
	 * Retrieves the singleton instance.
	 *
	 * @return URPT_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Protected constructor to enforce singleton pattern.
	 */
	protected function __construct() {
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Requires all internal plugin component files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once URPT_PLUGIN_DIR . 'includes/class-urpt-http-interceptor.php';
		require_once URPT_PLUGIN_DIR . 'includes/class-urpt-time-travel.php';
		require_once URPT_PLUGIN_DIR . 'includes/class-urpt-webhook-dispatcher.php';
		require_once URPT_PLUGIN_DIR . 'includes/class-urpt-assertions.php';
		require_once URPT_PLUGIN_DIR . 'includes/class-urpt-mail-trap.php';
		require_once URPT_PLUGIN_DIR . 'includes/class-urpt-scenario-runner.php';
		require_once URPT_PLUGIN_DIR . 'includes/class-urpt-admin.php';

		// Gateway and addon drivers.
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-stripe.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-paypal.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-bank.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-upgrades.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-team.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-currency.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-coupon.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-content.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-invoice.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-authorize.php';
		require_once URPT_PLUGIN_DIR . 'includes/drivers/class-urpt-driver-mollie.php';

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once URPT_PLUGIN_DIR . 'includes/class-urpt-cli.php';
		}
	}

	/**
	 * Instantiates and coordinates all plugin modules.
	 *
	 * @return void
	 */
	private function init_components() {
		$this->assertions         = new URPT_Assertions();
		$this->mail_trap          = new URPT_Mail_Trap();
		$this->time_travel        = new URPT_Time_Travel();
		$this->webhook_dispatcher = new URPT_Webhook_Dispatcher();

		// Initialize gateway and addon drivers.
		$this->driver_stripe    = new URPT_Driver_Stripe();
		$this->driver_paypal    = new URPT_Driver_Paypal();
		$this->driver_bank      = new URPT_Driver_Bank();
		$this->driver_upgrades  = new URPT_Driver_Upgrades();
		$this->driver_team      = new URPT_Driver_Team();
		$this->driver_currency  = new URPT_Driver_Currency();
		$this->driver_coupon    = new URPT_Driver_Coupon();
		$this->driver_content   = new URPT_Driver_Content();
		$this->driver_invoice   = new URPT_Driver_Invoice();
		$this->driver_authorize = new URPT_Driver_Authorize();
		$this->driver_mollie    = new URPT_Driver_Mollie();

		// HTTP interceptor coordinates with drivers in simulation mode.
		$this->http_interceptor = new URPT_HTTP_Interceptor( $this );

		// Scenario runner combines drivers, time-travel, and assertions.
		$this->scenario_runner = new URPT_Scenario_Runner( $this );

		// Admin UI controller.
		$this->admin = new URPT_Admin( $this );
	}

	/**
	 * Purges all simulated test data from database tables and options.
	 *
	 * Only deletes records explicitly created by URPT or tagged with urpt_simulated meta.
	 *
	 * @return array Summary of deleted rows.
	 */
	public function purge_test_data() {
		global $wpdb;

		$deleted_orders        = 0;
		$deleted_subscriptions = 0;
		$deleted_events        = 0;
		$deleted_users         = 0;

		// Clean up trapped emails and logs.
		delete_option( 'urpt_trapped_emails' );
		delete_option( 'urpt_last_scenario_result' );

		$orders_table        = $wpdb->prefix . 'ur_membership_orders';
		$order_meta_table    = $wpdb->prefix . 'ur_membership_ordermeta';
		$subscriptions_table = $wpdb->prefix . 'ur_membership_subscriptions';
		$events_table        = $wpdb->prefix . 'ur_membership_subscription_events';

		// Find orders tagged with simulation meta.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $order_meta_table ) ) === $order_meta_table ) {
			$simulated_order_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT order_id FROM {$order_meta_table} WHERE meta_key = %s",
					'urpt_simulated'
				)
			);

			if ( ! empty( $simulated_order_ids ) ) {
				$ids_placeholder = implode( ',', array_map( 'absint', $simulated_order_ids ) );
				// Delete corresponding subscription events.
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) ) === $events_table ) {
					$deleted_events += (int) $wpdb->query(
						"DELETE FROM {$events_table} WHERE reference_id IN ({$ids_placeholder})"
					);
				}
				// Delete order meta rows.
				$wpdb->query(
					"DELETE FROM {$order_meta_table} WHERE order_id IN ({$ids_placeholder})"
				);
				// Delete orders.
				$deleted_orders += (int) $wpdb->query(
					"DELETE FROM {$orders_table} WHERE ID IN ({$ids_placeholder})"
				);
			}
		}

		// Delete test users created by scenarios with urpt_test_user meta.
		$test_users = get_users(
			array(
				'meta_key'   => 'urpt_test_user',
				'meta_value' => '1',
				'fields'     => 'ID',
			)
		);

		if ( ! empty( $test_users ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ( $test_users as $user_id ) {
				// Clean user subscriptions first.
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subscriptions_table ) ) === $subscriptions_table ) {
					$deleted_subscriptions += (int) $wpdb->delete(
						$subscriptions_table,
						array( 'user_id' => $user_id ),
						array( '%d' )
					);
				}
				wp_delete_user( $user_id );
				$deleted_users++;
			}
		}

		return array(
			'orders'        => $deleted_orders,
			'subscriptions' => $deleted_subscriptions,
			'events'        => $deleted_events,
			'users'         => $deleted_users,
		);
	}
}
