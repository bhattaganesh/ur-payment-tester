/**
 * UR Payment Testing & Simulation Suite - Admin JS
 *
 * @package UR_Payment_Tester
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// 1. Tab Switching
		$('.urpt-nav-tabs .nav-tab').on('click', function(e) {
			e.preventDefault();
			var targetTab = $(this).data('tab');

			$('.urpt-nav-tabs .nav-tab').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');

			$('.urpt-tab-pane').removeClass('urpt-tab-pane-active');
			$('#' + targetTab).addClass('urpt-tab-pane-active');

			window.location.hash = targetTab;
		});

		// Activate tab from URL hash if present
		if (window.location.hash) {
			var hash = window.location.hash.replace('#', '');
			var activeLink = $('.urpt-nav-tabs .nav-tab[data-tab="' + hash + '"]');
			if (activeLink.length) {
				activeLink.trigger('click');
			}
		}

		// 2. Gateway Dropdown Toggle
		$('#urpt-wh-gateway').on('change', function() {
			var gateway = $(this).val();
			$('#urpt-stripe-events, #urpt-paypal-events, #urpt-authorize-events, #urpt-mollie-events').hide();

			if (gateway === 'stripe') {
				$('#urpt-stripe-events').show();
				$('#urpt-wh-event').val('invoice.payment_succeeded');
			} else if (gateway === 'paypal') {
				$('#urpt-paypal-events').show();
				$('#urpt-wh-event').val('PAYMENT.CAPTURE.COMPLETED');
			} else if (gateway === 'authorize') {
				$('#urpt-authorize-events').show();
				$('#urpt-wh-event').val('net.authorize.payment.authcapture.created');
			} else if (gateway === 'mollie') {
				$('#urpt-mollie-events').show();
				$('#urpt-wh-event').val('payment.paid');
			}
		});

		// 3. Logger Helper
		function logToConsole(title, isPass, details) {
			var $log = $('#urpt-audit-log');
			$log.find('.urpt-log-empty').remove();

			var timeStr = new Date().toLocaleTimeString();
			var statusTag = isPass ? '<span class="urpt-log-pass">[PASS]</span>' : '<span class="urpt-log-fail">[FAIL]</span>';

			var html = '<div class="urpt-log-entry">';
			html += '<span class="urpt-log-time">' + timeStr + '</span> ' + statusTag + ' <strong>' + title + '</strong>';

			if (details) {
				var jsonStr = typeof details === 'object' ? JSON.stringify(details, null, 2) : details;
				html += '<pre class="urpt-log-json">' + $('<div>').text(jsonStr).html() + '</pre>';
			}

			html += '</div>';
			$log.append(html);
			$log.scrollTop($log[0].scrollHeight);
		}

		$('#urpt-clear-console').on('click', function() {
			$('#urpt-audit-log').html('<div class="urpt-log-empty">Console cleared. Ready for next action.</div>');
		});

		// 4. Time-Travel: Shift Dates
		$('.urpt-action-btn[data-action="shift_dates"]').on('click', function() {
			var $btn = $(this);
			var subId = $('#urpt-sub-picker').val();
			var days = $btn.data('days');

			$btn.prop('disabled', true);
			logToConsole('Time-Travel: Shifting subscription #' + subId + ' by ' + days + ' days...', true);

			$.post(urpt_ajax.ajax_url, {
				action: 'urpt_shift_dates',
				nonce: urpt_ajax.nonce,
				subscription_id: subId,
				days: days
			}).done(function(res) {
				if (res.success) {
					logToConsole('Time-Travel Completed: New Billing Date -> ' + res.data.new_billing_date, true, res.data);
				} else {
					logToConsole('Time-Travel Failed: ' + (res.data ? res.data.message : 'Unknown error'), false, res.data);
				}
			}).fail(function(xhr) {
				logToConsole('Time-Travel Server Error', false, xhr.responseText);
			}).always(function() {
				$btn.prop('disabled', false);
			});
		});

		// 5. Run Single Cron Hook
		$('.urpt-cron-btn').on('click', function() {
			var $btn = $(this);
			var hook = $btn.data('cron');

			$btn.prop('disabled', true);
			logToConsole('Triggering native cron action: ' + hook + '...', true);

			$.post(urpt_ajax.ajax_url, {
				action: 'urpt_run_crons',
				nonce: urpt_ajax.nonce,
				crons: [hook]
			}).done(function(res) {
				if (res.success) {
					logToConsole('Cron Action ' + hook + ' executed successfully.', true, res.data.cron_log);
				} else {
					logToConsole('Cron Action failed.', false, res.data);
				}
			}).always(function() {
				$btn.prop('disabled', false);
			});
		});

		// 6. Run All Crons
		$('.urpt-action-btn[data-action="run_all_crons"]').on('click', function() {
			var $btn = $(this);
			$btn.prop('disabled', true);
			logToConsole('Triggering all 5 URM native crons sequentially...', true);

			$.post(urpt_ajax.ajax_url, {
				action: 'urpt_run_crons',
				nonce: urpt_ajax.nonce
			}).done(function(res) {
				if (res.success) {
					logToConsole('All Crons executed successfully.', true, res.data.cron_log);
				}
			}).always(function() {
				$btn.prop('disabled', false);
			});
		});

		// 7. Quick Webhook Buttons
		$('.urpt-webhook-quick-btn').on('click', function() {
			var $btn = $(this);
			var gateway = $btn.data('gateway');
			var eventName = $btn.data('event');
			var subId = $('#urpt-sub-picker').val();

			$btn.prop('disabled', true);
			logToConsole('Synthesizing quick webhook (' + eventName + ')...', true);

			$.post(urpt_ajax.ajax_url, {
				action: 'urpt_dispatch_webhook',
				nonce: urpt_ajax.nonce,
				gateway: gateway,
				event: eventName,
				subscription_id: subId
			}).done(function(res) {
				if (res.success && res.data.status_code === 200) {
					logToConsole('Webhook ' + eventName + ' verified & processed (HTTP 200, ' + res.data.duration_ms + 'ms)', true, res.data);
				} else {
					logToConsole('Webhook response: HTTP ' + (res.data ? res.data.status_code : 'Error'), false, res.data);
				}
			}).always(function() {
				$btn.prop('disabled', false);
			});
		});

		// 8. Webhook Synthesizer Form Submit
		$('#urpt-webhook-form').on('submit', function(e) {
			e.preventDefault();
			var $btn = $('#urpt-wh-dispatch-btn');
			var gateway = $('#urpt-wh-gateway').val();
			var eventName = $('#urpt-wh-event').val();
			var subId = $('#urpt-wh-sub-id').val();
			var amount = $('#urpt-wh-amount').val();

			$btn.prop('disabled', true);
			logToConsole('Dispatching ' + gateway.toUpperCase() + ' webhook: ' + eventName + ' to REST endpoint...', true);

			$.post(urpt_ajax.ajax_url, {
				action: 'urpt_dispatch_webhook',
				nonce: urpt_ajax.nonce,
				gateway: gateway,
				event: eventName,
				subscription_id: subId,
				amount: amount
			}).done(function(res) {
				if (res.success && res.data.status_code === 200) {
					logToConsole('Signed Webhook Delivered Successfully: ' + eventName + ' (HTTP 200 in ' + res.data.duration_ms + 'ms)', true, res.data);
				} else {
					logToConsole('Webhook delivery rejected or error: HTTP ' + (res.data ? res.data.status_code : 'Error'), false, res.data);
				}
			}).always(function() {
				$btn.prop('disabled', false);
			});
		});

		// 9. Run Scenario Recipes
		$('.urpt-run-scenario-btn').on('click', function() {
			var $btn = $(this);
			var scId = $btn.data('scenario');

			$btn.prop('disabled', true).text('Running Recipe...');
			logToConsole('Starting Scenario #' + scId + ' execution...', true);

			$.post(urpt_ajax.ajax_url, {
				action: 'urpt_run_scenario',
				nonce: urpt_ajax.nonce,
				scenario_id: scId
			}).done(function(res) {
				if (res.success) {
					var data = res.data;
					logToConsole(data.title + ' Completed (' + (data.all_passed ? 'ALL ASSERTIONS PASSED' : 'SOME CHECKS FAILED') + ')', data.all_passed, data);
				} else {
					logToConsole('Scenario execution failed: ' + (res.data ? res.data.message : 'Unknown error'), false, res.data);
				}
			}).fail(function(xhr) {
				logToConsole('Scenario execution server error', false, xhr.responseText);
			}).always(function() {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Run Recipe');
			});
		});

		// 10. Direct Bank Approval
		$('.urpt-approve-bank-btn').on('click', function() {
			var $btn = $(this);
			var orderId = $btn.data('order');

			$btn.prop('disabled', true);
			logToConsole('Admin approving Bank Transfer Order #' + orderId + '...', true);

			$.post(urpt_ajax.ajax_url, {
				action: 'urpt_approve_bank',
				nonce: urpt_ajax.nonce,
				order_id: orderId
			}).done(function(res) {
				if (res.success) {
					logToConsole('Bank Order #' + orderId + ' Approved! Subscription Active & User Login Gate Released.', true, res.data);
					$btn.closest('tr').fadeOut(400, function() { $(this).remove(); });
				} else {
					logToConsole('Bank Approval failed: ' + (res.data ? res.data.message : 'Unknown error'), false, res.data);
					$btn.prop('disabled', false);
				}
			});
		});

		// 11. Addon Drivers Calculation
		$('.urpt-addon-calc-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var addon = $form.data('addon');
			var formData = $form.serializeArray();

			var postData = {
				action: 'urpt_test_addon',
				nonce: urpt_ajax.nonce,
				addon: addon
			};

			$.each(formData, function(i, field) {
				postData[field.name] = field.value;
			});

			var $resultBox = $form.siblings('.urpt-addon-result');
			$resultBox.html('Calculating...');

			$.post(urpt_ajax.ajax_url, postData).done(function(res) {
				if (res.success) {
					$resultBox.html('<pre class="urpt-log-json" style="background:#f6f7f7; color:#1d2327;">' + JSON.stringify(res.data, null, 2) + '</pre>');
					logToConsole('Addon Driver (' + addon + ') calculation verified.', true, res.data);
				}
			});
		});

		// 12. Settings Form Submit
		$('#urpt-settings-form').on('submit', function(e) {
			e.preventDefault();
			var formData = $(this).serializeArray();
			var postData = {
				action: 'urpt_save_settings',
				nonce: urpt_ajax.nonce
			};

			$.each(formData, function(i, field) {
				postData[field.name] = field.value;
			});

			// Ensure unchecked mail_trap checkbox explicitly sends 'no'.
			if (!postData.mail_trap) {
				postData.mail_trap = 'no';
			}

			$.post(urpt_ajax.ajax_url, postData).done(function(res) {
				if (res.success) {
					logToConsole('Settings saved successfully.', true, res.data);
					$('#urpt-active-mode-pill').html('Mode: <strong>' + res.data.mode.toUpperCase() + '</strong>');
					alert('Settings updated successfully.');
				}
			});
		});

		// 13. Purge Test Data
		$('#urpt-purge-btn').on('click', function() {
			if (!confirm(urpt_ajax.strings.confirm)) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true);
			logToConsole('Purging all simulated test orders, subscriptions, events, and test users...', true);

			$.post(urpt_ajax.ajax_url, {
				action: 'urpt_purge_data',
				nonce: urpt_ajax.nonce
			}).done(function(res) {
				if (res.success) {
					logToConsole('Purge completed cleanly: ' + res.data.orders + ' orders, ' + res.data.subscriptions + ' subscriptions, ' + res.data.events + ' events, ' + res.data.users + ' test users deleted.', true, res.data);
					alert('Test data purged cleanly.');
				}
			}).always(function() {
				$btn.prop('disabled', false);
			});
		});

		// 14. Clear Mailbox
		$('#urpt-clear-mailbox-btn').on('click', function() {
			$.post(urpt_ajax.ajax_url, {
				action: 'urpt_clear_mailbox',
				nonce: urpt_ajax.nonce
			}).done(function(res) {
				if (res.success) {
					$('.urpt-table-mailbox tbody').empty();
					$('#urpt-mail-count').text('0');
					logToConsole('Local mail trap cleared.', true);
				}
			});
		});

		// 15. View Mail Modal
		$('.urpt-view-mail-btn').on('click', function() {
			var mailData = $(this).data('mail');
			if (!mailData) return;

			$('#urpt-modal-subject').text(mailData.subject || '(No Subject)');
			$('#urpt-modal-to').text(mailData.to || '');
			$('#urpt-modal-time').text(mailData.timestamp || '');
			$('#urpt-modal-body').html(mailData.body || '');

			$('#urpt-mail-modal').fadeIn(200);
		});

		$('.urpt-modal-close, #urpt-mail-modal').on('click', function(e) {
			if (e.target === this) {
				$('#urpt-mail-modal').fadeOut(200);
			}
		});
	});
})(jQuery);
