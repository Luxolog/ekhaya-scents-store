<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/Database.php';
require_once dirname(__DIR__, 2) . '/src/Response.php';
require_once dirname(__DIR__, 2) . '/src/Auth.php';
require_once dirname(__DIR__, 2) . '/src/Mailer.php';
$configPath = dirname(__DIR__, 2) . '/config.local.php';
if (!is_file($configPath)) Response::json(['ok'=>false,'error'=>'application_not_configured'],503);
$config=require $configPath;
date_default_timezone_set($config['app']['timezone'] ?? 'Africa/Johannesburg');
$origin=$_SERVER['HTTP_ORIGIN'] ?? '';
$allowed=$config['app']['allowed_origins'] ?? [];
if ($origin!=='' && in_array($origin,$allowed,true)) {
 header('Access-Control-Allow-Origin: '.$origin); header('Access-Control-Allow-Credentials: true'); header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin'); header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='OPTIONS') { http_response_code(204); exit; }
$path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
 $pdo=null;
 $db=static function() use (&$pdo,$config): PDO { if (!$pdo) $pdo=Database::connection($config); return $pdo; };
 $body=static function(): array { $data=json_decode(file_get_contents('php://input') ?: '{}',true); return is_array($data)?$data:[]; };
 $json=static function(array $data,int $status=200): never { Response::json($data,$status); };
 if ($method==='GET' && preg_match('~/api/?$~',$path)) $json(['ok'=>true,'service'=>'ekhaya-scents-api','environment'=>$config['app']['environment'] ?? 'staging','version'=>'0.3.0']);
 if ($method==='GET' && preg_match('~/api/health/?$~',$path)) { $db()->query('SELECT 1'); $json(['ok'=>true,'database'=>'connected','time'=>gmdate('c')]); }
 if ($method==='GET' && preg_match('~/api/auth/csrf/?$~',$path)) { Auth::start(); $json(['ok'=>true,'csrf_token'=>Auth::csrfToken()]); }
 if ($method==='POST' && preg_match('~/api/auth/register/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $data=$body();
  $email=strtolower(trim((string)($data['email'] ?? ''))); $password=(string)($data['password'] ?? '');
  $first=trim((string)($data['first_name'] ?? '')); $last=trim((string)($data['last_name'] ?? ''));
  if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($password)<12 || strlen($password)>200 || strlen($first)>100 || strlen($last)>100) $json(['ok'=>false,'error'=>'invalid_registration_details'],422);
  Auth::rateLimit($db(),'register',$email,5);
  try { $db()->prepare('INSERT INTO customers (email,password_hash,first_name,last_name,account_status) VALUES (?,?,?,?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),$first ?: null,$last ?: null,'pending']); }
  catch (PDOException $e) { if ($e->getCode()==='23000') $json(['ok'=>false,'error'=>'account_already_exists'],409); throw $e; }
  $customerId=(int)$db()->lastInsertId(); $token=bin2hex(random_bytes(32));
  $db()->prepare('INSERT INTO email_verification_tokens (customer_id,token_hash,expires_at) VALUES (?,?,?)')->execute([$customerId,hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+86400)]);
  $url=rtrim($config['app']['base_url'],'/').'/verify-email?token='.urlencode($token);
  Mailer::send($config,$email,'Verify your Ekhaya Scents account',Mailer::verificationHtml($first,$url));
  $json(['ok'=>true,'message'=>'account_created_verification_sent'],201);
 }
 if ($method==='POST' && preg_match('~/api/auth/verify-email/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $token=(string)($body()['token'] ?? '');
  if (!preg_match('/^[a-f0-9]{64}$/',$token)) $json(['ok'=>false,'error'=>'invalid_or_expired_token'],400);
  $q=$db()->prepare('SELECT customer_id FROM email_verification_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() LIMIT 1');
  $q->execute([hash('sha256',$token)]); $row=$q->fetch();
  if (!$row) $json(['ok'=>false,'error'=>'invalid_or_expired_token'],400);
  $db()->beginTransaction();
  try {
   $db()->prepare("UPDATE customers SET email_verified_at=UTC_TIMESTAMP(),account_status='active' WHERE id=? AND account_status='pending'")->execute([(int)$row['customer_id']]);
   $db()->prepare('UPDATE email_verification_tokens SET used_at=UTC_TIMESTAMP() WHERE customer_id=? AND used_at IS NULL')->execute([(int)$row['customer_id']]);
   $db()->commit();
  } catch (Throwable $e) { if ($db()->inTransaction()) $db()->rollBack(); throw $e; }
  $json(['ok'=>true,'message'=>'email_verified_account_activated']);
 }
 if ($method==='POST' && preg_match('~/api/auth/password/forgot/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $email=strtolower(trim((string)($body()['email'] ?? '')));
  Auth::rateLimit($db(),'password_forgot',$email,5);
  if (filter_var($email,FILTER_VALIDATE_EMAIL)) {
   $q=$db()->prepare('SELECT id,first_name FROM customers WHERE email=? LIMIT 1'); $q->execute([$email]); $customer=$q->fetch();
   if ($customer) {
    $token=bin2hex(random_bytes(32));
    $db()->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE customer_id=? AND used_at IS NULL')->execute([(int)$customer['id']]);
    $db()->prepare('INSERT INTO password_reset_tokens (customer_id,token_hash,expires_at) VALUES (?,?,?)')->execute([(int)$customer['id'],hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+3600)]);
    $url=rtrim($config['app']['base_url'],'/').'/reset-password?token='.urlencode($token);
    Mailer::send($config,$email,'Reset your Ekhaya Scents password',Mailer::resetHtml((string)$customer['first_name'],$url));
   }
  }
  $json(['ok'=>true,'message'=>'if_account_exists_reset_email_sent']);
 }
 if ($method==='POST' && preg_match('~/api/auth/password/reset/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $data=$body(); $token=(string)($data['token'] ?? ''); $password=(string)($data['password'] ?? '');
  if (!preg_match('/^[a-f0-9]{64}$/',$token) || strlen($password)<12 || strlen($password)>200) $json(['ok'=>false,'error'=>'invalid_reset_details'],422);
  $q=$db()->prepare('SELECT customer_id FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() LIMIT 1');
  $q->execute([hash('sha256',$token)]); $row=$q->fetch();
  if (!$row) $json(['ok'=>false,'error'=>'invalid_or_expired_token'],400);
  $db()->beginTransaction();
  try {
   $db()->prepare('UPDATE customers SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$row['customer_id']]);
   $db()->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE customer_id=? AND used_at IS NULL')->execute([(int)$row['customer_id']]);
   $db()->commit();
  } catch (Throwable $e) { if ($db()->inTransaction()) $db()->rollBack(); throw $e; }
  $json(['ok'=>true,'message'=>'password_reset']);
 }
 if ($method==='POST' && preg_match('~/api/auth/login/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $data=$body();
  $email=strtolower(trim((string)($data['email'] ?? ''))); $password=(string)($data['password'] ?? '');
  Auth::rateLimit($db(),'login',$email,8);
  $q=$db()->prepare('SELECT id,email,password_hash,first_name,last_name,account_status,email_verified_at FROM customers WHERE email=? LIMIT 1');
  $q->execute([$email]); $customer=$q->fetch();
  if (!$customer || !$customer['password_hash'] || !password_verify($password,$customer['password_hash'])) $json(['ok'=>false,'error'=>'invalid_email_or_password'],401);
  if ($customer['account_status']!=='active' || empty($customer['email_verified_at'])) $json(['ok'=>false,'error'=>'account_not_active'],403);
  session_regenerate_id(true); $_SESSION['customer_id']=(int)$customer['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
  $json(['ok'=>true,'customer'=>['id'=>(int)$customer['id'],'email'=>$customer['email'],'first_name'=>$customer['first_name'],'last_name'=>$customer['last_name']],'csrf_token'=>$_SESSION['csrf']]);
 }
 if ($method==='GET' && preg_match('~/api/auth/me/?$~',$path)) {
  Auth::start(); $id=(int)($_SESSION['customer_id'] ?? 0); if (!$id) $json(['ok'=>true,'customer'=>null]);
  $q=$db()->prepare('SELECT id,email,first_name,last_name,account_status,email_verified_at FROM customers WHERE id=?'); $q->execute([$id]); $customer=$q->fetch();
  if (!$customer || $customer['account_status']!=='active' || empty($customer['email_verified_at'])) $json(['ok'=>true,'customer'=>null]);
  unset($customer['email_verified_at']); $json(['ok'=>true,'customer'=>$customer]);
 }
 if ($method==='POST' && preg_match('~/api/auth/logout/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $_SESSION=[];
  if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']); }
  session_destroy(); $json(['ok'=>true]);
 }
 if ($method==='GET' && preg_match('~/api/account/profile/?$~',$path)) {
  Auth::start(); $id=(int)($_SESSION['customer_id'] ?? 0); if (!$id) $json(['ok'=>false,'error'=>'authentication_required'],401);
  $q=$db()->prepare('SELECT id,email,first_name,last_name,phone,created_at FROM customers WHERE id=? AND account_status=\'active\' AND email_verified_at IS NOT NULL');
  $q->execute([$id]); $customer=$q->fetch(); if (!$customer) $json(['ok'=>false,'error'=>'authentication_required'],401);
  $json(['ok'=>true,'customer'=>$customer]);
 }
 if ($method==='POST' && preg_match('~/api/account/profile/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $id=(int)($_SESSION['customer_id'] ?? 0); if (!$id) $json(['ok'=>false,'error'=>'authentication_required'],401);
  $data=$body(); $first=trim((string)($data['first_name'] ?? '')); $last=trim((string)($data['last_name'] ?? '')); $phone=trim((string)($data['phone'] ?? ''));
  if (strlen($first)>100 || strlen($last)>100 || strlen($phone)>40) $json(['ok'=>false,'error'=>'invalid_profile_details'],422);
  $q=$db()->prepare("UPDATE customers SET first_name=?,last_name=?,phone=? WHERE id=? AND account_status='active' AND email_verified_at IS NOT NULL");
  $q->execute([$first ?: null,$last ?: null,$phone ?: null,$id]);
  $json(['ok'=>true,'message'=>'profile_updated']);
 }
 if ($method==='GET' && preg_match('~/api/account/addresses/?$~',$path)) {
  Auth::start(); $id=(int)($_SESSION['customer_id'] ?? 0); if (!$id) $json(['ok'=>false,'error'=>'authentication_required'],401);
  $q=$db()->prepare('SELECT id,address_type,first_name,last_name,company,phone,address_1,address_2,city,province,postcode,country_code,is_default FROM customer_addresses WHERE customer_id=? ORDER BY is_default DESC,id DESC');
  $q->execute([$id]); $json(['ok'=>true,'addresses'=>$q->fetchAll()]);
 }
 if ($method==='POST' && preg_match('~/api/account/addresses/save/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $id=(int)($_SESSION['customer_id'] ?? 0); if (!$id) $json(['ok'=>false,'error'=>'authentication_required'],401);
  $d=$body(); $addressId=(int)($d['id'] ?? 0); $type=(string)($d['address_type'] ?? 'shipping');
  $allowedTypes=['billing','shipping','other'];
  $fields=['first_name'=>100,'last_name'=>100,'company'=>150,'phone'=>40,'address_1'=>190,'address_2'=>190,'city'=>100,'province'=>100,'postcode'=>20];
  if (!in_array($type,$allowedTypes,true)) $json(['ok'=>false,'error'=>'invalid_address_type'],422);
  $values=[]; foreach ($fields as $key=>$max) { $v=trim((string)($d[$key] ?? '')); if (strlen($v)>$max) $json(['ok'=>false,'error'=>'invalid_address_details'],422); $values[$key]=$v ?: null; }
  if (!$values['first_name'] || !$values['last_name'] || !$values['address_1'] || !$values['city'] || !$values['province'] || !$values['postcode']) $json(['ok'=>false,'error'=>'required_address_fields_missing'],422);
  $country=strtoupper(trim((string)($d['country_code'] ?? 'ZA'))); if (!preg_match('/^[A-Z]{2}$/',$country)) $json(['ok'=>false,'error'=>'invalid_country_code'],422);
  $default=!empty($d['is_default']) ? 1 : 0;
  $db()->beginTransaction();
  try {
   if ($default) $db()->prepare('UPDATE customer_addresses SET is_default=0 WHERE customer_id=? AND address_type=?')->execute([$id,$type]);
   if ($addressId>0) {
    $sql='UPDATE customer_addresses SET address_type=?,first_name=?,last_name=?,company=?,phone=?,address_1=?,address_2=?,city=?,province=?,postcode=?,country_code=?,is_default=? WHERE id=? AND customer_id=?';
    $db()->prepare($sql)->execute([$type,...array_values($values),$country,$default,$addressId,$id]);
    if ($db()->query('SELECT ROW_COUNT()')->fetchColumn()===false) throw new RuntimeException('Address update failed.');
   } else {
    $sql='INSERT INTO customer_addresses (customer_id,address_type,first_name,last_name,company,phone,address_1,address_2,city,province,postcode,country_code,is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $db()->prepare($sql)->execute([$id,$type,...array_values($values),$country,$default]);
   }
   $db()->commit();
  } catch (Throwable $e) { if ($db()->inTransaction()) $db()->rollBack(); throw $e; }
  $json(['ok'=>true,'message'=>'address_saved']);
 }
 if ($method==='POST' && preg_match('~/api/account/addresses/delete/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $id=(int)($_SESSION['customer_id'] ?? 0); if (!$id) $json(['ok'=>false,'error'=>'authentication_required'],401);
  $addressId=(int)($body()['id'] ?? 0); if ($addressId<1) $json(['ok'=>false,'error'=>'invalid_address_id'],422);
  $q=$db()->prepare('DELETE FROM customer_addresses WHERE id=? AND customer_id=?'); $q->execute([$addressId,$id]);
  $json(['ok'=>true,'deleted'=>$q->rowCount()===1]);
 }
 if ($method==='GET' && preg_match('~/api/products/?$~',$path)) {
  $products=$db()->query("SELECT p.id,p.sku,p.name,p.slug,p.product_type,p.short_description,p.regular_price,p.sale_price,p.stock_status,p.stock_quantity FROM products p WHERE p.status='published' ORDER BY p.created_at DESC,p.id DESC LIMIT 100")->fetchAll();
  $json(['ok'=>true,'products'=>$products]);
 }
 $json(['ok'=>false,'error'=>'not_found'],404);
} catch (Throwable $e) {
 error_log('[ekhaya-api] '.get_class($e).': '.$e->getMessage());
 Response::json(['ok'=>false,'error'=>'server_error'],500);
}
