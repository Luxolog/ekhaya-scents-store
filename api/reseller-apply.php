<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $config['site']['allowed_origins'] ?? [];
if (in_array($origin, $allowed, true)) {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'POST required']);
  exit;
}
if (!in_array($origin, $allowed, true)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Origin not allowed']);
  exit;
}
function respond(int $status, array $body): never {
  http_response_code($status);
  echo json_encode($body);
  exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) respond(400, ['ok'=>false,'error'=>'Invalid request.']);

$first = trim((string)($input['firstName'] ?? ''));
$last = trim((string)($input['lastName'] ?? ''));
$business = trim((string)($input['businessName'] ?? ''));
$email = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$phone = trim((string)($input['phone'] ?? ''));
$privacy = ($input['privacyAcknowledged'] ?? false) === true;
$version = trim((string)($input['privacyNoticeVersion'] ?? ''));
$marketing = ($input['marketingConsent'] ?? false) === true;

if ($first === '' || $last === '' || !$email || $phone === '' || !$privacy || $version === '') {
  respond(422, ['ok'=>false,'error'=>'Please complete all required fields and acknowledge the Privacy Notice.']);
}
if (mb_strlen($first)>100 || mb_strlen($last)>100 || mb_strlen($business)>160 || mb_strlen($phone)>30) {
  respond(422, ['ok'=>false,'error'=>'One or more fields are too long.']);
}
$now = date('Y-m-d H:i:s');
try {
  $pdo = new PDO(
    'mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset=utf8mb4',
    $config['db']['user'],
    $config['db']['pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
  );
  $stmt = $pdo->prepare("INSERT INTO reseller_applications
    (first_name,last_name,business_name,email,phone,status,privacy_notice_version,privacy_acknowledged_at,marketing_consent,marketing_consent_at)
    VALUES (?,?,?,?,?,'PENDING',?,?,?,?,?)");
  $stmt->execute([
    $first,$last,$business !== '' ? $business : null,$email,$phone,$version,$now,
    $marketing ? 1 : 0,$marketing ? $now : null
  ]);
  $applicationId = (int)$pdo->lastInsertId();
} catch (PDOException $e) {
  if (($e->errorInfo[1] ?? null) === 1062) {
    respond(409, ['ok'=>false,'error'=>'An application with this email address already exists. Please contact Ekhaya Scents if you need help.']);
  }
  error_log('Ekhaya reseller application DB error: '.$e->getMessage());
  respond(500, ['ok'=>false,'error'=>'We could not save your application. Please try again later.']);
}

$adminEmail = 'hello@ekhayascents.store';
$from = $config['mail']['from'] ?? 'hello@ekhayascents.store';
$fromName = $config['mail']['from_name'] ?? 'Ekhaya Scents';
$subject = 'New reseller application #'.$applicationId.' — approval required';
$body = '<h2>New Ekhaya Scents reseller application</h2>'
  .'<p><strong>Application:</strong> #'.htmlspecialchars((string)$applicationId, ENT_QUOTES, 'UTF-8').'</p>'
  .'<p><strong>Name:</strong> '.htmlspecialchars($first.' '.$last, ENT_QUOTES, 'UTF-8').'</p>'
  .'<p><strong>Business:</strong> '.htmlspecialchars($business !== '' ? $business : 'Not supplied', ENT_QUOTES, 'UTF-8').'</p>'
  .'<p><strong>Email:</strong> '.htmlspecialchars($email, ENT_QUOTES, 'UTF-8').'</p>'
  .'<p><strong>Phone:</strong> '.htmlspecialchars($phone, ENT_QUOTES, 'UTF-8').'</p>'
  .'<p><strong>Status:</strong> PENDING — approval required</p>'
  .'<p><strong>Privacy notice:</strong> '.htmlspecialchars($version, ENT_QUOTES, 'UTF-8').'</p>'
  .'<p><strong>Marketing opt-in:</strong> '.($marketing ? 'Yes' : 'No').'</p>'
  .'<p>Review this application in the Ekhaya Scents admin dashboard when available.</p>';
$headers = [
  'MIME-Version: 1.0',
  'Content-Type: text/html; charset=UTF-8',
  'From: '. $fromName .' <'. $from .'>',
  'Reply-To: '. $email,
  'X-Mailer: Ekhaya Scents'
];
$notified = @mail($adminEmail, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, implode("\r\n", $headers));

echo json_encode([
  'ok'=>true,
  'applicationId'=>$applicationId,
  'status'=>'PENDING',
  'notificationSent'=>$notified,
  'message'=>'Your application has been received and is awaiting approval.'
]);
