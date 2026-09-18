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
| **Authorize.Net** | API interceptor (`api.authorize.net`) AIM & ARB | **None (0ms)** | Yes (ARB Subscriptions) | Webhook simulation & silent post dispatch |
| **Mollie** | API interceptor (`api.mollie.com`) Payments & Mandates | **None (0ms)** | Yes (SEPA / Recurring) | Webhook callback & paid state retrieval |
| **Direct Bank Transfer** | Offline wire quarantine & 1-click admin approval | **None** | No | Manual verification & approval gate release |
| **Coupons & UR-4386** | 100% discount delayed schedule validation | **None** | Yes | Prevents 0-amount gateway errors |
| **Multi-Currency** | Real-time FX conversion engine | **None** | Yes | USD, EUR, GBP, CAD, AUD, JPY, INR |
| **Inclusive/Exclusive Tax** | Dynamic tax calculation & breakdown | **None** | Yes | Region-based tax rate assertion |
| **Team Membership** | Fixed, per-seat, and tiered allocation tests | **None** | Yes | Seat allocation & validation |
| **Membership Upgrades** | Proration calculation (`price_per_day * days`) | **None** | Yes | Order upgrade & credit transition |
| **PDF Invoices** | Sequential `INV-{year}-{id}` generator | **None** | Yes | Tokenized download URL |

---

## How to Use This Plugin for Testing

### 1. Zero-Credentials Simulation Mode (Default)
When activated, `ur-payment-tester` automatically operates in **Simulated Mode**. All outgoing HTTP requests to Stripe, PayPal, Authorize.Net, and Mollie are intercepted locally with 0ms latency. You **do not need API keys, merchant accounts, or webhooks configured** in Stripe/PayPal dashboards. Forms will process checkouts and webhook endpoints will accept authentic payloads out-of-the-box.

---

## QA Testing Guide: Browser UI Walkthrough (No WP-CLI Needed)

If you are a QA engineer who tests exclusively via the WordPress Admin dashboard without terminal or WP-CLI access, follow this comprehensive playbook:

### Step 1: 30-Second Sanity Check (Scenario Recipes)
1. Go to **WP Admin → UR Payment Tester → Scenario Recipes**.
2. Click the blue **Run Recipe** button on any scenario:
   * **Scenario 1**: Happy Path 3-Cycle Subscription (Stripe & PayPal)
   * **Scenario 2**: Failed Payment & Grace Period Retry Exhaustion (3 cycles)
   * **Scenario 3**: Cancellation at Period End & Expiry Check
   * **Scenario 4**: Prorated Tier Upgrade ($10/mo Basic -> $30/mo Pro)
   * **Scenario 5**: 100% Coupon Delayed Subscription Schedule (UR-4386)
   * **Scenario 6**: Direct Bank Transfer Quarantine & Admin Approval
3. Watch the **Real-Time Audit Console** on the right side of the screen stream live verification checks:
   * Every passed assertion outputs green `[PASS]`.
   * If an assertion fails, it displays red `[FAIL]` with the expected vs actual value.

---

### Step 2: Testing Real-World Custom Use Cases (e.g. 6-Month Plan with Setup Fee)

#### The Scenario:
* A user registers for a **6-Month Membership Plan** at **$15.00/month**.
* **Month 1**: Charged **$25.00** ($10 one-time setup fee + $15 recurring charge).
* **Months 2 through 6**: Charged the regular **$15.00/month** recurring rate.
* **Month 7**: The plan reaches its 6-month limit and must expire, locking out member content.

#### How to test this in 3 minutes via the UI:

1. **Month 1 — Initial Registration & First Charge**:
   * Open the frontend registration form in an incognito window.
   * Select the 6-Month Plan and choose any payment method (Stripe, PayPal, Authorize.Net, Mollie, or Bank Transfer).
   * Enter test credentials (e.g. test card `4242...`) and submit.
   * Go to **User Registration → Orders**: Verify Order #1 has status `completed` and total `$25.00`.
   * Go to **User Registration → Subscriptions**: Verify subscription is `active` with next billing date set to +1 month.
   * Go to **UR Payment Tester → Mailbox Trap**: Click **View Content** to inspect the welcome email and initial $25 receipt.

2. **Month 2 — Fast-Forwarding & Renewal at $15.00**:
   * Go to **UR Payment Tester → Test Bench**.
   * Under **Select Target Member / Subscription**, pick this new subscription from the dropdown.
   * Click **Advance 30 Days & Renew**: The database billing date advances by 30 days into Month 2.
   * Under **Programmatic Cron Execution**, click **Renewal Check Cron** (`urm_daily_membership_renewal_check`).
   * Go to **User Registration → Orders**: Verify a **Cycle 2 order** was generated for **$15.00** (confirming that the one-time $10 setup fee from Month 1 was **not** re-billed).

3. **Months 3, 4, and 5 — Mid-Term Recurring Progression**:
   * On the **Test Bench**, click **Advance 30 Days & Renew** and then **Renewal Check Cron** for Months 3, 4, and 5.
   * Verify an order for **$15.00** is logged sequentially for each cycle.

4. **Negative Test: What If Payment Fails on Month 3? (Grace Period & Retries)**:
   * At Month 3, instead of renewing, click the red **Inject Failure** button on the Test Bench (or dispatch `invoice.payment_failed` / `PAYMENT.FAILED` from the Webhook Synthesizer).
   * Subscription status switches to `pending` (grace period).
   * Click **Payment Retry Cron** (`urm_daily_payment_retry_check`).
   * Go to **Mailbox Trap**: Verify the user received the **"Payment Failed — Please Update Your Card"** email.
   * Click **Payment Retry Cron** 3 times to simulate daily retries: Confirm that after max retries are exhausted, the subscription cancels and access is revoked.

5. **Month 6 — Expiration & Access Revocation Boundary**:
   * On the **Test Bench**, click **Advance 30 Days** past the 6th month into Month 7.
   * Click **Expiration Check Cron** (`urm_daily_membership_expiration_check`).
   * Verify the subscription status switches from `active` to **`expired`**.
   * Go to **Mailbox Trap**: Confirm the **"Membership Expired"** notification was dispatched.
   * Visit a restricted post/page as that user: Confirm the user is locked out by the content restriction gate.

---

### Step 3: Webhook Synthesizer Tab (Custom Edge Cases)
* **Target Gateways**: Stripe, PayPal REST, Authorize.Net, and Mollie.
* **Selectable Events**:
  * Stripe: `invoice.payment_succeeded`, `invoice.payment_failed`, `customer.subscription.deleted`, `charge.refunded`, `customer.subscription.created`, `payment_intent.payment_failed`.
  * PayPal: `PAYMENT.CAPTURE.COMPLETED`, `BILLING.SUBSCRIPTION.ACTIVATED`, `PAYMENT.SALE.COMPLETED`, `BILLING.SUBSCRIPTION.PAYMENT.FAILED`, `BILLING.SUBSCRIPTION.CANCELLED`.
  * Authorize.Net: `net.authorize.payment.authcapture.created`, `net.authorize.customer.subscription.failed`, `net.authorize.customer.subscription.cancelled`, `net.authorize.payment.refund.created`.
  * Mollie: `payment.paid`, `payment.failed`, `subscription.canceled`, `payment.refunded`.
* Enter any Target Subscription ID or Order ID, set a custom Charge Amount ($), and click **Dispatch Signed Webhook to URM Endpoint**.
* The server will deliver the payload internally with valid cryptographic signatures and display the HTTP status code and latency in the console.

---

### Step 4: Direct Bank Transfer (Offline BACS Quarantine & Approval)
1. Register a user selecting **Direct Bank Transfer**.
2. Go to **UR Payment Tester → Test Bench → Pending Bank Approvals**.
3. Confirm the order is `pending` and user login status is `0` (quarantined).
4. Click **Approve Payment & Release Gate**.
5. Confirm the order is completed, subscription activated, and user login unlocked (`ur_user_status = 1`).

---

### Step 5: Local Mailbox Trap (Email Verification)
* Every email sent by User Registration (welcome notices, receipts, retry warnings, cancellation emails) is trapped in memory.
* Click **View Content** to inspect:
  * Recipient, subject, and timestamp.
  * Rendered HTML layout and dynamic placeholders (invoice numbers, amounts, dates).
* Click **Clear Mailbox** to reset the trap.

---

### Step 6: 1-Click Clean Teardown & Reset
* When your test run is complete, click **Purge Test Data** in the top header (or navigate to **Settings & Purge → Purge All Simulated Data**).
* All mock orders, subscriptions, trapped emails, and ephemeral test users tagged with `urpt_simulated` are deleted without touching genuine customer data.

---

## Testing via Headless WP-CLI

For developers, CI/CD pipelines, or terminal users:

```bash
# 1. Run Pre-Packaged End-to-End Scenarios (1 through 6)
wp urpt scenario 1
wp urpt scenario 2
wp urpt scenario 3
wp urpt scenario 4
wp urpt scenario 5
wp urpt scenario 6

# 2. Fast-Forward / Time-Travel Subscriptions
# Advance subscription #20 by 30 days and advance billing dates
wp urpt time-travel --subscription=20 --days=30

# Rewind subscription #20 by 7 days
wp urpt time-travel --subscription=20 --days=-7

# 3. Dispatch Authentic Signed Webhooks Across All 4 Gateways
# Stripe renewal webhook
wp urpt webhook --gateway=stripe --event=invoice.payment_succeeded --subscription=20

# Stripe payment failure webhook
wp urpt webhook --gateway=stripe --event=invoice.payment_failed --subscription=20

# PayPal sale completed webhook
wp urpt webhook --gateway=paypal --event=PAYMENT.SALE.COMPLETED --subscription=20

# Authorize.Net payment captured webhook
wp urpt webhook --gateway=authorize --event=net.authorize.payment.authcapture.created --subscription=20

# Mollie payment paid webhook
wp urpt webhook --gateway=mollie --event=payment.paid --subscription=20

# 4. Safe Data Purge
wp urpt purge
```

---

## Installation & TasteWP Deployment

1. Download `ur-payment-tester.zip`.
2. On any WordPress site (TasteWP, InstaWP, LocalWP, or Staging):
   * Go to **Plugins > Add New > Upload Plugin**.
   * Upload `ur-payment-tester.zip` and click **Activate**.
3. Access the suite under **User Registration > UR Payment Tester** or **WP Admin > UR Payment Tester**.

---

## Author & License

* **Author**: Ganesh Bhatta ([@bhattaganesh](https://github.com/bhattaganesh))
* **License**: GPL-2.0+
