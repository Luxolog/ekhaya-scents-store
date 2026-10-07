<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://ekhayascents.store');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$config = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'POST required']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$to = filter_var($input['to'] ?? '', FILTER_VALIDATE_EMAIL);
$subject = trim((string)($input['subject'] ?? ''));
$html = (string)($input['html'] ?? '');

if (!$to || $subject === '' || $html === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'Recipient, subject and message are required']);
  exit;
}

// Keep the From address fixed. The browser can never choose a different sender.
$from = $config['mail']['from'];
$fromName = $config['mail']['from_name'];
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/html; charset=UTF-8';
$headers[] = 'From: ' . $fromName . ' <' . $from . '>';
$headers[] = 'Reply-To: ' . $from;
$headers[] = 'X-Mailer: Ekhaya Scents';

$ok = mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, implode("\r\n", $headers));
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Mail transport rejected the message']);
  exit;
}
echo json_encode(['ok'=>true]);
