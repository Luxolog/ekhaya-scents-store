<?php
// Copy to config.php on the hosting account. Never commit real credentials.
return [
  'db' => [
    'host' => 'localhost',
    'name' => 'robogirl_ekhaya',
    'user' => 'robogirl_ekhaya_api',
    'pass' => 'REPLACE_WITH_DATABASE_PASSWORD',
  ],
  'payfast' => [
    'merchant_id' => 'YOUR_SANDBOX_MERCHANT_ID',
    'merchant_key' => 'YOUR_SANDBOX_MERCHANT_KEY',
    'passphrase' => 'YOUR_SANDBOX_PASSPHRASE',
    'sandbox' => true,
    'return_url' => 'https://dev.ekhayascents.co.za/payment-success.html',
    'cancel_url' => 'https://dev.ekhayascents.co.za/payment-cancelled.html',
    'notify_url' => 'https://dev.ekhayascents.co.za/api/payfast-itn.php',
  ],
  'site' => [
    'allowed_origins' => [
      'https://luxolog.github.io',
      'https://dev.ekhayascents.co.za',
      'https://ekhayascents.co.za',
    ],
  ],
];
