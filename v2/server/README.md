# Ekhaya Scents v2 PHP backend

PHP 8.2+, PDO MySQL. The API is an early staging foundation.

## Deployment layout
Set the subdomain document root to `v2/server/public`. Keep `src`, `config.local.php`, and secrets outside the public directory.

## Configure
- Apply `../database/schema.sql`, then `../database/auth-additions.sql`.
- Copy `config.example.php` to `config.local.php` in `v2/server`.
- Set database credentials and a long random `EKHAYA_RATE_LIMIT_SECRET` through hosting environment variables.
- Set `EKHAYA_ALLOWED_ORIGINS` and `EKHAYA_BASE_URL` to the exact staging origin.
- Set `EKHAYA_MAIL_FROM=hello@ekhayascents.co.za`. PHP `mail()` must be enabled and routed by the host; use authenticated SMTP or a transactional provider for production once credentials are provisioned.
- Use HTTPS. Session cookies become Secure when HTTPS is detected.

## Endpoints
- GET /api, /api/health, /api/products, /api/auth/csrf, /api/auth/me
- POST /api/auth/register, /api/auth/verify-email, /api/auth/login, /api/auth/logout
- POST /api/auth/password/forgot, /api/auth/password/reset

## Account lifecycle
Registration creates a pending account and sends a single-use verification link (24-hour expiry). Verification activates the account. Login requires active status and verified email. Password reset links are single-use and expire after one hour. Forgot-password responses do not reveal whether an account exists. Passwords require at least 12 characters. Only SHA-256 hashes of random 256-bit tokens are stored.

## Security notes
Passwords use PHP password_hash/password_verify. Mutating auth requests require a CSRF token. Registration, login and recovery are rate-limited with a server-only HMAC secret. Keep config outside the public web root and use HTTPS. Verify host mail delivery, SPF, DKIM, DMARC and delivery logs before public signups.

Not production-ready yet: frontend verification/reset screens, admin account controls, SMTP integration, session invalidation, and full security testing remain.
