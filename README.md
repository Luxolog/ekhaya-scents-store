# Ekhaya Scents Store

Standalone Ekhaya Scents / Origin storefront. Separate from Inganathi Driving Academy.

## Current build
- Responsive premium storefront
- Origin signature collection
- Home fragrance and car perfume categories
- Product filtering
- Shopping cart with browser persistence
- WhatsApp ordering for testing
- Newsletter capture interface
- Reseller information page
- **Reseller & Referral Programme prototype** in `reseller-program.html`

## Reseller programme prototype
Open `reseller-program.html` locally or through GitHub Pages to preview:
- Example reseller sales link and code
- Fixed per-product commission summary
- Once-off R50 referral reward after a qualifying reseller sale
- Monthly EFT payout workflow
- Demo-only sale and referral simulator

**Important:** The reseller programme page is a front-end prototype only. It stores demo state in the visitor's browser and does not create secure accounts, track real WooCommerce orders, or process payments/payouts. Do not use it to collect real personal or banking information.

## Planned production integrations
- WordPress + WooCommerce on WebPanda hosting
- Secure payment gateway
- Real order database/backend
- Transactional email
- Abandoned-cart automation
- Post-purchase follow-up
- Upsell/cross-sell campaigns
- Delivery/courier integration
- Server-side reseller attribution, commission records, refund reversals and monthly EFT reporting

Never place payment or email-service secrets in client-side JavaScript. GitHub Pages is for the static design/prototype; production WooCommerce functionality requires a server-side WordPress installation.
