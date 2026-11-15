# Ekhaya Scents v2 PHP backend and staging storefront

PHP 8.2+, PDO MySQL. The API and account area are an early staging foundation.

## Deployment layout
Set the staging subdomain document root to `v2/server/public`. This folder now contains the storefront entry page, assets, account pages, and API front controller. Keep `src`, `config.local.php`, and secrets outside the public directory.

## Configure
- Apply `../database/schema.sql`, then `../database/auth-additions.sql`, then `../database/marketing-additions.sql`.
- Read `CUSTOMER-ENGAGEMENT.md` for lifecycle, consent, campaign, queue and launch-gate requirements. The seeded automation rules and templates are inactive; this migration does not send email.
- Copy `config.example.php` to `config.local.php` in `v2/server`.
- Set database credentials and a long random `EKHAYA_RATE_LIMIT_SECRET` through hosting environment variables.
- Set `EKHAYA_ALLOWED_ORIGINS` and `EKHAYA_BASE_URL` to the exact staging origin.
- Set `EKHAYA_MAIL_FROM=hello@ekhayascents.co.za`. PHP `mail()` must be enabled and routed by the host; use authenticated SMTP or a transactional provider for production once credentials are provisioned.
- Use HTTPS. Session cookies become Secure when HTTPS is detected.

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

## Security notes
Passwords use PHP password_hash/password_verify. Mutating auth requests require a CSRF token. Registration, login and recovery are rate-limited with a server-only HMAC secret. Keep config outside the public web root and use HTTPS. Verify host mail delivery, SPF, DKIM, DMARC and delivery logs before public signups.

Not production-ready yet: full runtime/security tests, SMTP integration and admin account controls remain. Order history reads the new platform's orders table; legacy WooCommerce orders will appear only after the migration importer is implemented and validated. Checkout remains disabled until secure order creation and PayFast ITN verification are completed.
