# Ekhaya Scents v2 PHP backend and staging storefront

PHP 8.2+, PDO MySQL. The API and account area are an early staging foundation.

## Safe staging deployment

The staging hostname is `dev.ekhayascents.co.za`. It must be configured independently of the production site. Do not change the production document root, DNS records, or live WordPress installation as part of staging work.

### 1. DNS and hosting

- In Domains.co.za, open **Manage Services → Web Hosting → Manage → Login** to enter cPanel.
- In cPanel **Domains**, create `dev.ekhayascents.co.za` with its own document root. Do not share the production document root.
- If DNS is hosted outside this cPanel account, add the matching A or CNAME record at the authoritative DNS provider using the actual hosting target supplied by Domains.co.za. Do not guess an IP address.
- If DNS is hosted in the same cPanel zone, check whether creating the subdomain has already created the DNS record.
- Confirm the hostname resolves before deployment. Issue/enable SSL (AutoSSL where available) and confirm HTTPS before using the staging URL.
- Set the staging document root to the contents of `v2/server/public`. Keep PHP source, database configuration and secrets outside the public directory.

Domains.co.za guides:
- Create a subdomain: https://www.domains.co.za/knowledgebase/hosting/how-to-create-a-subdomain-in-cpanel/
- Manage DNS records: https://www.domains.co.za/knowledgebase/domains/dns-records-in-cpanel/

### 2. Staging application configuration

- Copy `config.example.php` to `config.local.php` in `v2/server`, outside the public web root.
- Configure a new, separate staging database. Never point staging at the production WordPress database.
- Set database credentials and a long random `EKHAYA_RATE_LIMIT_SECRET` through hosting environment variables or private configuration.
- Set `EKHAYA_ENV=staging`, `EKHAYA_BASE_URL=https://dev.ekhayascents.co.za`, and `EKHAYA_ALLOWED_ORIGINS=https://dev.ekhayascents.co.za`.
- Set `EKHAYA_MAIL_FROM=hello@ekhayascents.co.za`. Do not send real customer emails during initial staging tests. PHP `mail()` must be enabled and routed by the host; use authenticated SMTP or a transactional provider for production once credentials are provisioned.

### 3. Database setup

Apply only the store and account schemas for the current staging milestone:

1. `../database/schema.sql`
2. `../database/auth-additions.sql`

The marketing/customer-engagement schema is not required for the storefront deployment and must not be applied as part of this setup. Ekhaya Support Desk is a separate product. Do not import or activate its data/workflows in the store.

### 4. Deploy and verify

- Upload the contents of `v2/server/public` to the staging document root only.
- Confirm `https://dev.ekhayascents.co.za/api/health` returns a healthy response.
- Confirm `/api/categories`, `/api/products`, a product detail page, and the account pages load.
- Test registration, verification, login, password reset and account address flows using test accounts and a staging mail sink/test inbox.
- Confirm the production website and its DNS remain unchanged.
- Keep staging noindex and access-restricted while it is under development.

## Customer endpoints

- GET `/api/auth/csrf`, `/api/auth/me`
- POST `/api/auth/register`, `/api/auth/verify-email`, `/api/auth/login`, `/api/auth/logout`
- POST `/api/auth/password/forgot`, `/api/auth/password/reset`
- GET/POST `/api/account/profile`
- GET `/api/account/addresses`
- POST `/api/account/addresses/save`, `/api/account/addresses/delete`
- GET `/api/account/orders` (latest 50 orders with item snapshots)
- GET `/api/categories`
- GET `/api/products` (supports `category`, `search`, and `limit` query parameters)
- GET `/api/products/{slug}` (product details, images, variants and categories)
- GET `/api/health`, `/api`

## Customer pages

- `/account.html`: sign in, profile, saved addresses, order history, sign out
- `/register.html`: customer registration
- `/verify-email.html?token=...`: email verification
- `/forgot-password.html`: request password reset
- `/reset-password.html?token=...`: set a new password

## Account lifecycle

Registration creates a pending account and sends a single-use verification link (24-hour expiry). Verification activates the account. Login requires active status and verified email. Password reset links are single-use and expire after one hour. Forgot-password responses do not reveal whether an account exists. Passwords require at least 12 characters. Only SHA-256 hashes of random 256-bit tokens are stored.

## Security and launch gates

Passwords use PHP `password_hash/password_verify`. Mutating auth requests require a CSRF token. Registration, login and recovery are rate-limited with a server-only HMAC secret. Keep config outside the public web root and use HTTPS. Verify host mail delivery, SPF, DKIM, DMARC and delivery logs before public signups.

This is not production-ready yet: full runtime/security tests, SMTP integration, admin account controls, server-side cart/checkout, order creation and PayFast ITN verification remain. Checkout must stay disabled until secure order creation and PayFast ITN verification are completed. Legacy WooCommerce orders are not yet migrated into the new platform's orders table.


## Reseller portal API foundation (staging only)

The existing `reseller_accounts`, `referrals`, `reseller_commission_ledger` and `audit_logs` tables in `../database/schema.sql` support the first reseller application and approval stage. Apply `schema.sql` and `auth-additions.sql` to a dedicated staging database before testing these endpoints.

- GET `/api/reseller/status`: authenticated customer's application state.
- POST `/api/reseller/apply`: authenticated customer submits `reseller_type` (`stock_buyer` or `referral_partner`); application remains pending.
- GET `/api/reseller/catalog`: returns the stock-buyer price list only when the authenticated account is approved and is a `stock_buyer`. Pending, rejected, suspended and referral-only accounts receive no prices.
- GET `/api/admin/resellers`: lists applications for an authenticated admin.
- POST `/api/admin/resellers/review`: admin approves, rejects or suspends an application. Approval generates a referral code. All review actions are audit logged.

Admin access fails closed unless the private server configuration contains `security.admin_emails`. Set `EKHAYA_ADMIN_EMAILS` to a comma-separated list of designated administrator email addresses in the private staging environment. Do not put admin addresses or credentials in public frontend code. Admins must also have a verified, active customer account and must sign in normally.

The approved stock-buyer price response currently reflects the agreed price list: Origin 30ml R99, Origin 50ml R135, car perfume 7ml R25; reed diffuser and linen spray are unavailable. This API is a protected price-list foundation, not an order/checkout system. Reseller order creation, stock reservation, notifications, payment and fulfilment are not implemented by these endpoints. The existing browser-only reseller prototype must not be used to collect real orders or disclose live prices.

The API code is committed to the development branch only. It is not active on GitHub Pages (which cannot execute PHP). Before use, deploy the PHP public directory to the isolated HTTPS staging host, provision private configuration and database, set the admin allowlist, run the schema, and complete runtime/security tests. Do not point this staging API at the live WooCommerce database.


### Reseller order-request endpoint

- POST `/api/reseller/orders`: accepts SKU and quantity only from an authenticated, verified, approved `stock_buyer` reseller. Prices are looked up server-side; browser-supplied prices and totals are ignored. Requests are limited to 20 lines and 100 units per SKU. The server writes an order with status `reseller_request_pending`, item snapshots and an audit entry in one database transaction.
- The endpoint does not deduct stock, charge a payment method, promise stock availability, or trigger email notifications. Ekhaya must confirm availability, delivery and payment instructions manually. Do not describe this as completed checkout.
- The reseller portal now includes a quantity form that submits this request to the protected endpoint.

### Session security

The PHP session cookie is HTTPS-only (`Secure`, `HttpOnly`, `SameSite=Lax`) and PHP strict session mode plus cookie-only sessions are enabled. Serve the API exclusively over HTTPS; do not test authenticated flows over plain HTTP. Login rotates the session ID and CSRF token.

- After a reseller order request is committed to the database, the API attempts to email the reseller a receipt and each configured administrator (`security.admin_emails`) an action notice. These are best-effort PHP `mail()` attempts: delivery is not guaranteed until the staging mail transport is configured and tested. A mail failure is logged and does not roll back the saved order. The API response reports whether each message was handed to the host mail function; this is not proof of inbox delivery.
