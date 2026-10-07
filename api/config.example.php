<?php
// Copy to config.php on HOSTAFRICA. Do NOT commit config.php or real credentials to GitHub.
return [
  'db' => [
    'host' => 'localhost',
    'name' => 'EKHAYA_DB_NAME',
    'user' => 'EKHAYA_DB_USER',
    'pass' => 'EKHAYA_DB_PASSWORD',
  ],
  'mail' => [
    'from' => 'hello@ekhayascents.store',
    'from_name' => 'Ekhaya Scents',
    // The server-side mail() function uses HOSTAFRICA's mail transport.
    // If your HOSTAFRICA plan requires SMTP authentication, use the SMTP
    // settings supplied in your control panel and replace the mail transport.
  ],
  'payfast' => [
    'merchant_id' => 'YOUR_PAYFAST_MERCHANT_ID',
    'merchant_key' => 'YOUR_PAYFAST_MERCHANT_KEY',
    'passphrase' => 'YOUR_PAYFAST_PASSPHRASE',
    'sandbox' => true,
    'return_url' => 'https://ekhayascents.store/payment-success.html',
    'cancel_url' => 'https://ekhayascents.store/payment-cancelled.html',
    'notify_url' => 'https://ekhayascents.store/api/payfast-itn.php',
  ],
  'site' => [
    'base_url' => 'https://ekhayascents.store',
    'review_url' => 'https://g.page/r/Cc7UmlM_-9a5EBM/review',
  ],
];
