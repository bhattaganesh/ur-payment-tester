<?php
/**
 * URPT Test Bench Partial View
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="urpt-grid urpt-grid-2">
	<!-- Left Card: Subscription Picker & Time-Travel -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h2><span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'Subscription Time-Travel Engine', 'ur-payment-tester' ); ?></h2>
		</div>
		<div class="urpt-card-body">
			<div class="urpt-form-group">
				<label for="urpt-sub-picker"><strong><?php esc_html_e( 'Select Target Member / Subscription:', 'ur-payment-tester' ); ?></strong></label>
				<select id="urpt-sub-picker" class="widefat">
					<?php if ( empty( $subscriptions ) ) : ?>
						<option value="1"><?php esc_html_e( 'No active subscriptions found — Use Mock ID #1', 'ur-payment-tester' ); ?></option>
					<?php else : ?>
						<?php foreach ( $subscriptions as $s ) : ?>
							<option value="<?php echo esc_attr( $s['ID'] ); ?>">
								<?php
								printf(
									'#%d — User #%d | Status: %s | Next Due: %s | %s ($%s)',
									esc_html( $s['ID'] ),
									esc_html( $s['user_id'] ),
									esc_html( strtoupper( $s['status'] ) ),
									esc_html( substr( $s['next_billing_date'] ?? 'N/A', 0, 10 ) ),
									esc_html( ucfirst( $s['billing_cycle'] ) ),
									esc_html( number_format( (float) $s['billing_amount'], 2 ) )
								);
								?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<div class="urpt-button-group">
				<button type="button" class="button button-primary urpt-action-btn" data-action="shift_dates" data-days="30">
					<span class="dashicons dashicons-controls-forward"></span> <?php esc_html_e( 'Advance 30 Days & Renew', 'ur-payment-tester' ); ?>
				</button>
				<button type="button" class="button urpt-action-btn" data-action="shift_dates" data-days="7">
					+7 <?php esc_html_e( 'Days', 'ur-payment-tester' ); ?>
				</button>
				<button type="button" class="button urpt-action-btn" data-action="shift_dates" data-days="-30">
					-30 <?php esc_html_e( 'Days (Rewind)', 'ur-payment-tester' ); ?>
				</button>
			</div>

			<div class="urpt-divider"></div>

			<h4><?php esc_html_e( 'Programmatic Cron Execution', 'ur-payment-tester' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Executes native URM cron handlers immediately without waiting for WordPress cron intervals.', 'ur-payment-tester' ); ?></p>
			<div class="urpt-button-group">
				<button type="button" class="button urpt-cron-btn" data-cron="urm_daily_membership_renewal_check">
					<?php esc_html_e( 'Renewal Check Cron', 'ur-payment-tester' ); ?>
				</button>
				<button type="button" class="button urpt-cron-btn" data-cron="urm_daily_payment_retry_check">
					<?php esc_html_e( 'Payment Retry Cron', 'ur-payment-tester' ); ?>
				</button>
				<button type="button" class="button urpt-cron-btn" data-cron="urm_daily_membership_expiration_check">
					<?php esc_html_e( 'Expiration Check Cron', 'ur-payment-tester' ); ?>
				</button>
				<button type="button" class="button button-secondary urpt-action-btn" data-action="run_all_crons">
					<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Run All Crons', 'ur-payment-tester' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Right Card: Quick Gateway Lifecycle Actions -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h2><span class="dashicons dashicons-randomize"></span> <?php esc_html_e( '1-Click Lifecycle Simulations', 'ur-payment-tester' ); ?></h2>
		</div>
		<div class="urpt-card-body">
			<div class="urpt-action-card">
				<div>
					<strong><?php esc_html_e( 'Simulate Payment Failure / Card Decline', 'ur-payment-tester' ); ?></strong>
					<p class="description"><?php esc_html_e( 'Dispatches invoice.payment_failed to test grace periods, retry counters, and retry warning emails.', 'ur-payment-tester' ); ?></p>
				</div>
				<button type="button" class="button button-secondary urpt-webhook-quick-btn" data-gateway="stripe" data-event="invoice.payment_failed">
					<?php esc_html_e( 'Inject Failure', 'ur-payment-tester' ); ?>
				</button>
			</div>

			<div class="urpt-action-card">
				<div>
					<strong><?php esc_html_e( 'Simulate Provider Cancellation', 'ur-payment-tester' ); ?></strong>
					<p class="description"><?php esc_html_e( 'Dispatches customer.subscription.deleted to immediately mark subscription as cancelled.', 'ur-payment-tester' ); ?></p>
				</div>
				<button type="button" class="button button-secondary urpt-webhook-quick-btn" data-gateway="stripe" data-event="customer.subscription.deleted">
					<?php esc_html_e( 'Trigger Cancellation', 'ur-payment-tester' ); ?>
				</button>
			</div>

			<div class="urpt-action-card">
				<div>
					<strong><?php esc_html_e( 'Simulate Charge Refund', 'ur-payment-tester' ); ?></strong>
					<p class="description"><?php esc_html_e( 'Dispatches charge.refunded to update order status to refunded and revoke access.', 'ur-payment-tester' ); ?></p>
				</div>
				<button type="button" class="button button-secondary urpt-webhook-quick-btn" data-gateway="stripe" data-event="charge.refunded">
					<?php esc_html_e( 'Process Refund', 'ur-payment-tester' ); ?>
				</button>
			</div>

			<div class="urpt-divider"></div>

			<h4><?php esc_html_e( 'Direct Bank Transfer Approval Bench', 'ur-payment-tester' ); ?></h4>
			<?php if ( empty( $pending_bank_orders ) ) : ?>
				<p class="description"><?php esc_html_e( 'No pending Direct Bank Transfer orders found. Stage a test order below or run Scenario 6.', 'ur-payment-tester' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order #', 'ur-payment-tester' ); ?></th>
							<th><?php esc_html_e( 'User', 'ur-payment-tester' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'ur-payment-tester' ); ?></th>
							<th><?php esc_html_e( 'Action', 'ur-payment-tester' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pending_bank_orders as $bo ) : ?>
							<tr>
								<td><strong>#<?php echo esc_html( $bo['ID'] ); ?></strong></td>
								<td>#<?php echo esc_html( $bo['user_id'] ); ?></td>
								<td>$<?php echo esc_html( number_format( (float) $bo['total_amount'], 2 ) ); ?></td>
								<td>
									<button type="button" class="button button-small button-primary urpt-approve-bank-btn" data-order="<?php echo esc_attr( $bo['ID'] ); ?>">
										<?php esc_html_e( 'Approve & Release Login', 'ur-payment-tester' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
