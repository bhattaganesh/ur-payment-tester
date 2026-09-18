=== UR Payment Testing & Simulation Suite ===
Contributors: bhattaganesh
Donate link: https://github.com/bhattaganesh
Tags: payments, testing, stripe, paypal, user registration
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enterprise companion testing suite for User Registration Pro payments, recurring subscriptions, and commercial addons.

== Description ==

UR Payment Testing & Simulation Suite (`ur-payment-tester`) is a standalone WordPress companion plugin engineered to test, simulate, and validate all payment gateways, recurring lifecycles, cryptographic webhooks, and commercial addons in User Registration Pro and its Membership module.

== Features ==
* Mode A: Zero-Credentials Simulation Mode (0ms latency, zero API keys required).
* Mode B: Hybrid Live-Sandbox Mode for real key checkouts.
* Full support for Stripe, PayPal REST, PayPal Standard IPN, and Direct Bank Transfer.
* Full support for commercial addons: Coupons, Taxes, Multi-Currency, Team Membership, Upgrades, PDF Invoices, and Content Restriction.
* Time-Travel date shifter and programmatic URM cron dispatcher.
* Authentic HMAC-signed webhook synthesizer.
* Automated 6-point state assertion engine.
* Local mail trap visual mailbox.
* Headless WP-CLI support.

== Installation ==
1. Upload `ur-payment-tester` folder to the `/wp-content/plugins/` directory, or upload the `.zip` archive via Plugins > Add New > Upload Plugin.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access the suite under 'User Registration > Payment Tester'.
