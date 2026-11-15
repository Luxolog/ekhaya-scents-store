# Ekhaya Scents v2 — backup import plan

## Source snapshot
- Backup: UpdraftPlus set dated 14 November 2026.
- Site: ekhayascents.co.za.
- Database archive and all supplied ZIP archives passed integrity checks.
- This is a staging/import source only. It is not the final launch dataset.

## Source tables confirmed in the SQL dump

### Identity and catalogue
- `wp_users`, `wp_usermeta`: customer accounts and account metadata.
- `wp_posts`, `wp_postmeta`: products, variations, media attachments and product metadata.
- `wp_terms`, `wp_term_taxonomy`, `wp_term_relationships`: product categories, tags and taxonomy links.
- `wp_wc_product_meta_lookup`, `wp_wc_product_attributes_lookup`, `wp_wc_category_lookup`: WooCommerce lookup/index tables. Rebuild or validate these from canonical product data; do not treat them as the only source.
- `wp_options`: selected store configuration only. Do not blindly copy all options into the new application.

### Orders and commerce
- `wp_wc_orders`, `wp_wc_orders_meta`: HPOS order records and metadata.
- `wp_wc_order_addresses`, `wp_wc_order_operational_data`: HPOS address and operational records.
- `wp_wc_order_stats`: analytics/status projection; reconcile against canonical HPOS orders.
- `wp_woocommerce_order_items`, `wp_woocommerce_order_itemmeta`: line items, fees, shipping and item metadata.
- `wp_wc_order_product_lookup`, `wp_wc_order_coupon_lookup`, `wp_wc_order_tax_lookup`: reporting projections.
- `wp_posts` / `wp_postmeta` may also contain legacy order records and must be checked for historical overlap. Do not double-import.
- `wp_wc_customer_lookup`: reporting/customer lookup projection.
- `wp_posts` / `wp_postmeta` coupon records and coupon metadata must be included in the coupon extraction.
- `wp_woocommerce_payment_tokens`: payment token metadata; do not migrate reusable payment tokens into the new system unless gateway and security requirements explicitly support it. Never import gateway secrets.

### Ekhaya business data
- `wp_ekhaya_commissions`, `wp_ekhaya_commissions_staging`: reseller commission ledger and staging records.
- `wp_ekhaya_rewards_ledger`: customer rewards history.
- `wp_ekhaya_bulk_jobs`, `wp_ekhaya_bulk_items`: bulk email jobs and individual queue items.
- `wp_ekhaya_cm_subscribers`, `wp_letterness_subscribers`: campaign subscriber records and consent/unsubscribe data; deduplicate only after preserving source IDs and consent evidence.
- Shopify import staging tables and any associated source metadata: retain for traceability, do not turn staging records into live customers without validation.

## Import sequence

1. **Read-only extraction**
   - Extract the SQL dump into an isolated staging database.
   - Record source table counts, source IDs, table schemas and snapshot timestamp.
   - Keep the source database read-only.
   - Never put the SQL dump, customer export, uploaded media archive or credentials in Git.

2. **Identity**
   - Import customer accounts using `wp_users` and relevant `wp_usermeta` fields.
   - Preserve source user IDs in `legacy_id_map`.
   - Carry password hashes only if compatibility is confirmed; otherwise require secure password reset. Never email password setup links during import.
   - Preserve account status and verified-email state where evidence exists.
   - Deduplicate by normalized email only with a conflict report; do not silently merge accounts.

3. **Catalogue and media**
   - Import published products and variations from canonical product posts and metadata.
   - Import category taxonomy relationships.
   - Preserve SKU, descriptions, prices, sale prices, stock settings, dimensions, shipping flags and product metadata.
   - Resolve attachment IDs to the supplied uploads archive paths; copy media into the new private staging web root and validate every referenced file.
   - Store source attachment/product IDs in `legacy_id_map`.
   - The backup does not establish the complete new perfume-making supplies catalogue. Add those products only from the owner's current SKU, price, stock and image list.

4. **Coupons**
   - Extract coupon posts and all applicable metadata.
   - Preserve code, discount type, amount, limits, dates, restrictions and usage count.
   - Report expired, duplicate, malformed and already-used coupons separately.

5. **Orders**
   - Treat HPOS tables as canonical for HPOS orders: `wp_wc_orders`, `wp_wc_orders_meta`, `wp_wc_order_addresses` and `wp_wc_order_operational_data`.
   - Extract line items from `wp_woocommerce_order_items` and `wp_woocommerce_order_itemmeta`.
   - Preserve order numbers, statuses, timestamps, totals, currency, billing/shipping snapshots, gateway names and transaction references.
   - Check legacy post-based orders and HPOS order IDs for overlap before importing; use a single canonical order per source order.
   - Reconcile `wp_wc_order_stats` against canonical order rows. Do not discard unmatched stats; write a reconciliation report.
   - Do not initiate a payment, issue a refund or send a customer notification as a side effect of importing.

6. **Reseller and rewards ledgers**
   - Preserve all original ledger entries and source IDs.
   - Import as historical ledger entries, then calculate and compare opening balances independently.
   - Keep customer rewards and reseller commissions in separate tables and services.
   - Do not create new accruals, payouts or redemptions during import.
   - Do not enable the inactive reseller plugin v1.6.0 monthly R600 threshold / R49 invoice behaviour.

7. **Consent and email**
   - Preserve subscribed, unsubscribed and unknown consent states plus available evidence/source timestamps.
   - Unknown consent remains non-marketable.
   - Import historical jobs/items as `audit_only` or `paused`; never replay the source queue.
   - Separate transactional messages from marketing.
   - New store sender identity: `hello@ekhayascents.co.za`.
   - Before creating a new campaign send, re-check current consent and unsubscribe state at send time.

8. **Validation and reconciliation**
   - Produce counts by entity, source IDs missing mappings, duplicate/conflict reports and row-level checksums.
   - Compare customers, products, variations, media, coupons, orders, order items, payment statuses, inventory, reward balances and commission balances.
   - Manually investigate the known difference between 25 HPOS order records and 24 order-stat records in this snapshot.
   - Confirm the 2,959 coupon records and their status/usage semantics before enabling coupons.
   - Run test imports repeatedly into disposable staging databases; importer must be idempotent and resumable.

9. **Final cutover**
   - Take a fresh final export immediately before cutover.
   - Import only the delta since the 14 November snapshot using stable source IDs/checksums.
   - Freeze old-store writes for the final sync window.
   - Complete checkout, PayFast sandbox/live verification, order emails, refunds, shipping and account tests.
   - Keep old hosting/store available through the rollback window. No DNS switch or cancellation before explicit acceptance.

## Importer implementation requirements
- PHP CLI-compatible scripts suitable for shared hosting; no Composer dependency until hosting SSH/Composer support is confirmed.
- Use PDO prepared statements and explicit transactions per batch.
- Batch reads/writes; checkpoint each source table and last processed source ID.
- Maintain `migration_batches` and `legacy_id_map`.
- Dry-run mode is the default. A separate explicit commit flag is required to write target rows.
- Idempotency: re-running a batch must not duplicate target rows or ledger entries.
- Never log passwords, email addresses, phone numbers, full addresses, payment tokens, secrets or full email payloads.
- Do not trust browser input for import, pricing, inventory or payment state.
- Importer must stop on schema mismatch and produce a sanitized report rather than guessing column mappings.

## Open deployment prerequisites
- Confirm Domains.co.za package and exact PHP/MySQL or MariaDB versions.
- Confirm required PHP extensions, cron, SSH/CLI availability and storage limits.
- Create a protected staging site and an empty staging database.
- Keep database credentials and gateway secrets in server environment/config outside the Git repository.
