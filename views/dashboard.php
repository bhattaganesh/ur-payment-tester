<?php
/**
 * URPT Dashboard Main View
 *
 * @package UR_Payment_Tester
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap urpt-wrap">
	<header class="urpt-header">
		<div class="urpt-header-left">
			<h1>
				<span class="dashicons dashicons-money-alt"></span>
				<?php esc_html_e( 'UR Payment Testing & Simulation Suite', 'ur-payment-tester' ); ?>
				<span class="urpt-badge urpt-badge-version">v<?php echo esc_html( URPT_VERSION ); ?></span>
			</h1>
			<p class="urpt-subtitle">
				<?php esc_html_e( 'End-to-end payment gateway simulation, webhook synthesis, time-travel renewal engine, and addon validation.', 'ur-payment-tester' ); ?>
			</p>
		</div>
		<div class="urpt-header-right">
			<span class="urpt-pill urpt-pill-env">
				<span class="urpt-dot urpt-dot-green"></span>
				<?php esc_html_e( 'Environment: Safe Test Mode', 'ur-payment-tester' ); ?>
			</span>
			<span class="urpt-pill urpt-pill-mode" id="urpt-active-mode-pill">
				<?php printf( esc_html__( 'Mode: %s', 'ur-payment-tester' ), '<strong>' . esc_html( ucfirst( $current_mode ) ) . '</strong>' ); ?>
			</span>
		</div>
	</header>

	<nav class="nav-tab-wrapper urpt-nav-tabs">
		<a href="#tab-bench" class="nav-tab nav-tab-active" data-tab="tab-bench">
			<span class="dashicons dashicons-controls-forward"></span> <?php esc_html_e( 'Test Bench', 'ur-payment-tester' ); ?>
		</a>
		<a href="#tab-webhooks" class="nav-tab" data-tab="tab-webhooks">
			<span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'Webhook Synthesizer', 'ur-payment-tester' ); ?>
		</a>
		<a href="#tab-scenarios" class="nav-tab" data-tab="tab-scenarios">
			<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Scenario Recipes', 'ur-payment-tester' ); ?>
		</a>
		<a href="#tab-addons" class="nav-tab" data-tab="tab-addons">
			<span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Addon Drivers', 'ur-payment-tester' ); ?>
		</a>
		<a href="#tab-mailbox" class="nav-tab" data-tab="tab-mailbox">
			<span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Mailbox Trap', 'ur-payment-tester' ); ?>
			<span class="urpt-badge urpt-badge-count" id="urpt-mail-count"><?php echo esc_html( count( $trapped_emails ) ); ?></span>
		</a>
		<a href="#tab-settings" class="nav-tab" data-tab="tab-settings">
			<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings & Purge', 'ur-payment-tester' ); ?>
		</a>
	</nav>

	<main class="urpt-tab-content">
		<div id="tab-bench" class="urpt-tab-pane urpt-tab-pane-active">
			<?php require URPT_PLUGIN_DIR . 'views/partials/tab-bench.php'; ?>
		</div>

		<div id="tab-webhooks" class="urpt-tab-pane">
			<?php require URPT_PLUGIN_DIR . 'views/partials/tab-webhooks.php'; ?>
		</div>

		<div id="tab-scenarios" class="urpt-tab-pane">
			<?php require URPT_PLUGIN_DIR . 'views/partials/tab-scenarios.php'; ?>
		</div>

		<div id="tab-addons" class="urpt-tab-pane">
			<?php require URPT_PLUGIN_DIR . 'views/partials/tab-addons.php'; ?>
		</div>

		<div id="tab-mailbox" class="urpt-tab-pane">
			<?php require URPT_PLUGIN_DIR . 'views/partials/tab-mailbox.php'; ?>
		</div>

		<div id="tab-settings" class="urpt-tab-pane">
			<?php require URPT_PLUGIN_DIR . 'views/partials/tab-settings.php'; ?>
		</div>
	</main>

	<!-- Live Audit Console -->
	<section class="urpt-audit-console">
		<div class="urpt-audit-header">
			<h3>
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Real-Time Audit Console', 'ur-payment-tester' ); ?>
			</h3>
			<button type="button" class="button button-small" id="urpt-clear-console">
				<?php esc_html_e( 'Clear Console', 'ur-payment-tester' ); ?>
			</button>
		</div>
		<div class="urpt-audit-body" id="urpt-audit-log">
			<div class="urpt-log-empty">
				<?php esc_html_e( 'Ready. Execute a scenario, time-travel shift, or webhook dispatch above to stream results.', 'ur-payment-tester' ); ?>
			</div>
		</div>
	</section>
</div>
