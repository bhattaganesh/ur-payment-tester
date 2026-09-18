<?php
/**
 * Plugin Name: UR Payment Testing & Simulation Suite
 * Plugin URI: https://github.com/bhattaganesh/ur-payment-tester
 * Description: Enterprise companion plugin for testing and simulating all payment gateways, recurring lifecycles, and auxiliary addons in User Registration Pro and its Membership module.
 * Version: 1.0.0
 * Author: Ganesh Bhatta
 * Author URI: https://github.com/bhattaganesh
 * License: GPL-2.0+
 * Text Domain: ur-payment-tester
 * Domain Path: /languages
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

define( 'URPT_VERSION', '1.0.0' );
define( 'URPT_PLUGIN_FILE', __FILE__ );
define( 'URPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'URPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Checks whether the current environment allows payment simulation.
 *
 * Unrestricted for developer & QA usage across local, staging, TasteWP, and test instances.
 *
 * @return bool True always so developers and QA can test anywhere.
 */
function urpt_is_safe_environment() {
	return true;
}

/**
 * Verifies whether the current user has permission to manage payment simulations.
 *
 * @return bool True if capable, false otherwise.
 */
function urpt_user_can_access() {
	return current_user_can( 'manage_user_registration' ) || current_user_can( 'manage_options' );
}

/**
 * Displays an admin notice if User Registration is not active.
 *
 * @return void
 */
function urpt_missing_dependency_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'UR Payment Testing & Simulation Suite requires User Registration Pro and its Membership module to be installed and active.', 'ur-payment-tester' ); ?></p>
	</div>
	<?php
}

/**
 * Initializes the plugin once all plugins have loaded.
 *
 * @return void
 */
function urpt_init() {
	if ( ! class_exists( 'UserRegistration' ) ) {
		add_action( 'admin_notices', 'urpt_missing_dependency_notice' );
		return;
	}

	require_once URPT_PLUGIN_DIR . 'includes/class-urpt-core.php';
	URPT_Core::instance();
}
add_action( 'plugins_loaded', 'urpt_init', 20 );

/**
 * Plugin activation callback.
 *
 * Sets up initial options for operating mode and email trap.
 *
 * @return void
 */
function urpt_activate() {
	if ( false === get_option( 'urpt_operating_mode', false ) ) {
		add_option( 'urpt_operating_mode', 'simulated' );
	}
	if ( false === get_option( 'urpt_mail_trap_enabled', false ) ) {
		add_option( 'urpt_mail_trap_enabled', 'yes' );
	}
}
register_activation_hook( __FILE__, 'urpt_activate' );
