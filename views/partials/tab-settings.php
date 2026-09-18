<?php
/**
 * URPT Settings & Purge Partial View
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="urpt-grid urpt-grid-2">
	<!-- Operating Modes -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h2><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Suite Operating Mode', 'ur-payment-tester' ); ?></h2>
		</div>
		<div class="urpt-card-body">
			<form id="urpt-settings-form">
				<div class="urpt-radio-card">
					<label>
						<input type="radio" name="operating_mode" value="simulated" <?php checked( $current_mode, 'simulated' ); ?>>
						<strong><?php esc_html_e( 'Mode A: Zero-Credentials Simulation Mode (Default)', 'ur-payment-tester' ); ?></strong>
					</label>
					<p class="description">
						<?php esc_html_e( 'Intercepts outgoing gateway HTTP requests in memory with 0ms latency. Allows instant testing on fresh TasteWP sites without configuring API keys.', 'ur-payment-tester' ); ?>
					</p>
				</div>

				<div class="urpt-radio-card">
					<label>
						<input type="radio" name="operating_mode" value="hybrid" <?php checked( $current_mode, 'hybrid' ); ?>>
						<strong><?php esc_html_e( 'Mode B: Hybrid Live-Sandbox Mode', 'ur-payment-tester' ); ?></strong>
					</label>
					<p class="description">
						<?php esc_html_e( 'Allows initial registration using real Stripe Test or PayPal Sandbox keys, then takes over lifecycle events (fast-forwarding renewals, webhooks, declines) on demand.', 'ur-payment-tester' ); ?>
					</p>
				</div>

				<div class="urpt-divider"></div>

				<div class="urpt-form-group">
					<label>
						<input type="checkbox" name="mail_trap" value="yes" <?php checked( $mail_trap_on, true ); ?>>
						<strong><?php esc_html_e( 'Enable Local Mail Trap', 'ur-payment-tester' ); ?></strong>
					</label>
					<p class="description">
						<?php esc_html_e( 'Prevents emails from being sent to external SMTP servers during test simulations.', 'ur-payment-tester' ); ?>
					</p>
				</div>

				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'ur-payment-tester' ); ?>
				</button>
			</form>
		</div>
	</div>

	<!-- Safe Environment & Teardown -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Environment Guard & Test Data Purge', 'ur-payment-tester' ); ?></h2>
		</div>
		<div class="urpt-card-body">
			<table class="widefat striped">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Current Host:', 'ur-payment-tester' ); ?></strong></td>
						<td><code><?php echo esc_html( $_SERVER['HTTP_HOST'] ?? 'localhost' ); ?></code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Environment Safety Guard:', 'ur-payment-tester' ); ?></strong></td>
						<td><span class="urpt-pill urpt-pill-green"><?php esc_html_e( 'Active & Verified Safe', 'ur-payment-tester' ); ?></span></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WP_DEBUG Enabled:', 'ur-payment-tester' ); ?></strong></td>
						<td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Yes' : 'No'; ?></td>
					</tr>
				</tbody>
			</table>

			<div class="urpt-divider"></div>

			<h4><?php esc_html_e( 'Safe Test Data Purge / Reset', 'ur-payment-tester' ); ?></h4>
			<p class="description">
				<?php esc_html_e( 'Deletes all simulated test orders, mock subscriptions, test users, and trapped emails created by this suite. Leaves core data intact.', 'ur-payment-tester' ); ?>
			</p>
			<button type="button" class="button button-danger" id="urpt-purge-btn">
				<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Purge All Simulated Test Data', 'ur-payment-tester' ); ?>
			</button>
		</div>
	</div>
</div>
