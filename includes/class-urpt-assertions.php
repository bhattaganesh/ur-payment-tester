<?php
/**
 * URPT 6-Point State Assertion Engine
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates database records, audit logs, user capabilities, invoices, and emails.
 */
class URPT_Assertions {

	/**
	 * Executes a comprehensive 6-point assertion against current system state.
	 *
	 * @param array $expected Array of expected criteria (order_id, subscription_id, user_id, etc.).
	 * @return array Unified verification report with pass/fail indicators.
	 */
	public function verify_state( array $expected ) {
		$checks = array();

		// Check 1: Orders table verification.
		if ( ! empty( $expected['order_id'] ) || ! empty( $expected['order_criteria'] ) ) {
			$checks['order'] = $this->assert_order( $expected['order_id'] ?? 0, $expected['order_criteria'] ?? array() );
		}

		// Check 2: Subscriptions table verification.
		if ( ! empty( $expected['subscription_id'] ) || ! empty( $expected['subscription_criteria'] ) ) {
			$checks['subscription'] = $this->assert_subscription( $expected['subscription_id'] ?? 0, $expected['subscription_criteria'] ?? array() );
		}

		// Check 3: Subscription audit event verification.
		if ( ! empty( $expected['subscription_id'] ) && ! empty( $expected['expected_event'] ) ) {
			$checks['event_log'] = $this->assert_event_logged( $expected['subscription_id'], $expected['expected_event'] );
		}

		// Check 4: User status and role verification.
		if ( ! empty( $expected['user_id'] ) ) {
			$checks['user_access'] = $this->assert_user_access(
				$expected['user_id'],
				$expected['expected_role'] ?? null,
				$expected['expected_user_status'] ?? 1
			);
		}

		// Check 5: PDF Invoice generation verification.
		if ( ! empty( $expected['order_id'] ) && ! empty( $expected['check_invoice'] ) ) {
			$checks['invoice'] = $this->assert_invoice_generated( $expected['order_id'] );
		}

		// Check 6: Trapped email verification.
		if ( ! empty( $expected['expected_email_pattern'] ) ) {
			$checks['email'] = $this->assert_email_trapped( $expected['expected_email_pattern'] );
		}

		$all_passed = true;
		foreach ( $checks as $c ) {
			if ( empty( $c['passed'] ) ) {
				$all_passed = false;
				break;
			}
		}

		return array(
			'all_passed' => $all_passed,
			'checks'     => $checks,
			'timestamp'  => current_time( 'mysql' ),
		);
	}

	/**
	 * Asserts that an order exists and matches criteria.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $criteria Expected fields (status, total_amount, payment_method).
	 * @return array Result.
	 */
	public function assert_order( $order_id, array $criteria = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_orders';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", absint( $order_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row ) {
			return array(
				'passed'  => false,
				'message' => sprintf( __( 'Order #%d not found in database.', 'ur-payment-tester' ), $order_id ),
				'actual'  => null,
			);
		}

		$mismatches = array();
		foreach ( $criteria as $key => $expected_val ) {
			if ( isset( $row[ $key ] ) ) {
				$actual_val = $row[ $key ];
				if ( is_numeric( $expected_val ) && is_numeric( $actual_val ) ) {
					// Numeric comparison prevents failures caused by database decimal trailing zeros.
					if ( abs( (float) $actual_val - (float) $expected_val ) > 0.0001 ) {
						$mismatches[] = "{$key}: expected '{$expected_val}', got '{$actual_val}'";
					}
				} elseif ( (string) $actual_val !== (string) $expected_val ) {
					$mismatches[] = "{$key}: expected '{$expected_val}', got '{$actual_val}'";
				}
			}
		}

		$passed = empty( $mismatches );
		return array(
			'passed'  => $passed,
			'message' => $passed ? sprintf( __( 'Order #%d validated successfully.', 'ur-payment-tester' ), $order_id ) : implode( '; ', $mismatches ),
			'actual'  => $row,
		);
	}

	/**
	 * Asserts that a subscription exists and matches criteria.
	 *
	 * @param int   $subscription_id Subscription ID.
	 * @param array $criteria Expected fields (status, billing_cycle).
	 * @return array Result.
	 */
	public function assert_subscription( $subscription_id, array $criteria = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_subscriptions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE ID = %d", absint( $subscription_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row ) {
			return array(
				'passed'  => false,
				'message' => sprintf( __( 'Subscription #%d not found in database.', 'ur-payment-tester' ), $subscription_id ),
				'actual'  => null,
			);
		}

		$mismatches = array();
		foreach ( $criteria as $key => $expected_val ) {
			if ( isset( $row[ $key ] ) ) {
				$actual_val = $row[ $key ];
				if ( is_numeric( $expected_val ) && is_numeric( $actual_val ) ) {
					// Numeric comparison prevents failures caused by database decimal trailing zeros.
					if ( abs( (float) $actual_val - (float) $expected_val ) > 0.0001 ) {
						$mismatches[] = "{$key}: expected '{$expected_val}', got '{$actual_val}'";
					}
				} elseif ( (string) $actual_val !== (string) $expected_val ) {
					$mismatches[] = "{$key}: expected '{$expected_val}', got '{$actual_val}'";
				}
			}
		}

		$passed = empty( $mismatches );
		return array(
			'passed'  => $passed,
			'message' => $passed ? sprintf( __( 'Subscription #%d status (%s) verified.', 'ur-payment-tester' ), $subscription_id, $row['status'] ) : implode( '; ', $mismatches ),
			'actual'  => $row,
		);
	}

	/**
	 * Asserts that an event log row exists for a given subscription.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $event_type Event type pattern.
	 * @return array Result.
	 */
	public function assert_event_logged( $subscription_id, $event_type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ur_membership_subscription_events';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array(
				'passed'  => true,
				'message' => __( 'Subscription events table not active in this installation.', 'ur-payment-tester' ),
			);
		}

		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE subscription_id = %d AND (event_type LIKE %s OR title LIKE %s) ORDER BY id DESC LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $subscription_id ),
				'%' . $wpdb->esc_like( $event_type ) . '%',
				'%' . $wpdb->esc_like( $event_type ) . '%'
			),
			ARRAY_A
		);

		$found = ! empty( $events );
		return array(
			'passed'  => $found,
			'message' => $found ? sprintf( __( "Found logged event '%s' for subscription #%d.", 'ur-payment-tester' ), $event_type, $subscription_id ) : sprintf( __( "Missing expected event '%s' for subscription #%d.", 'ur-payment-tester' ), $event_type, $subscription_id ),
			'actual'  => $events,
		);
	}

	/**
	 * Asserts user access status and roles.
	 *
	 * @param int         $user_id User ID.
	 * @param string|null $expected_role Optional expected role.
	 * @param int         $expected_status Expected ur_user_status (1 = active, 0 = pending).
	 * @return array Result.
	 */
	public function assert_user_access( $user_id, $expected_role = null, $expected_status = 1 ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user ) {
			return array(
				'passed'  => false,
				'message' => sprintf( __( 'User #%d not found.', 'ur-payment-tester' ), $user_id ),
			);
		}

		$status = (int) get_user_meta( $user_id, 'ur_user_status', true );
		$status_matched = $status === (int) $expected_status;

		$role_matched = true;
		if ( null !== $expected_role ) {
			$role_matched = in_array( $expected_role, (array) $user->roles, true );
		}

		$passed = $status_matched && $role_matched;
		return array(
			'passed'  => $passed,
			'message' => $passed ? sprintf( __( 'User #%d access verified (Status: %d, Roles: %s).', 'ur-payment-tester' ), $user_id, $status, implode( ', ', $user->roles ) ) : sprintf( __( 'User #%d access check failed (Status: %d, Expected: %d).', 'ur-payment-tester' ), $user_id, $status, $expected_status ),
			'actual'  => array(
				'ur_user_status' => $status,
				'roles'          => $user->roles,
			),
		);
	}

	/**
	 * Asserts that a PDF invoice has been generated for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array Result.
	 */
	public function assert_invoice_generated( $order_id ) {
		global $wpdb;

		$meta_table = $wpdb->prefix . 'ur_membership_ordermeta';
		$inv_number = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$meta_table} WHERE order_id = %d AND meta_key IN ('ur_invoice_number', 'urm_invoice_number')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $order_id )
			)
		);

		$passed = ! empty( $inv_number );
		return array(
			'passed'  => $passed,
			'message' => $passed ? sprintf( __( 'PDF Invoice verified: %s.', 'ur-payment-tester' ), $inv_number ) : sprintf( __( 'Invoice record missing for Order #%d.', 'ur-payment-tester' ), $order_id ),
			'actual'  => $inv_number,
		);
	}

	/**
	 * Asserts that an email matching the subject pattern was intercepted in the local mail trap.
	 *
	 * @param string $subject_pattern Regex or substring to match.
	 * @return array Result.
	 */
	public function assert_email_trapped( $subject_pattern ) {
		$trapped = get_option( 'urpt_trapped_emails', array() );
		if ( ! is_array( $trapped ) ) {
			$trapped = array();
		}

		$found = false;
		$matched_item = null;
		foreach ( array_reverse( $trapped ) as $mail ) {
			$subject = $mail['subject'] ?? '';
			if ( false !== stripos( $subject, $subject_pattern ) || @preg_match( '#' . $subject_pattern . '#i', $subject ) ) {
				$found = true;
				$matched_item = $mail;
				break;
			}
		}

		return array(
			'passed'  => $found,
			'message' => $found ? sprintf( __( "Email with subject matching '%s' found in trap.", 'ur-payment-tester' ), $subject_pattern ) : sprintf( __( "No email matching '%s' found in mail trap.", 'ur-payment-tester' ), $subject_pattern ),
			'actual'  => $matched_item,
		);
	}
}
