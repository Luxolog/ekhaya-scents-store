# Ekhaya Customer Engagement

This module is the planned first-party lifecycle, after-sales, upsell and campaign system. It extends the existing email job queue; it is not a separate marketing plugin.

## Database setup

Apply in this order to a fresh staging database:

1. `../database/schema.sql`
2. `../database/auth-additions.sql`
3. `../database/marketing-additions.sql`

The marketing additions migration creates templates, customer events, automation rules and enrollments, campaign definitions, provider delivery events and channel suppressions. It seeds four starter automation rules as **inactive**. Applying the migration does not send email and does not activate marketing.

## Separation of message types

- **Transactional:** account verification, password reset, order received, payment confirmed, cancellation, refund and delivery status. These are triggered by verified system events and are not promotional.
- **Service/after-sales:** non-promotional product care, delivery satisfaction and support follow-ups. Keep content strictly service-related; if an email contains offers or product promotion, classify it as marketing.
- **Marketing:** newsletters, launches, offers, win-back, upsell and cross-sell. Require a recorded, valid email marketing consent and no unsubscribe/suppression.
- **Password setup:** controlled one-time account activation or migration flow. Never send plaintext or old passwords.

Unknown consent is not permission. Do not infer consent from an account, an order, a historical email queue or a purchased customer list.

## Initial journeys (all disabled)

| Rule | Trigger | Delay | Audience / guard |
|---|---|---:|---|
| First purchase day 7 | `order_completed` | 7 days | First purchase, explicit email marketing consent |
| First purchase day 15 | `order_completed` | 15 days | First purchase, explicit email marketing consent |
| After-sales day 1 | `order_delivered` | 1 day | Service-only content |
| After-sales day 7 | `order_delivered` | 7 days | Service-only content |

The seeded rules are configuration records, not a live scheduler. The event processor, rule evaluator, queue worker, unsubscribe handling and provider callbacks must be implemented and tested before enabling them. Product-specific content and recommendation mappings must be approved before activation.

## Recommended trigger catalogue

- `customer_registered`: welcome / account guidance (transactional or service-only; no unsolicited promotion).
- `order_created`: order receipt.
- `payment_confirmed`: verified payment confirmation.
- `order_processing`, `order_packed`, `order_dispatched`, `order_delivered`: fulfilment updates.
- `order_cancelled`, `refund_issued`, `payment_failed`: service notifications.
- `order_completed`: eligible post-purchase marketing journeys.
- `reward_earned`, `reward_expiring`: rewards service notifications.
- `reseller_commission_payable`, `referral_qualified`: private reseller account notifications.
- `product_replenishment_due`, `product_recommendation_eligible`: marketing only, with consent and eligibility checks.

Only publish an event after the underlying transaction is committed. Payment events must be created only after gateway signature, source, amount, currency and idempotency checks succeed.

## Campaign audience rules

Build audiences from first-party facts such as product category purchased, number of completed orders, last purchase date, customer lifetime spend, rewards eligibility and explicit preferences. Exclude:

- unsubscribed or unknown-consent contacts;
- hard-bounced, complained-about or invalid addresses;
- customers with unresolved support or delivery cases from promotional journeys;
- customers who already purchased the promoted item;
- customers whose order is cancelled/refunded where the journey assumes a successful purchase.

Marketing consent and channel suppressions must be checked again immediately before enqueue and immediately before sending. Keep a working unsubscribe mechanism in every marketing email.

## Delivery and reliability requirements

- Use a durable queue and a scheduled CLI worker; never send campaign mail inside a checkout or web request.
- Initial batch size: 40-50 recipients, configurable and rate-limited to the provider's approved throughput.
- Use unique event/enrollment keys to prevent duplicates.
- Retry transient errors with bounded exponential backoff; do not retry permanent rejection, hard bounce or complaint.
- Persist provider message IDs and webhook events. Verify provider webhook signatures before accepting delivery events.
- Separate transactional and marketing queues and prioritise critical transactional messages.
- Do not replay the legacy WooCommerce queue. Keep imported historical email items audit-only.
- Send from `hello@ekhayascents.co.za`; production requires authenticated SMTP or a transactional provider, SPF, DKIM and DMARC.

## Required admin capabilities

1. Customer segments with a recipient count and consent/suppression preview.
2. Automation rule editor: trigger, delay, template, conditions, stop conditions, caps, cooldown and active state.
3. Campaign builder: audience, template, test send, approval, schedule, pause and cancel.
4. Template editor with HTML and plain-text versions and version history.
5. Queue monitor: queued, processing, sent, failed, skipped, suppressed and retry states.
6. Delivery report: accepted, delivered, deferred, bounced, rejected, complaint, open and click events where provider support and privacy rules permit.
7. Attribution: campaign/automation code recorded on orders to measure revenue without claiming unsupported causal certainty.

## Launch gates

Do not activate any automation until all of these are complete:

- staging database migration applied and checked;
- event sources integrated with order/payment lifecycle;
- consent and suppression checks covered by tests;
- template preview, test-send and unsubscribe flow verified;
- duplicate and retry tests pass;
- SMTP/provider credentials stored outside Git;
- SPF, DKIM, DMARC and provider webhooks verified;
- admin access control and audit logging implemented;
- customer-care and marketing copy reviewed;
- staged test orders cover each relevant journey.

## Current implementation status

The database foundation and starter inactive rules are committed. The scheduler, event producers, queue processor, provider integration, unsubscribe endpoints, admin UI and analytics are **not yet implemented**. No campaign or follow-up emails are currently being sent by this module.
