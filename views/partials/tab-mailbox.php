<?php
/**
 * URPT Mailbox Trap Partial View
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="urpt-card">
	<div class="urpt-card-header urpt-flex-between">
		<div>
			<h2><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Local Mail Trap & Visual Mailbox', 'ur-payment-tester' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'All outgoing emails triggered during payment simulations are trapped locally. No emails are transmitted externally.', 'ur-payment-tester' ); ?>
			</p>
		</div>
		<button type="button" class="button button-secondary" id="urpt-clear-mailbox-btn">
			<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear All Trapped Emails', 'ur-payment-tester' ); ?>
		</button>
	</div>
	<div class="urpt-card-body">
		<?php if ( empty( $trapped_emails ) ) : ?>
			<div class="urpt-empty-state">
				<span class="dashicons dashicons-email-alt" style="font-size: 40px; width: 40px; height: 40px; color: #a7aaad;"></span>
				<p><strong><?php esc_html_e( 'Your visual mailbox is empty.', 'ur-payment-tester' ); ?></strong></p>
				<p class="description"><?php esc_html_e( 'Emails sent by URM (welcome emails, payment reminders, failed payment warnings, PDF invoices) will appear here in real time.', 'ur-payment-tester' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat striped urpt-table-mailbox">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'ur-payment-tester' ); ?></th>
						<th><?php esc_html_e( 'Recipient (To)', 'ur-payment-tester' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'ur-payment-tester' ); ?></th>
						<th><?php esc_html_e( 'Attachments', 'ur-payment-tester' ); ?></th>
						<th><?php esc_html_e( 'Action', 'ur-payment-tester' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $trapped_emails as $mail ) : ?>
						<tr>
							<td><code><?php echo esc_html( $mail['timestamp'] ); ?></code></td>
							<td><strong><?php echo esc_html( $mail['to'] ); ?></strong></td>
							<td><?php echo esc_html( $mail['subject'] ); ?></td>
							<td>
								<?php
								$att_count = ! empty( $mail['attachments'] ) && is_array( $mail['attachments'] ) ? count( $mail['attachments'] ) : 0;
								if ( $att_count > 0 ) {
									echo '<span class="urpt-pill urpt-pill-att"><span class="dashicons dashicons-paperclip"></span> ' . esc_html( $att_count ) . '</span>';
								} else {
									echo '<span class="urpt-muted">—</span>';
								}
								?>
							</td>
							<td>
								<button type="button" class="button button-small urpt-view-mail-btn" data-mail='<?php echo esc_attr( wp_json_encode( $mail ) ); ?>'>
									<?php esc_html_e( 'View Content', 'ur-payment-tester' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<!-- Modal for Viewing Trapped Email Body -->
<div id="urpt-mail-modal" class="urpt-modal" style="display:none;">
	<div class="urpt-modal-content">
		<div class="urpt-modal-header">
			<h3 id="urpt-modal-subject"></h3>
			<button type="button" class="urpt-modal-close">&times;</button>
		</div>
		<div class="urpt-modal-meta">
			<p><strong><?php esc_html_e( 'To:', 'ur-payment-tester' ); ?></strong> <span id="urpt-modal-to"></span></p>
			<p><strong><?php esc_html_e( 'Sent At:', 'ur-payment-tester' ); ?></strong> <span id="urpt-modal-time"></span></p>
		</div>
		<div class="urpt-modal-body" id="urpt-modal-body"></div>
	</div>
</div>
