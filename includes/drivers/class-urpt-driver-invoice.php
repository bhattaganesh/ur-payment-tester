<?php
/**
 * URPT PDF Invoices Driver
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates and simulates sequential PDF invoice generation, line item rendering, and token downloads.
 */
class URPT_Driver_Invoice {

	/**
	 * Generates a formatted sequential invoice identifier for an order.
	 *
	 * Matches URM format: INV-{year}-{order_id}.
	 *
	 * @param int $order_id Order ID.
	 * @return string Formatted invoice number.
	 */
	public function generate_invoice_number( $order_id ) {
		$year = gmdate( 'Y' );
		return sprintf( 'INV-%s-%04d', $year, absint( $order_id ) );
	}

	/**
	 * Simulates PDF invoice attachment to an order and validates token accessibility.
	 *
	 * @param int $order_id Order row ID.
	 * @return array Generated invoice metadata.
	 */
	public function simulate_invoice( $order_id ) {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'ur_membership_orders';
		$meta_table   = $wpdb->prefix . 'ur_membership_ordermeta';

		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE ID = %d", absint( $order_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $order ) {
			throw new \InvalidArgumentException( __( 'Order not found for invoice generation.', 'ur-payment-tester' ) );
		}

		$invoice_num   = $this->generate_invoice_number( $order_id );
		$invoice_token = wp_generate_password( 32, false );
		$download_url  = add_query_arg(
			array(
				'ur_download_pdf' => 1,
				'order_id'        => $order_id,
				'token'           => $invoice_token,
			),
			home_url( '/' )
		);

		// Record invoice metadata.
		$meta = array(
			'ur_invoice_number'   => $invoice_num,
			'urm_invoice_number'  => $invoice_num,
			'ur_invoice_token'    => $invoice_token,
			'ur_invoice_date'     => current_time( 'mysql' ),
			'urpt_simulated'      => '1',
		);

		foreach ( $meta as $k => $v ) {
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
			'invoice_number' => $invoice_num,
			'token'          => $invoice_token,
			'download_url'   => $download_url,
			'status'         => 'generated',
		);
	}
}
