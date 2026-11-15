# Ekhaya Scents v2 PHP backend foundation

PHP 8.2+, PDO MySQL and MariaDB/MySQL. No framework or Composer dependency required for this first phase.

## Structure
- public/: document root contents; point the staging subdomain here when deploying.
- public/api/index.php: minimal JSON API.
- src/: application classes; keep outside public web root.
- config.example.php: safe configuration template.
- config.local.php: local/server secrets; never commit.

## Configure
1. Create the v2 database using the SQL schema at ../database/schema.sql.
2. Copy config.example.php to config.local.php and configure database values through environment variables or a server-only file.
3. Set the staging subdomain document root to this public directory (or copy public contents into its document root while keeping src/config outside the public tree and adjust paths).
4. Enable SSL before testing authentication or checkout.
5. Test GET /api, GET /api/health and GET /api/products.

## API routes
- GET /api — service/version metadata.
- GET /api/health — database connectivity check.
- GET /api/products — published catalogue records (maximum 100).

## Safety status
- No customer login or account creation endpoint is exposed yet.
- No cart/order/payment endpoint is exposed yet.
- PayFast checkout is intentionally not implemented until server-side order creation, signature/source verification, amount reconciliation and idempotent ITN handling are ready.
- API errors return generic messages; detailed errors go to the server error log.
- Do not expose this staging API publicly until SSL, access protection and database credentials are configured.
