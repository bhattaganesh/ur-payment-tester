<?php
/**
 * URPT Webhook Synthesizer Partial View
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="urpt-grid urpt-grid-2">
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h2><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'Synthesize & Dispatch Webhook', 'ur-payment-tester' ); ?></h2>
		</div>
		<div class="urpt-card-body">
			<form id="urpt-webhook-form">
				<div class="urpt-form-group">
					<label for="urpt-wh-gateway"><strong><?php esc_html_e( 'Target Gateway:', 'ur-payment-tester' ); ?></strong></label>
					<select id="urpt-wh-gateway" class="widefat">
						<option value="stripe">Stripe (REST /wp-json/user-registration/stripe-webhook)</option>
						<option value="paypal">PayPal REST (POST /wp-json/user-registration/paypal-webhook)</option>
						<option value="authorize">Authorize.Net (Webhook Simulation & Driver)</option>
						<option value="mollie">Mollie (Webhook Simulation & Driver)</option>
					</select>
				</div>

				<div class="urpt-form-group">
					<label for="urpt-wh-event"><strong><?php esc_html_e( 'Event Type:', 'ur-payment-tester' ); ?></strong></label>
					<select id="urpt-wh-event" class="widefat">
						<!-- Stripe Events -->
						<optgroup label="Stripe Lifecycle Events" id="urpt-stripe-events">
							<option value="invoice.payment_succeeded">invoice.payment_succeeded (Renewal / Initial Payment)</option>
							<option value="invoice.payment_failed">invoice.payment_failed (Card Decline / Insufficient Funds)</option>
							<option value="customer.subscription.deleted">customer.subscription.deleted (Plan Cancellation)</option>
							<option value="charge.refunded">charge.refunded (Order Refund)</option>
							<option value="customer.subscription.created">customer.subscription.created (UR-4386 Delayed Schedule)</option>
							<option value="payment_intent.payment_failed">payment_intent.payment_failed (Frontend Intent Failure)</option>
						</optgroup>

						<!-- PayPal Events -->
						<optgroup label="PayPal REST Events" id="urpt-paypal-events" style="display:none;">
							<option value="PAYMENT.CAPTURE.COMPLETED">PAYMENT.CAPTURE.COMPLETED (One-Time Order)</option>
							<option value="BILLING.SUBSCRIPTION.ACTIVATED">BILLING.SUBSCRIPTION.ACTIVATED (Subscription Start)</option>
							<option value="PAYMENT.SALE.COMPLETED">PAYMENT.SALE.COMPLETED (Recurring Cycle)</option>
							<option value="BILLING.SUBSCRIPTION.PAYMENT.FAILED">BILLING.SUBSCRIPTION.PAYMENT.FAILED (Declined Cycle)</option>
							<option value="BILLING.SUBSCRIPTION.CANCELLED">BILLING.SUBSCRIPTION.CANCELLED (Buyer/Admin Cancel)</option>
						</optgroup>

						<!-- Authorize.Net Events -->
						<optgroup label="Authorize.Net Events" id="urpt-authorize-events" style="display:none;">
							<option value="net.authorize.payment.authcapture.created">net.authorize.payment.authcapture.created (Payment Succeeded)</option>
							<option value="net.authorize.customer.subscription.failed">net.authorize.customer.subscription.failed (Payment Failed)</option>
							<option value="net.authorize.customer.subscription.cancelled">net.authorize.customer.subscription.cancelled (Subscription Cancelled)</option>
							<option value="net.authorize.payment.refund.created">net.authorize.payment.refund.created (Payment Refunded)</option>
						</optgroup>

						<!-- Mollie Events -->
						<optgroup label="Mollie Events" id="urpt-mollie-events" style="display:none;">
							<option value="payment.paid">payment.paid (Renewal / Payment Paid)</option>
							<option value="payment.failed">payment.failed (Payment Failed)</option>
							<option value="subscription.canceled">subscription.canceled (Subscription Canceled)</option>
							<option value="payment.refunded">payment.refunded (Payment Refunded)</option>
						</optgroup>
					</select>
				</div>

				<div class="urpt-form-group">
					<label for="urpt-wh-sub-id"><?php esc_html_e( 'Target Subscription ID / Reference:', 'ur-payment-tester' ); ?></label>
					<input type="text" id="urpt-wh-sub-id" class="widefat" placeholder="e.g. sub_12345 or I-BW4567" value="sub_urpt_test">
				</div>

				<div class="urpt-form-group">
					<label for="urpt-wh-amount"><?php esc_html_e( 'Charge Amount ($):', 'ur-payment-tester' ); ?></label>
					<input type="number" step="0.01" id="urpt-wh-amount" class="widefat" value="19.99">
				</div>

				<button type="submit" class="button button-primary button-large" id="urpt-wh-dispatch-btn">
					<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Dispatch Signed Webhook to URM Endpoint', 'ur-payment-tester' ); ?>
				</button>
			</form>
		</div>
	</div>

	<div class="urpt-card">
		<div class="urpt-card-header">
			<h2><span class="dashicons dashicons-media-code"></span> <?php esc_html_e( 'Cryptographic & REST Routing Overview', 'ur-payment-tester' ); ?></h2>
		</div>
		<div class="urpt-card-body">
			<div class="urpt-code-preview">
				<p><strong><?php esc_html_e( 'Stripe Routing & Verification:', 'ur-payment-tester' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Route: POST /wp-json/user-registration/stripe-webhook', 'ur-payment-tester' ); ?></li>
					<li><?php esc_html_e( 'Signature Header: Stripe-Signature (HMAC-SHA256 timestamped hash)', 'ur-payment-tester' ); ?></li>
					<li><?php esc_html_e( 'Automated Secret Injection: Filtered through user_registration_stripe_webhook_secret', 'ur-payment-tester' ); ?></li>
				</ul>

				<p><strong><?php esc_html_e( 'PayPal REST Routing & Verification:', 'ur-payment-tester' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Route: POST /wp-json/user-registration/paypal-webhook', 'ur-payment-tester' ); ?></li>
					<li><?php esc_html_e( 'Handshake Interceptor: pre_http_request mocks /v1/notifications/verify-webhook-signature', 'ur-payment-tester' ); ?></li>
					<li><?php esc_html_e( 'Verification Return: {"verification_status": "SUCCESS"}', 'ur-payment-tester' ); ?></li>
				</ul>

				<p><strong><?php esc_html_e( 'Authorize.Net Webhook & Silent Post Routing:', 'ur-payment-tester' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Driver: URPT_Driver_Authorize with silent post / webhook event dispatching', 'ur-payment-tester' ); ?></li>
					<li><?php esc_html_e( 'Signature Header: X-Anet-Signature (HMAC-SHA512 verification support)', 'ur-payment-tester' ); ?></li>
				</ul>

				<p><strong><?php esc_html_e( 'Mollie Webhook Routing:', 'ur-payment-tester' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Driver: URPT_Driver_Mollie with payment & subscription status callbacks', 'ur-payment-tester' ); ?></li>
					<li><?php esc_html_e( 'Payload: id (Mollie payment/subscription identifier)', 'ur-payment-tester' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</div>
