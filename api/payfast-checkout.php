<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://ekhayascents.store');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$config = require __DIR__ . '/config.php';
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$amount = (float)($input['amount'] ?? 0);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$name = trim((string)($input['name'] ?? ''));
$description = trim((string)($input['description'] ?? 'Ekhaya Scents order'));

if ($amount <= 0 || !$email) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'Valid amount and email are required']);
  exit;
}

$merchantId = $config['payfast']['merchant_id'];
$merchantKey = $config['payfast']['merchant_key'];
$passphrase = $config['payfast']['passphrase'];
$sandbox = (bool)$config['payfast']['sandbox'];

$endpoint = $sandbox
  ? 'https://sandbox.payfast.co.za/eng/process'
  : 'https://www.payfast.co.za/eng/process';

$orderId = 'ES-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));

$data = [
  'merchant_id' => $merchantId,
  'merchant_key' => $merchantKey,
  'return_url' => $config['payfast']['return_url'],
  'cancel_url' => $config['payfast']['cancel_url'],
  'notify_url' => $config['payfast']['notify_url'],
  'name_first' => $name,
  'email_address' => $email,
  'm_payment_id' => $orderId,
  'amount' => number_format($amount, 2, '.', ''),
  'item_name' => mb_substr($description, 0, 100),
];

$signatureString = '';
foreach ($data as $key => $value) {
  if ($value === '' || $value === null) continue;
  $signatureString .= $key . '=' . urlencode(trim((string)$value)) . '&';
}
$signatureString = rtrim($signatureString, '&');
if ($passphrase !== '') {
  $signatureString .= '&passphrase=' . urlencode(trim($passphrase));
}
$data['signature'] = md5($signatureString);

echo json_encode([
  'ok' => true,
  'endpoint' => $endpoint,
  'fields' => $data,
]);
