# UR Payment Testing & Simulation Suite (`ur-payment-tester`)

An enterprise-grade, standalone WordPress companion plugin engineered to test, simulate, and validate all payment gateways, recurring lifecycles, cryptographic webhooks, and commercial addons in **User Registration Pro** and its **Membership module**.

Deployable on **TasteWP**, staging, LocalWP, and live testing environments with **zero modifications to production code**.

---

## Supported Payment Gateways & Addons Matrix

| Gateway / Addon | Simulation Method | Credentials Required | Recurring / ARB | Webhook / IPN Simulation |
| :--- | :--- | :---: | :---: | :--- |
| **Stripe** | In-memory `URPT_Stripe_Mock_Client` & Elements mock | **None (0ms)** | Yes (Sub Schedules) | `Stripe-Signature` HMAC-SHA256 REST |
| **PayPal REST** | Outgoing HTTP interceptor (`api.sandbox.paypal.com`) | **None (0ms)** | Yes (v1 Subscriptions) | REST Transmission Signature & Cert mock |
| **Direct Bank Transfer** | Offline wire quarantine & 1-click admin approval | **None** | No | Manual verification & approval |
| **Authorize.Net** | API interceptor (`api.authorize.net`) AIM & ARB | **None (0ms)** | Yes (ARB Subscriptions) | Direct Approved Response |
| **Mollie** | API interceptor (`api.mollie.com`) Payments & Mandates | **None (0ms)** | Yes (SEPA / Recurring) | Webhook callback & paid state retrieval |
| **Coupons & UR-4386** | 100% discount delayed schedule validation | **None** | Yes | Prevents 0-amount gateway errors |
| **Multi-Currency** | Real-time FX conversion engine | **None** | Yes | USD, EUR, GBP, CAD, AUD, JPY, INR |
| **Inclusive/Exclusive Tax** | Dynamic tax calculation & breakdown | **None** | Yes | Region-based tax rate assertion |
| **Team Membership** | Fixed, per-seat, and tiered allocation tests | **None** | Yes | Seat allocation & validation |
| **Membership Upgrades** | Proration calculation (`price_per_day * days`) | **None** | Yes | Order upgrade & credit transition |
| **PDF Invoices** | Sequential `INV-{year}-{id}` generator | **None** | Yes | Tokenized download URL |

---

## How to Use This Plugin for Testing

### 1. Zero-Credentials Simulation Mode (Default)
When activated, `ur-payment-tester` automatically operates in **Simulated Mode**. All outgoing HTTP requests to Stripe, PayPal, Authorize.Net, and Mollie are intercepted locally with 0ms latency. You **do not need API keys, merchant accounts, or webhooks configured in Stripe/PayPal dashboards**.

### 2. Testing via the WordPress Admin Test Bench
1. In your WordPress Admin, navigate to **User Registration > Payment Tester**.
2. **Dashboard Overview**:
   * View live statistics of active simulated subscriptions, orders, intercepted webhooks, and trapped emails.
   * Switch between **Simulated Mode** and **Live-Sandbox Mode**.
3. **Scenario Recipes Tab**:
   * **Scenario 1 (Happy Path 3-Cycle Subscription)**: Tests initial checkout and fast-forwards 2 renewal cycles (30 days + 30 days) with `invoice.payment_succeeded` webhooks.
   * **Scenario 2 (Payment Failure & Retries)**: Injects `invoice.payment_failed` webhooks and triggers retry crons.
   * **Scenario 3 (Immediate Cancellation)**: Tests buyer/admin subscription cancellation and status revocation.
   * **Scenario 4 (Prorated Upgrade)**: Tests upgrading between tiers with exact daily proration credit calculations.
   * **Scenario 5 (100% Off Coupon - UR-4386)**: Tests zero-amount initial checkout with delayed recurring subscription schedules.
   * **Scenario 6 (Bank Transfer Quarantine)**: Tests account quarantine on signup and instant release upon admin approval.
4. **Webhooks Simulator Tab**:
   * Select a gateway (**Stripe** or **PayPal**).
   * Choose an event (`invoice.payment_succeeded`, `invoice.payment_failed`, `customer.subscription.deleted`, `charge.refunded`, `PAYMENT.CAPTURE.COMPLETED`, `BILLING.SUBSCRIPTION.ACTIVATED`).
   * Enter an existing Subscription ID or Order ID, and click **Dispatch Webhook**.
   * Inspect the response code, latency, and JSON payload in real time.
5. **Addon Drivers Tab**:
   * **Upgrades**: Run proration calculations across custom plan prices and elapsed days.
   * **Team Seats**: Test fixed seats, per-seat tiers, and max seat limits.
   * **Multi-Currency & Taxes**: Test FX conversion rates and inclusive/exclusive taxes.
   * **PDF Invoices**: Generate and verify sequential PDF invoice numbers and download tokens.
6. **Local Mailbox Tab**:
   * All emails sent during testing (welcome emails, invoice attachments, payment failures) are caught safely without sending external emails.
   * Inspect HTML bodies, recipients, and attachments directly.
7. **Clean Purge**:
   * Click **Purge All Simulated Data** at any time to wipe only test orders, subscriptions, users, and logs while leaving real data untouched.

---

## Testing via Headless WP-CLI

The plugin provides first-class WP-CLI integration for automated CI/CD pipelines and headless terminal testing:

```bash
# 1. Run Pre-Packaged End-to-End Scenarios (1 through 6)
wp urpt scenario 1
wp urpt scenario 2
wp urpt scenario 3
wp urpt scenario 4
wp urpt scenario 5
wp urpt scenario 6

# 2. Fast-Forward / Time-Travel Subscriptions
# Advance subscription #15 by 30 days and advance billing dates
wp urpt time-travel --subscription=15 --days=30

# Rewind subscription #15 by 7 days
wp urpt time-travel --subscription=15 --days=-7

# 3. Dispatch Authentic Signed Webhooks
# Stripe renewal webhook
wp urpt webhook --gateway=stripe --event=invoice.payment_succeeded --subscription=15

# Stripe payment failure webhook
wp urpt webhook --gateway=stripe --event=invoice.payment_failed --subscription=15

# PayPal order capture webhook
wp urpt webhook --gateway=paypal --event=PAYMENT.CAPTURE.COMPLETED

# 4. Safe Data Purge
wp urpt purge
```

---

## Installation & TasteWP Deployment

1. Download `ur-payment-tester.zip`.
2. On any WordPress site (TasteWP, InstaWP, LocalWP, or Staging):
   * Go to **Plugins > Add New > Upload Plugin**.
   * Upload `ur-payment-tester.zip` and click **Activate**.
3. The plugin will immediately enable simulation for all gateways and addons.

---

## Author & License

* **Author**: Ganesh Bhatta ([@bhattaganesh](https://github.com/bhattaganesh))
* **License**: GPL-2.0+
