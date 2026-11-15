# Ekhaya Scents v2 PHP backend

PHP 8.2+, PDO MySQL. The API is an early staging foundation.

## Deployment layout
Set the subdomain document root to `v2/server/public`. Keep `src`, `config.local.php`, and secrets outside the public directory.

## Configure
- Apply `../database/schema.sql`, then `../database/auth-additions.sql`.
- Copy `config.example.php` to `config.local.php` in `v2/server`.
- Set database credentials and a long random `EKHAYA_RATE_LIMIT_SECRET` through hosting environment variables.
- Set `EKHAYA_ALLOWED_ORIGINS` to the exact staging origin.
- Use HTTPS. Session cookies become Secure when HTTPS is detected.

## Endpoints
- GET /api
- GET /api/health
- GET /api/products
- GET /api/auth/csrf
- POST /api/auth/register
- POST /api/auth/login
- GET /api/auth/me
- POST /api/auth/logout

## Account activation
New registrations are created with `pending` status and cannot log in until an administrator or verified email workflow activates them. This intentionally avoids unverified accounts being treated as active customers. A proper email verification and password reset workflow is a next phase.

## Security notes
- Passwords use PHP `password_hash` and `password_verify`.
- Mutating auth requests require a session CSRF token.
- Login and registration attempts are rate-limited using a server-only HMAC secret.
- Use HTTPS and keep config outside public web root.
- This is not yet ready for public customer signups: email verification, account activation administration, recovery flow, and full security testing are still required.
