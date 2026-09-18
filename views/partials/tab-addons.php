<?php
/**
 * URPT Addon Drivers Partial View
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="urpt-grid urpt-grid-3">
	<!-- 1. Coupon & Discount Driver -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h3><span class="dashicons dashicons-tag"></span> <?php esc_html_e( 'Coupons Addon Driver', 'ur-payment-tester' ); ?></h3>
		</div>
		<div class="urpt-card-body">
			<form class="urpt-addon-calc-form" data-addon="coupon">
				<div class="urpt-form-group">
					<label><strong><?php esc_html_e( 'Coupon Type:', 'ur-payment-tester' ); ?></strong></label>
					<select name="coupon_type" class="widefat">
						<option value="percent"><?php esc_html_e( 'Percentage (e.g. 20%)', 'ur-payment-tester' ); ?></option>
						<option value="fixed"><?php esc_html_e( 'Fixed Amount (e.g. $10)', 'ur-payment-tester' ); ?></option>
						<option value="100percent"><?php esc_html_e( '100% Discount (UR-4386 Delayed Schedule)', 'ur-payment-tester' ); ?></option>
					</select>
				</div>
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Subtotal ($):', 'ur-payment-tester' ); ?></label>
					<input type="number" step="0.01" name="subtotal" class="widefat" value="50.00">
				</div>
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Discount Value (% or $):', 'ur-payment-tester' ); ?></label>
					<input type="number" step="0.01" name="value" class="widefat" value="20.00">
				</div>
				<button type="submit" class="button button-secondary widefat">
					<?php esc_html_e( 'Validate & Apply Coupon', 'ur-payment-tester' ); ?>
				</button>
			</form>
			<div class="urpt-addon-result"></div>
		</div>
	</div>

	<!-- 2. Team Membership Driver -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h3><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Team Membership Driver', 'ur-payment-tester' ); ?></h3>
		</div>
		<div class="urpt-card-body">
			<form class="urpt-addon-calc-form" data-addon="team">
				<div class="urpt-form-group">
					<label><strong><?php esc_html_e( 'Seat Model:', 'ur-payment-tester' ); ?></strong></label>
					<select name="model" class="widefat">
						<option value="per_seat"><?php esc_html_e( 'Per-Seat ($15/seat)', 'ur-payment-tester' ); ?></option>
						<option value="fixed"><?php esc_html_e( 'Fixed Lump Sum ($100)', 'ur-payment-tester' ); ?></option>
						<option value="tiered"><?php esc_html_e( 'Tiered Pricing (1-5: $50, 6-10: $90)', 'ur-payment-tester' ); ?></option>
					</select>
				</div>
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Seat Count:', 'ur-payment-tester' ); ?></label>
					<input type="number" name="seats" class="widefat" value="5" min="1">
				</div>
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Rate / Base ($):', 'ur-payment-tester' ); ?></label>
					<input type="number" step="0.01" name="rate" class="widefat" value="15.00">
				</div>
				<button type="submit" class="button button-secondary widefat">
					<?php esc_html_e( 'Calculate Team Price & Seats', 'ur-payment-tester' ); ?>
				</button>
			</form>
			<div class="urpt-addon-result"></div>
		</div>
	</div>

	<!-- 3. Upgrades Proration Driver -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h3><span class="dashicons dashicons-arrow-up-alt"></span> <?php esc_html_e( 'Upgrades Proration Driver', 'ur-payment-tester' ); ?></h3>
		</div>
		<div class="urpt-card-body">
			<form class="urpt-addon-calc-form" data-addon="upgrade">
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Current Plan Price ($/mo):', 'ur-payment-tester' ); ?></label>
					<input type="number" step="0.01" name="curr_amount" class="widefat" value="10.00">
				</div>
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'New Plan Price ($/mo):', 'ur-payment-tester' ); ?></label>
					<input type="number" step="0.01" name="new_amount" class="widefat" value="30.00">
				</div>
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Days Passed in 30-day Cycle:', 'ur-payment-tester' ); ?></label>
					<input type="number" name="days_passed" class="widefat" value="15" min="0" max="30">
				</div>
				<button type="submit" class="button button-secondary widefat">
					<?php esc_html_e( 'Calculate Prorated Delta', 'ur-payment-tester' ); ?>
				</button>
			</form>
			<div class="urpt-addon-result"></div>
		</div>
	</div>
</div>

<div class="urpt-grid urpt-grid-3">
	<!-- 4. Taxes & Local Currency Driver -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h3><span class="dashicons dashicons-calculator"></span> <?php esc_html_e( 'Taxes & Multi-Currency', 'ur-payment-tester' ); ?></h3>
		</div>
		<div class="urpt-card-body">
			<form class="urpt-addon-calc-form" data-addon="currency">
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Subtotal ($):', 'ur-payment-tester' ); ?></label>
					<input type="number" step="0.01" name="subtotal" class="widefat" value="50.00">
				</div>
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Tax Rate (%):', 'ur-payment-tester' ); ?></label>
					<input type="number" step="0.1" name="tax_rate" class="widefat" value="10.0">
				</div>
				<div class="urpt-form-group">
					<label><strong><?php esc_html_e( 'Tax Mode:', 'ur-payment-tester' ); ?></strong></label>
					<select name="tax_mode" class="widefat">
						<option value="exclusive"><?php esc_html_e( 'Exclusive (Added On Top)', 'ur-payment-tester' ); ?></option>
						<option value="inclusive"><?php esc_html_e( 'Inclusive (Included in Price)', 'ur-payment-tester' ); ?></option>
					</select>
				</div>
				<div class="urpt-form-group">
					<label><strong><?php esc_html_e( 'Target Local Currency:', 'ur-payment-tester' ); ?></strong></label>
					<select name="target_currency" class="widefat">
						<option value="EUR">EUR (€) - FX: 0.92</option>
						<option value="GBP">GBP (£) - FX: 0.79</option>
						<option value="CAD">CAD ($) - FX: 1.35</option>
						<option value="AUD">AUD ($) - FX: 1.52</option>
						<option value="JPY">JPY (¥ - Zero-Decimal) - FX: 155.0</option>
						<option value="INR">INR (₹) - FX: 83.5</option>
						<option value="BRL">BRL (R$) - FX: 5.40</option>
					</select>
				</div>
				<button type="submit" class="button button-secondary widefat">
					<?php esc_html_e( 'Calculate Tax & FX Conversion', 'ur-payment-tester' ); ?>
				</button>
			</form>
			<div class="urpt-addon-result"></div>
		</div>
	</div>

	<!-- 5. PDF Invoices Driver -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h3><span class="dashicons dashicons-media-document"></span> <?php esc_html_e( 'PDF Invoices Driver', 'ur-payment-tester' ); ?></h3>
		</div>
		<div class="urpt-card-body">
			<form class="urpt-addon-calc-form" data-addon="invoice">
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Target Order # for Invoice:', 'ur-payment-tester' ); ?></label>
					<input type="number" name="order_id" class="widefat" value="101" min="1">
				</div>
				<p class="description">
					<?php esc_html_e( 'Generates sequential INV-{year}-{id}, stores token meta, and validates download token.', 'ur-payment-tester' ); ?>
				</p>
				<button type="submit" class="button button-secondary widefat">
					<?php esc_html_e( 'Simulate Invoice Generation', 'ur-payment-tester' ); ?>
				</button>
			</form>
			<div class="urpt-addon-result"></div>
		</div>
	</div>

	<!-- 6. Content Restriction Gatekeeper -->
	<div class="urpt-card">
		<div class="urpt-card-header">
			<h3><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Content Restriction Gatekeeper', 'ur-payment-tester' ); ?></h3>
		</div>
		<div class="urpt-card-body">
			<form class="urpt-addon-calc-form" data-addon="content">
				<div class="urpt-form-group">
					<label><?php esc_html_e( 'Target Subscription #:', 'ur-payment-tester' ); ?></label>
					<input type="number" name="subscription_id" class="widefat" value="1" min="1">
				</div>
				<p class="description">
					<?php esc_html_e( 'Verifies active member access unlock, then simulates expiration and asserts immediate content lockout.', 'ur-payment-tester' ); ?>
				</p>
				<button type="submit" class="button button-secondary widefat">
					<?php esc_html_e( 'Test Access & Expiration Lockout', 'ur-payment-tester' ); ?>
				</button>
			</form>
			<div class="urpt-addon-result"></div>
		</div>
	</div>
</div>
