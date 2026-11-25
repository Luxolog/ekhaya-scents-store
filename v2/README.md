# Ekhaya Scents Platform v2 — staging build

This folder is an isolated rebuild preview. It does not replace the current production storefront.

## Preview
Open `index.html` in a browser or deploy this folder to a protected staging location.

## Current milestone
- Premium responsive Ekhaya storefront direction
- Origin signature fragrance collection
- Home and car fragrance collection
- New perfume-making supplies division (pure oils, bottles, boxes, bases and accessories)
- Starter-kit discovery section
- Reseller programme entry point
- Interactive front-end bag preview

## Important limitations
- This is a front-end design and interaction preview only.
- Cart state is in memory and is not an order.
- Checkout is intentionally disabled. No payment, customer data or order is collected.
- Supply products are category placeholders until the live SKU list, stock and prices are confirmed.
- Do not use this preview for live sales.

## Production migration requirements
1. Inventory and export current WooCommerce products, variations, images, categories, coupons, customers, orders and order statuses.
2. Back up WordPress files, database, uploads, plugin ZIPs and configuration.
3. Inspect and document Ekhaya rewards, reseller, referral, commission and campaign rules from the actual plugin source and database.
4. Build the PHP 8.2+ / MariaDB backend with server-side pricing, inventory and order persistence.
5. Integrate PayFast with server-created order totals, ITN signature and source verification, amount/currency checks, idempotency and payment-state transitions.
6. Implement customer authentication, order history, admin catalogue/order management and shipping configuration.
7. Test on Domains.co.za staging before changing DNS.
8. Freeze writes briefly for final database delta export/import, verify payments and email, then switch DNS.
9. Keep HostAfrica active until post-cutover verification and a rollback window are complete.

## Deployment target
Domains.co.za Basic shared hosting, subject to confirming available PHP extensions, MariaDB, cron, SSH/Composer and storage. Keep deployment compatible with cPanel and avoid requiring Vercel.

## Recovery checkpoint — 26 November 2026
The V2 storefront source is preserved on `ekhaya-platform-v2`. The recovery backup branch is `backup/ekhaya-platform-v2-2026-11-26`. Keep this V2 source as the storefront recovery baseline; do not merge the divergent `main` branch over it. This preview is not a live checkout: payment and order processing remain disabled pending backend integration and testing.
