# Ekhaya Scents backend — HOSTAFRICA

This folder is for the production backend. GitHub Pages remains the public frontend test environment.

## Before going live
1. Upload the API folder to the Ekhaya Scents HOSTAFRICA hosting account.
2. Copy config.example.php to config.php.
3. Put the MySQL database name/user/password and Payfast merchant credentials into config.php on HOSTAFRICA only.
4. Never commit config.php, Payfast passphrases or customer data to the public GitHub repository.
5. Import schema.sql into the HOSTAFRICA MySQL database.
6. Point the storefront checkout API URL to the HOSTAFRICA HTTPS endpoint.
7. Test Payfast in sandbox mode first.
8. Only switch sandbox=false after ITN/order verification has been tested.

## Email
The API fixes the sender as hello@ekhayascents.store. The hosting mail transport is used server-side; the browser cannot spoof the From address.

## Campaigns
Historical customers should remain REVIEW_REQUIRED until marketing consent/POPIA basis is confirmed. Transactional emails (order confirmation, payment confirmation, shipping updates) are separate from marketing campaigns.

## Payfast
The custom integration follows the Payfast web integration pattern: the browser never receives the merchant passphrase. The server creates a signed payment payload. The ITN endpoint must perform Payfast's required security checks before marking an order paid.
