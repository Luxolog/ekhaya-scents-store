<?php
declare(strict_types=1);
return [
 'app'=>[
  'environment'=>getenv('EKHAYA_ENV') ?: 'staging',
  'base_url'=>getenv('EKHAYA_BASE_URL') ?: 'https://dev.ekhayascents.co.za',
  'timezone'=>'Africa/Johannesburg',
  'allowed_origins'=>array_filter(array_map('trim',explode(',',getenv('EKHAYA_ALLOWED_ORIGINS') ?: 'https://dev.ekhayascents.co.za'))),
 ],
 'security'=>['rate_limit_secret'=>getenv('EKHAYA_RATE_LIMIT_SECRET') ?: '',
  'admin_emails'=>array_filter(array_map('trim',explode(',',getenv('EKHAYA_ADMIN_EMAILS') ?: '')))],
 'mail'=>['from_address'=>getenv('EKHAYA_MAIL_FROM') ?: 'hello@ekhayascents.co.za','from_name'=>getenv('EKHAYA_MAIL_NAME') ?: 'Ekhaya Scents'],
 'database'=>[
  'host'=>getenv('EKHAYA_DB_HOST') ?: '127.0.0.1','port'=>getenv('EKHAYA_DB_PORT') ?: '3306',
  'name'=>getenv('EKHAYA_DB_NAME') ?: '','user'=>getenv('EKHAYA_DB_USER') ?: '',
  'password'=>getenv('EKHAYA_DB_PASS') ?: '',
 ],
 'payfast'=>[
  'environment'=>getenv('PAYFAST_ENV') ?: 'sandbox',
  'merchant_id'=>getenv('PAYFAST_MERCHANT_ID') ?: '',
  'merchant_key'=>getenv('PAYFAST_MERCHANT_KEY') ?: '',
  'passphrase'=>getenv('PAYFAST_PASSPHRASE') ?: '',
 ],
];
