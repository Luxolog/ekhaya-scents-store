<?php
declare(strict_types=1);

// Payfast ITN endpoint.
// IMPORTANT: this file is a starting point and must be completed with your
// production database/order verification before live payments are enabled.
// Payfast requires signature, source-IP, amount and data validation checks.

$config = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('POST required');
}

$data = $_POST;
if (!$data) {
  http_response_code(400);
  exit('No ITN data');
}

$receivedSignature = (string)($data['signature'] ?? '');
unset($data['signature']);

$signatureString = '';
foreach ($data as $key => $value) {
  if ($value === '' || $value === null) continue;
  $signatureString .= $key . '=' . urlencode(trim((string)$value)) . '&';
}
$signatureString = rtrim($signatureString, '&');
if ($config['payfast']['passphrase'] !== '') {
  $signatureString .= '&passphrase=' . urlencode(trim($config['payfast']['passphrase']));
}
$expectedSignature = md5($signatureString);

if (!hash_equals($expectedSignature, $receivedSignature)) {
  http_response_code(400);
  exit('Invalid signature');
}

// TODO production: verify Payfast source IP, validate amount against the
// server-side order, then mark the order paid and trigger confirmation email.
http_response_code(200);
echo 'OK';
