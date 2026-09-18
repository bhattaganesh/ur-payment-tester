<?php
/**
 * URPT Admin Menu & AJAX Controller
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles WP Admin views, scripts enqueueing, and AJAX simulation requests.
 */
class URPT_Admin {

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

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Register AJAX actions.
		$ajax_actions = array(
			'urpt_shift_dates'       => 'ajax_shift_dates',
			'urpt_run_crons'         => 'ajax_run_crons',
			'urpt_dispatch_webhook'  => 'ajax_dispatch_webhook',
			'urpt_run_scenario'      => 'ajax_run_scenario',
			'urpt_purge_data'        => 'ajax_purge_data',
			'urpt_approve_bank'      => 'ajax_approve_bank',
			'urpt_clear_mailbox'     => 'ajax_clear_mailbox',
			'urpt_save_settings'     => 'ajax_save_settings',
			'urpt_test_addon'        => 'ajax_test_addon',
		);

		foreach ( $ajax_actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Registers the admin menu item under User Registration.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		$parent_slug = 'user-registration';
		$page_title  = __( 'UR Payment Tester', 'ur-payment-tester' );
		$menu_title  = __( 'Payment Tester', 'ur-payment-tester' );
		$capability  = 'manage_options';
		$menu_slug   = 'ur-payment-tester';

		global $menu;
		$parent_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && 'user-registration' === $item[2] ) {
					$parent_exists = true;
					break;
				}
			}
		}

		if ( $parent_exists ) {
			add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, array( $this, 'render_dashboard' ) );
		} else {
			add_menu_page( $page_title, $page_title, $capability, $menu_slug, array( $this, 'render_dashboard' ), 'dashicons-money-alt', 58 );
		}
	}

	/**
	 * Enqueues CSS and JS assets on the Payment Tester admin screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'ur-payment-tester' ) ) {
			return;
		}

		wp_enqueue_style( 'urpt-admin-css', URPT_PLUGIN_URL . 'assets/css/urpt-admin.css', array(), URPT_VERSION );
		wp_enqueue_script( 'urpt-admin-js', URPT_PLUGIN_URL . 'assets/js/urpt-admin.js', array( 'jquery' ), URPT_VERSION, true );

		wp_localize_script(
			'urpt-admin-js',
			'urpt_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'urpt_admin_nonce' ),
				'strings'  => array(
					'running'    => __( 'Executing...', 'ur-payment-tester' ),
					'success'    => __( 'Completed successfully', 'ur-payment-tester' ),
					'failed'     => __( 'Action failed', 'ur-payment-tester' ),
					'confirm'    => __( 'Are you sure you want to purge all simulated data?', 'ur-payment-tester' ),
				),
			)
		);
	}

	/**
	 * Validates AJAX request nonce and permissions.
	 *
	 * @return void
	 */
	private function verify_ajax() {
		check_ajax_referer( 'urpt_admin_nonce', 'nonce' );
		if ( ! urpt_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ur-payment-tester' ) ), 403 );
		}
	}

	/**
	 * AJAX handler for shifting dates on a subscription.
	 *
	 * @return void
	 */
	public function ajax_shift_dates() {
		$this->verify_ajax();

		$sub_id = isset( $_POST['subscription_id'] ) ? absint( wp_unslash( $_POST['subscription_id'] ) ) : 0;
		$days   = isset( $_POST['days'] ) ? intval( wp_unslash( $_POST['days'] ) ) : 30;

		try {
			$res = $this->core->time_travel->shift_subscription_dates( $sub_id, $days );
			wp_send_json_success( $res );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for triggering native URM crons.
	 *
	 * @return void
	 */
	public function ajax_run_crons() {
		$this->verify_ajax();

		$crons = isset( $_POST['crons'] ) && is_array( $_POST['crons'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['crons'] ) )
			: array();

		$log = $this->core->time_travel->trigger_crons( $crons );
		wp_send_json_success( array( 'cron_log' => $log ) );
	}

	/**
	 * AJAX handler for dispatching webhooks.
	 *
	 * @return void
	 */
	public function ajax_dispatch_webhook() {
		$this->verify_ajax();

		$gateway = sanitize_text_field( wp_unslash( $_POST['gateway'] ?? 'stripe' ) );
		$event   = sanitize_text_field( wp_unslash( $_POST['event'] ?? 'invoice.payment_succeeded' ) );
		$sub_raw = sanitize_text_field( wp_unslash( $_POST['subscription_id'] ?? '' ) );
		$amount  = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 19.99;

		$context = array(
			'subscription_id' => ! empty( $sub_raw ) ? $sub_raw : 'sub_urpt_' . time(),
			'amount'          => $amount,
		);
		$res     = $this->core->webhook_dispatcher->dispatch( $gateway, $event, $context );

		wp_send_json_success( $res );
	}

	/**
	 * AJAX handler for running scenario recipes.
	 *
	 * @return void
	 */
	public function ajax_run_scenario() {
		$this->verify_ajax();

		$id = absint( $_POST['scenario_id'] ?? 1 );

		try {
			$res = $this->core->scenario_runner->run( $id );
			wp_send_json_success( $res );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for purging simulated test data.
	 *
	 * @return void
	 */
	public function ajax_purge_data() {
		$this->verify_ajax();

		$summary = $this->core->purge_test_data();
		wp_send_json_success( $summary );
	}

	/**
	 * AJAX handler for approving bank transfer payments.
	 *
	 * @return void
	 */
	public function ajax_approve_bank() {
		$this->verify_ajax();

		$order_id = absint( $_POST['order_id'] ?? 0 );

		try {
			$res = $this->core->driver_bank->approve_payment( $order_id );
			wp_send_json_success( $res );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for clearing the local mail trap.
	 *
	 * @return void
	 */
	public function ajax_clear_mailbox() {
		$this->verify_ajax();

		$this->core->mail_trap->clear_trapped_emails();
		wp_send_json_success( array( 'cleared' => true ) );
	}

	/**
	 * AJAX handler for updating tester settings.
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		$this->verify_ajax();

		$mode      = isset( $_POST['operating_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['operating_mode'] ) ) : 'simulated';
		$mail_trap = isset( $_POST['mail_trap'] ) ? sanitize_text_field( wp_unslash( $_POST['mail_trap'] ) ) : 'yes';

		update_option( 'urpt_operating_mode', $mode );
		update_option( 'urpt_mail_trap_enabled', $mail_trap );

		wp_send_json_success( array( 'saved' => true, 'mode' => $mode, 'mail_trap' => $mail_trap ) );
	}

	/**
	 * AJAX handler for addon-specific test drivers.
	 *
	 * @return void
	 */
	public function ajax_test_addon() {
		$this->verify_ajax();

		$addon = sanitize_text_field( $_POST['addon'] ?? '' );
		$res   = array();

		switch ( $addon ) {
			case 'team':
				$model = sanitize_text_field( $_POST['model'] ?? 'per_seat' );
				$seats = absint( $_POST['seats'] ?? 5 );
				$rate  = (float) ( $_POST['rate'] ?? 15.00 );
				$res   = $this->core->driver_team->calculate_price( $model, $rate, $seats );
				break;

			case 'upgrade':
				$curr = (float) ( $_POST['curr_amount'] ?? 10.00 );
				$new  = (float) ( $_POST['new_amount'] ?? 30.00 );
				$days = absint( $_POST['days_passed'] ?? 15 );
				$res  = $this->core->driver_upgrades->calculate_prorated_delta( $curr, $new, 30, $days );
				break;

			case 'currency':
				$subtotal = (float) ( $_POST['subtotal'] ?? 50.00 );
				$rate     = (float) ( $_POST['tax_rate'] ?? 10.0 );
				$mode     = sanitize_text_field( $_POST['tax_mode'] ?? 'exclusive' );
				$currency = sanitize_text_field( $_POST['target_currency'] ?? 'EUR' );
				$fx_rates = array(
					'EUR' => 0.92,
					'GBP' => 0.79,
					'CAD' => 1.35,
					'AUD' => 1.52,
					'JPY' => 155.0,
					'INR' => 83.5,
					'BRL' => 5.40,
				);
				$fx_rate  = $fx_rates[ $currency ] ?? 1.0;

				$tax_res = $this->core->driver_currency->calculate_tax( $subtotal, $rate, $mode );
				$fx_res  = $this->core->driver_currency->convert_to_local_currency( $tax_res['total_amount'], $fx_rate, $currency );
				$res     = array(
					'tax_breakdown' => $tax_res,
					'local_currency'=> $fx_res,
				);
				break;

			case 'coupon':
				$subtotal    = (float) ( $_POST['subtotal'] ?? 50.00 );
				$coupon_type = sanitize_text_field( $_POST['coupon_type'] ?? 'percent' );
				$val         = (float) ( $_POST['value'] ?? 20.00 );
				$res         = $this->core->driver_coupon->apply_coupon( $subtotal, $coupon_type, $val, 'SIMULATED_COUPON' );
				break;

			case 'invoice':
				$order_id = absint( $_POST['order_id'] ?? 101 );
				$res      = array(
					'invoice_number' => $this->core->driver_invoice->generate_invoice_number( $order_id ),
					'order_id'       => $order_id,
					'status'         => 'verified',
					'download_url'   => home_url( '/?ur_download_pdf=1&order_id=' . $order_id . '&token=' . wp_generate_password( 24, false ) ),
				);
				break;

			case 'content':
				$sub_id = absint( $_POST['subscription_id'] ?? 1 );
				$res    = $this->core->driver_content->simulate_lockout( $sub_id );
				break;
		}

		wp_send_json_success( $res );
	}

	/**
	 * Renders the main Payment Tester dashboard view.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		global $wpdb;

		// Fetch subscriptions for dropdown selector.
		$subs_table = $wpdb->prefix . 'ur_membership_subscriptions';
		$subscriptions = array();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $subs_table ) ) === $subs_table ) {
			$subscriptions = $wpdb->get_results( "SELECT * FROM {$subs_table} ORDER BY ID DESC LIMIT 30", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Fetch pending bank orders for approval bench.
		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$pending_bank_orders = array();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table ) {
			$pending_bank_orders = $wpdb->get_results( "SELECT * FROM {$orders_table} WHERE payment_method = 'bank' AND status = 'pending' ORDER BY ID DESC LIMIT 15", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$trapped_emails = $this->core->mail_trap->get_trapped_emails();
		$current_mode   = get_option( 'urpt_operating_mode', 'simulated' );
		$mail_trap_on   = 'yes' === get_option( 'urpt_mail_trap_enabled', 'yes' );

		require_once URPT_PLUGIN_DIR . 'views/dashboard.php';
	}
}
