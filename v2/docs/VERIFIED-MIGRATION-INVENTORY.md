# Ekhaya Scents v2 — verified migration inventory

Audit basis: the UpdraftPlus backup set dated 14 November 2026 for https://ekhayascents.co.za. The archive integrity checks passed for every supplied ZIP and the database gzip stream.

## Existing platform identified
- WordPress 7.1.4, WooCommerce 11.2.1, PHP 8.2.35 at source.
- WooCommerce High-Performance Order Storage (HPOS) is enabled. Import/export must preserve the HPOS order records and related order metadata, not assume orders exist only in `wp_posts`.
- Active theme: `ekhaya-scents`.
- Store currency: ZAR. Store base location: Pretoria, Gauteng, South Africa.
- PayFast gateway is active in the backup. Paystack plugin files are present, but Paystack is not listed among the active plugins.
- Existing site email configuration and policy content must be normalised to `hello@ekhayascents.co.za` during migration.

## Existing catalogue snapshot
The database contains published Origin/signature fragrances, inspired-by perfumes, car perfume and reed diffuser products. This snapshot does not yet provide a complete catalogue for the newly expanded perfume-making supplies division. Pure oils, bottles, boxes, bases, accessories and starter kits must be added from the current business SKU/price/stock list rather than invented from the backup.

## Verified Ekhaya business rules from active plugin source

### Customer rewards — Ekhaya Scents Rewards 1.2.2
- Earn a flat 5% reward on qualifying website merchandise spend.
- Shipping and tax are excluded from the qualifying spend.
- 100 points = R10.
- Minimum redemption: 1,000 points = R100.
- Redemption is restricted to eligible perfume products; shipping, tax and non-perfume items are excluded.
- Unredeemed points expire 12 months after earning.
- Free delivery is applied to qualifying purchases 5 and 11, then every third qualifying purchase after 11 (14, 17, 20, etc.).
- Customer rewards ledger is separate from the reseller commission ledger.
- Refunds reverse earned points proportionally; cancelled/failed orders can restore redeemed points.

### Reseller programme — active copy 1.5.7
- Two distinct paths exist: referral partner (no stock held) and stock-buying/wholesale reseller.
- Approved wholesale resellers can receive product-level wholesale prices and sell stock at their own retail prices.
- Referral commission is R10 for each complete R50 block of eligible merchandise spend after discounts. Incomplete blocks do not earn; shipping and tax are excluded.
- The active source also has a separate once-off R50 first-paid-purchase referral bonus.
- A separate R50 reseller-recruitment reward is recorded when the recruited reseller's first qualifying stock purchase exceeds R250, subject to the plugin's eligibility checks.
- Refund adjustments and duplicate-prevention checks exist in the commission ledger.
- Commission payment is intended to be blocked until the related order is completed.

### Important version discrepancy — do not silently enable
- The active plugin path in the database is the older reseller programme copy reporting version 1.5.7.
- A separate installed but inactive 1.6.0 copy exists.
- Version 1.6.0 adds a monthly wholesale-reseller subscription rule: it checks the previous month's qualifying stock purchases against R600 and may create a R49 invoice and suspend direct-store selling until paid.
- This is not active in the supplied database's active plugin list. Do not carry this rule into v2 unless the business owner explicitly confirms it.

### Marketing and email automation
- Campaign Manager 1.3.0 restricts marketing campaigns to active subscribers and customers with recorded marketing consent; unsubscribed and unknown-consent contacts are excluded.
- Automated follow-ups 1.1.0 supports first-purchase follow-ups after 7 and 15 days and a monthly campaign on the 29th at 09:00 (last day of February when no 29th exists).
- Bulk email processing uses queued jobs and individual/batched sends. Historical queued jobs/items must be imported as audit records only; never automatically replay them during migration because that could resend password setup or marketing emails.
- Transactional messages must remain distinct from marketing messages.

## Data structures to preserve
The database includes WordPress users/usermeta, WooCommerce products and coupons, HPOS orders/order stats/order addresses/order items, shipping configuration, Ekhaya commissions, Ekhaya rewards ledger, bulk-processing jobs/items, campaign subscribers/events and Shopify customer-import staging data.

## Migration controls
1. Treat the 14 November backup as a snapshot, not the final cutover dataset. Orders, users, stock, coupons and balances may change before migration.
2. Preserve original IDs or create explicit old-to-new mapping tables for customers, products, orders, coupons, reseller accounts and ledger entries.
3. Import order history and payment states; do not initiate payments during import.
4. Preserve commission and reward ledgers as immutable history. Reconcile opening balances before enabling new accrual/redemption.
5. Preserve marketing consent and unsubscribe status; default unknown consent to non-marketable.
6. Keep email queues paused during import and cutover. Reconcile queued work manually before any new send.
7. Verify PayFast server-side order creation, ITN signature/source verification, amount/currency matching, idempotency and payment-state transitions before live checkout.
8. Run final delta export/import and full payment/order tests before DNS cutover. Keep the old store and hosting available through the rollback window.

## Source files
The original backup archives remain the source of truth for the plugin/theme implementations and media. Do not upload database dumps, customer exports, credentials, or backup archives into the public Git repository.
