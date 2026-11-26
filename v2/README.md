# Ekhaya Scents Platform V2

This is the canonical V2 application source for the consolidated Ekhaya Scents platform.

## Source of truth
- Repository: `Luxolog/ekhaya-scents-store`
- Development branch: `ekhaya-platform-v2`
- Recovery branch: `backup/ekhaya-platform-v2-2026-11-26`
- Do not merge the divergent legacy `main` branch into V2.
- The GitHub Pages workflow deploys a preview only. It does not deploy to the production domain.

## V2 application layout
- `index.html`, `assets/`: V2 storefront and browser application.
- `server/public/`: PHP API public document root.
- `server/src/`: private PHP application classes.
- `server/config.example.php`: configuration template only; never put credentials here.
- `database/schema.sql`: V2 database schema.

## Consolidation and production status
The current production domain, `https://ekhayascents.co.za`, is still serving the existing storefront. V2 is not yet approved for production cutover.

The hosting account has a private workspace at `/home/robogirl/ekhaya-staging/` and an existing V1 API at `/home/robogirl/public_html/dev.ekhayascents.co.za/api/`. Preserve the V1 files and database until the V2 migration has been verified.

## Required before production
1. Back up the production website files, database, uploads, plugin configuration, and payment settings.
2. Export and map WooCommerce products, variations, categories, images, stock, coupons, customers, addresses, orders, refunds, and order statuses.
3. Inspect the actual reseller, referral, loyalty, reward, commission, and campaign rules and migrate only verified records.
4. Create a separate V2 production database; never import the V2 schema over the V1 database.
5. Seed the V2 product catalogue from the approved live SKU list and server-side prices. Do not rely on browser-only catalogue data for production pricing or stock.
6. Review and test authentication, sessions, CSRF, email delivery, admin permissions, order persistence, shipping, and PayFast ITN verification.
7. Complete end-to-end PayFast Sandbox tests, including success, failure, cancellation, duplicate ITNs, amount mismatch, and invalid signatures.
8. Confirm hosting PHP version/extensions, database version, SSL, document roots, private config path, file permissions, and backup/restore procedure.
9. Freeze production writes for the final migration delta, reconcile record counts and totals, then deploy during an agreed maintenance window.
10. Keep the previous production site and its backups available for rollback until post-cutover checks pass.

## Current commercial rules to verify during migration
- Standard delivery: R85; estimated 3–5 business days.
- Origin Signature retail: 30ml R169, 50ml R249, 100ml R349.
- Origin Inspired retail: 30ml R199, 50ml R299, 100ml R399.
- Reed diffuser 150ml: R299.
- Linen spray 250ml: R149.
- Car perfume: R50.
- Approved stock-buying resellers only receive reseller pricing after authentication and server-side approval.
- Fragrance supplies remain enquiry-only until each SKU and price is confirmed.

## Security
- Never commit database credentials, PayFast merchant keys, passphrases, API keys, customer exports, or production backups.
- Store secrets only in the private `config.local.php` or protected hosting environment variables.
- Do not mark an order paid from the browser return page. Only a verified PayFast ITN may confirm payment.
