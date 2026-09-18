<?php
/**
 * URPT Taxes & Local Currency Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Simulates inclusive/exclusive tax calculations and multi-currency exchange rate conversions.
 */
class URPT_Driver_Currency {

	/**
	 * Calculates tax amounts for inclusive or exclusive configurations.
	 *
	 * @param float  $subtotal Order base subtotal amount.
	 * @param float  $rate Tax rate percentage (e.g. 10 for 10%).
	 * @param string $mode Tax mode ('inclusive' or 'exclusive').
	 * @return array Breakdown of subtotal, tax amount, and total.
	 */
	public function calculate_tax( $subtotal, $rate, $mode = 'exclusive' ) {
		$rate_decimal = $rate / 100;

		if ( 'inclusive' === $mode ) {
			// Tax already included within base price.
			$pre_tax_subtotal = round( $subtotal / ( 1 + $rate_decimal ), 2 );
			$tax_amount       = round( $subtotal - $pre_tax_subtotal, 2 );
			$total            = $subtotal;
		} else {
			// Tax added on top of subtotal.
			$tax_amount       = round( $subtotal * $rate_decimal, 2 );
			$pre_tax_subtotal = $subtotal;
			$total            = round( $subtotal + $tax_amount, 2 );
		}

		return array(
			'mode'             => $mode,
			'tax_rate_percent' => (float) $rate,
			'pre_tax_subtotal' => $pre_tax_subtotal,
			'tax_amount'       => $tax_amount,
			'total_amount'     => $total,
		);
	}

	/**
	 * Simulates foreign exchange rate conversion to local currency.
	 *
	 * @param float  $amount Base currency amount.
	 * @param float  $exchange_rate Target exchange rate multiplier.
	 * @param string $target_currency Target 3-letter currency code (e.g. 'EUR', 'GBP').
	 * @return array Converted pricing details.
	 */
	public function convert_to_local_currency( $amount, $exchange_rate, $target_currency = 'EUR' ) {
		$converted = round( $amount * $exchange_rate, 2 );

		return array(
			'base_amount'     => (float) $amount,
			'base_currency'   => 'USD',
			'exchange_rate'   => (float) $exchange_rate,
			'target_currency' => strtoupper( $target_currency ),
			'local_amount'    => $converted,
		);
	}

	/**
	 * Simulates staging an order with comprehensive tax breakdown and local currency meta.
	 *
	 * @param int    $plan_id Membership post ID.
	 * @param int    $user_id User ID.
	 * @param float  $base_amount Order base subtotal.
	 * @param float  $tax_rate Tax rate percent.
	 * @param string $tax_mode 'inclusive' or 'exclusive'.
	 * @param string $currency Currency code.
	 * @param float  $fx_rate Currency exchange rate multiplier.
	 * @return array Staged order data.
	 */
	public function simulate_order_with_tax_and_currency( $plan_id, $user_id, $base_amount = 50.00, $tax_rate = 10.0, $tax_mode = 'exclusive', $currency = 'EUR', $fx_rate = 0.92 ) {
		global $wpdb;

		$tax_calc = $this->calculate_tax( $base_amount, $tax_rate, $tax_mode );
		$fx_calc  = $this->convert_to_local_currency( $tax_calc['total_amount'], $fx_rate, $currency );

		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$meta_table   = $wpdb->prefix . 'ur_membership_ordermeta';

		$wpdb->insert(
			$orders_table,
			array(
				'item_id'         => $plan_id,
				'user_id'         => $user_id,
				'subscription_id' => 0,
				'created_by'      => $user_id,
				'transaction_id'  => 'tax_fx_' . time(),
				'payment_method'  => 'stripe',
				'total_amount'    => $tax_calc['total_amount'],
				'status'          => 'completed',
				'order_type'      => 'paid',
				'trial_status'    => 'off',
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		$order_id = $wpdb->insert_id;

		// Record financial breakdown metadata.
		$metadata = array(
			'urpt_simulated'    => '1',
			'tax_mode'          => $tax_mode,
			'tax_rate'          => (string) $tax_rate,
			'tax_amount'        => (string) $tax_calc['tax_amount'],
			'subtotal'          => (string) $tax_calc['pre_tax_subtotal'],
			'local_currency'    => $currency,
			'exchange_rate'     => (string) $fx_rate,
			'local_currency_amt'=> (string) $fx_calc['local_amount'],
		);

		foreach ( $metadata as $k => $v ) {
			$wpdb->insert(
				$meta_table,
				array(
					'order_id'   => $order_id,
					'meta_key'   => $k,
					'meta_value' => $v,
				),
				array( '%d', '%s', '%s' )
			);
		}

		return array(
			'order_id'       => $order_id,
			'tax_breakdown'  => $tax_calc,
			'currency_fx'    => $fx_calc,
			'recorded_meta'  => $metadata,
		);
	}
}
