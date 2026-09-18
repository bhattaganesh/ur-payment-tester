=== UR Payment Testing & Simulation Suite ===
Contributors: bhattaganesh
Donate link: https://github.com/bhattaganesh
Tags: payments, testing, stripe, paypal, user registration
Requires at least: 5.8
Tested up to: 7.1.1
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
* Full support for Stripe, PayPal REST, Authorize.Net, Mollie, and Direct Bank Transfer.
* Full support for commercial addons: Coupons, Taxes, Multi-Currency, Team Membership, Upgrades, PDF Invoices, and Content Restriction.
* Time-Travel date shifter and programmatic URM cron dispatcher.
* Authentic HMAC-signed webhook synthesizer.
* Automated 6-point state assertion engine with live browser audit console.
* Local mail trap visual mailbox.
* Headless WP-CLI support for CI/CD automation.

== Installation ==
1. Upload `ur-payment-tester` folder to the `/wp-content/plugins/` directory, or upload the `.zip` archive via Plugins > Add New > Upload Plugin.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access the suite under 'User Registration > UR Payment Tester' or 'WP Admin > UR Payment Tester'.

== Frequently Asked Questions ==

= Do I need real Stripe or PayPal sandbox credentials? =
No. In Simulated Mode (default), all outgoing API requests are intercepted locally. Checkout forms and webhooks work out of the box with zero API credentials.

= How does a QA engineer test without WP-CLI? =
The plugin includes a full browser UI under 'UR Payment Tester'. You can run automated 1-click recipes in 'Scenario Recipes', shift dates in 'Test Bench', dispatch webhooks in 'Webhook Synthesizer', and inspect emails in 'Mailbox Trap'.

= How do I test a 6-month or 1-year subscription renewal without waiting? =
Use the 'Test Bench' tab. Pick your subscription, click 'Advance 30 Days & Renew', and click 'Renewal Check Cron'. The cycle will advance and generate renewal orders in 2 seconds.

= Will this modify or pollute real site data? =
No. All simulated orders, subscriptions, and users are isolated with `urpt_simulated` meta. Clicking 'Purge Test Data' deletes only simulated records, keeping genuine data safe.
