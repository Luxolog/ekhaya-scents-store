<?php
declare(strict_types=1);
$privateRoot = rtrim((string)($_SERVER['EKHAYA_PRIVATE_ROOT'] ?? getenv('EKHAYA_PRIVATE_ROOT') ?: dirname(__DIR__, 2)), '/');
require_once $privateRoot . '/src/Database.php';
require_once $privateRoot . '/src/Response.php';
require_once $privateRoot . '/src/Auth.php';
require_once $privateRoot . '/src/Mailer.php';
$configPath = $privateRoot . '/config.local.php';
if (!is_file($configPath)) Response::json(['ok'=>false,'error'=>'application_not_configured'],503);
$config=require $configPath;
$rateSecret=(string)($config['security']['rate_limit_secret'] ?? '');
if ($rateSecret !== '') putenv('EKHAYA_RATE_LIMIT_SECRET='.$rateSecret);
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
  $url=rtrim($config['app']['base_url'],'/').'/verify-email.html?token='.urlencode($token);
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
    $url=rtrim($config['app']['base_url'],'/').'/reset-password.html?token='.urlencode($token);
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
 if ($method==='GET' && preg_match('~/api/account/orders/?$~',$path)) {
  Auth::start(); $id=(int)($_SESSION['customer_id'] ?? 0); if (!$id) $json(['ok'=>false,'error'=>'authentication_required'],401);
  $q=$db()->prepare("SELECT o.id,o.order_number,o.status,o.currency,o.subtotal,o.discount_total,o.shipping_total,o.tax_total,o.grand_total,o.created_at,o.paid_at,o.completed_at FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.customer_id=? AND c.account_status='active' AND c.email_verified_at IS NOT NULL ORDER BY o.created_at DESC,o.id DESC LIMIT 50");
  $q->execute([$id]); $orders=$q->fetchAll();
  if (!$orders) {
   $active=$db()->prepare("SELECT id FROM customers WHERE id=? AND account_status='active' AND email_verified_at IS NOT NULL");
   $active->execute([$id]); if (!$active->fetchColumn()) $json(['ok'=>false,'error'=>'authentication_required'],401);
  }
  if ($orders) {
   $ids=array_map(static fn($o)=>(int)$o['id'],$orders); $marks=implode(',',array_fill(0,count($ids),'?'));
   $items=$db()->prepare("SELECT order_id,product_name_snapshot,sku_snapshot,quantity,unit_price,line_total FROM order_items WHERE order_id IN ($marks) ORDER BY id");
   $items->execute($ids); $byOrder=[];
   foreach ($items->fetchAll() as $item) { $byOrder[(int)$item['order_id']][]=$item; }
   foreach ($orders as &$order) { $order['items']=$byOrder[(int)$order['id']] ?? []; unset($order['id']); } unset($order);
  }
  $json(['ok'=>true,'orders'=>$orders]);
 }
 if ($method==='GET' && preg_match('~/api/account/addresses/?$~',$path)) {
  Auth::start(); $id=(int)($_SESSION['customer_id'] ?? 0); if (!$id) $json(['ok'=>false,'error'=>'authentication_required'],401);
  $q=$db()->prepare("SELECT a.id,a.address_type,a.first_name,a.last_name,a.company,a.phone,a.address_1,a.address_2,a.city,a.province,a.postcode,a.country_code,a.is_default FROM customer_addresses a JOIN customers c ON c.id=a.customer_id WHERE a.customer_id=? AND c.account_status='active' AND c.email_verified_at IS NOT NULL ORDER BY a.is_default DESC,a.id DESC");
  $q->execute([$id]); $addresses=$q->fetchAll();
  if (!$addresses) {
   $active=$db()->prepare("SELECT id FROM customers WHERE id=? AND account_status='active' AND email_verified_at IS NOT NULL");
   $active->execute([$id]); if (!$active->fetchColumn()) $json(['ok'=>false,'error'=>'authentication_required'],401);
  }
  $json(['ok'=>true,'addresses'=>$addresses]);
 }
 if ($method==='POST' && preg_match('~/api/account/addresses/save/?$~',$path)) {
  Auth::start(); Auth::verifyCsrf(); $id=(int)($_SESSION['customer_id'] ?? 0); if (!$id) $json(['ok'=>false,'error'=>'authentication_required'],401);
  $active=$db()->prepare("SELECT id FROM customers WHERE id=? AND account_status='active' AND email_verified_at IS NOT NULL");
  $active->execute([$id]); if (!$active->fetchColumn()) $json(['ok'=>false,'error'=>'authentication_required'],401);
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
    $update=$db()->prepare($sql);
    $update->execute([$type,...array_values($values),$country,$default,$addressId,$id]);
    if ($update->rowCount()===0) {
     $exists=$db()->prepare('SELECT id FROM customer_addresses WHERE id=? AND customer_id=? LIMIT 1');
     $exists->execute([$addressId,$id]);
     if (!$exists->fetchColumn()) throw new RuntimeException('Address not found for this account.');
    }
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
  $q=$db()->prepare("DELETE a FROM customer_addresses a JOIN customers c ON c.id=a.customer_id WHERE a.id=? AND a.customer_id=? AND c.account_status='active' AND c.email_verified_at IS NOT NULL"); $q->execute([$addressId,$id]);
  $json(['ok'=>true,'deleted'=>$q->rowCount()===1]);
 }
 // Reseller portal: every price response is guarded by the authenticated server-side approval state.
 $currentCustomer=static function() use ($db): array {
  Auth::start(); $id=(int)($_SESSION['customer_id'] ?? 0);
  if ($id<1) Response::json(['ok'=>false,'error'=>'authentication_required'],401);
  $q=$db()->prepare("SELECT id,email,first_name,last_name,account_status,email_verified_at FROM customers WHERE id=? LIMIT 1");
  $q->execute([$id]); $customer=$q->fetch();
  if (!$customer || $customer['account_status']!=='active' || empty($customer['email_verified_at'])) Response::json(['ok'=>false,'error'=>'authentication_required'],401);
  return $customer;
 };
 $adminCustomer=static function() use ($currentCustomer,$config): array {
  $customer=$currentCustomer(); $admins=array_map('strtolower',array_filter($config['security']['admin_emails'] ?? []));
  if (!$admins || !in_array(strtolower((string)$customer['email']),$admins,true)) Response::json(['ok'=>false,'error'=>'admin_access_required'],403);
  return $customer;
 };
 if ($method==='GET' && preg_match('~/api/reseller/status/?$~',$path)) {
  $customer=$currentCustomer();
  $q=$db()->prepare('SELECT id,reseller_type,status,referral_code,approved_at,created_at FROM reseller_accounts WHERE customer_id=? LIMIT 1');
  $q->execute([(int)$customer['id']]); $account=$q->fetch() ?: null;
  $json(['ok'=>true,'reseller'=>$account]);
 }
 if ($method==='POST' && preg_match('~/api/reseller/apply/?$~',$path)) {
  $customer=$currentCustomer(); Auth::verifyCsrf(); Auth::rateLimit($db(),'reseller_apply',(string)$customer['email'],3);
  $data=$body(); $type=(string)($data['reseller_type'] ?? '');
  if (!in_array($type,['referral_partner','stock_buyer'],true)) $json(['ok'=>false,'error'=>'invalid_reseller_type'],422);
  $existing=$db()->prepare('SELECT id,status FROM reseller_accounts WHERE customer_id=? LIMIT 1'); $existing->execute([(int)$customer['id']]);
  if ($existing->fetch()) $json(['ok'=>false,'error'=>'application_already_exists'],409);
  $db()->prepare("INSERT INTO reseller_accounts (customer_id,reseller_type,status) VALUES (?,?,'pending')")->execute([(int)$customer['id'],$type]);
  $id=(int)$db()->lastInsertId();
  $db()->prepare("INSERT INTO audit_logs (actor_customer_id,action,entity_type,entity_id,details) VALUES (?,?,?,?,?)")
    ->execute([(int)$customer['id'],'reseller.application_submitted','reseller_account',$id,json_encode(['reseller_type'=>$type],JSON_THROW_ON_ERROR)]);
  $json(['ok'=>true,'message'=>'application_submitted_pending_review','status'=>'pending'],201);
 }
 if ($method==='GET' && preg_match('~/api/reseller/catalog/?$~',$path)) {
  $customer=$currentCustomer();
  $q=$db()->prepare('SELECT reseller_type,status FROM reseller_accounts WHERE customer_id=? LIMIT 1'); $q->execute([(int)$customer['id']]); $account=$q->fetch();
  if (!$account || $account['status']!=='approved') $json(['ok'=>false,'error'=>'reseller_approval_required'],403);
  if ($account['reseller_type']!=='stock_buyer') $json(['ok'=>false,'error'=>'stock_buyer_access_required'],403);
  $json(['ok'=>true,'currency'=>'ZAR','products'=>[
   ['sku'=>'ORIGIN-30','name'=>'Origin perfume','size'=>'30ml','price'=>99.00,'available'=>true],
   ['sku'=>'ORIGIN-50','name'=>'Origin perfume','size'=>'50ml','price'=>135.00,'available'=>true],
   ['sku'=>'CAR-7','name'=>'Car perfume','size'=>'7ml','price'=>25.00,'available'=>true],
   ['sku'=>'HOME-DIFFUSER','name'=>'Reed diffuser','size'=>null,'price'=>null,'available'=>false,'stock_status'=>'out_of_stock'],
   ['sku'=>'HOME-LINEN','name'=>'Linen spray','size'=>null,'price'=>null,'available'=>false,'stock_status'=>'out_of_stock']
  ]]);
 }
 if ($method==='POST' && preg_match('~/api/reseller/orders/?$~',$path)) {
  $customer=$currentCustomer(); Auth::verifyCsrf(); Auth::rateLimit($db(),'reseller_order',(string)$customer['email'],8);
  $q=$db()->prepare("SELECT id,status,reseller_type FROM reseller_accounts WHERE customer_id=? LIMIT 1");
  $q->execute([(int)$customer['id']]); $account=$q->fetch();
  if (!$account || $account['status']!=='approved' || $account['reseller_type']!=='stock_buyer') $json(['ok'=>false,'error'=>'approved_stock_reseller_required'],403);
  $data=$body(); $items=$data['items'] ?? [];
  if (!is_array($items) || count($items)<1 || count($items)>20) $json(['ok'=>false,'error'=>'invalid_order_items'],422);
  $catalog=[
   'ORIGIN-30'=>['name'=>'Origin perfume 30ml','price'=>99.00,'available'=>true],
   'ORIGIN-50'=>['name'=>'Origin perfume 50ml','price'=>135.00,'available'=>true],
   'CAR-7'=>['name'=>'Car perfume 7ml','price'=>25.00,'available'=>true]
  ];
  $normal=[]; $subtotal=0.00;
  foreach($items as $item) {
   if (!is_array($item)) $json(['ok'=>false,'error'=>'invalid_order_item'],422);
   $sku=(string)($item['sku'] ?? ''); $qty=filter_var($item['quantity'] ?? null,FILTER_VALIDATE_INT);
   if (!isset($catalog[$sku]) || $qty===false || $qty<1 || $qty>100) $json(['ok'=>false,'error'=>'invalid_or_unavailable_product'],422);
   $price=$catalog[$sku]['price']; $line=round($price*$qty,2); $subtotal=round($subtotal+$line,2);
   $normal[]=['sku'=>$sku,'name'=>$catalog[$sku]['name'],'quantity'=>$qty,'price'=>$price,'line'=>$line];
  }
  $orderNumber='RS'.gmdate('ymd').strtoupper(bin2hex(random_bytes(4)));
  $db()->beginTransaction();
  try {
   $db()->prepare("INSERT INTO orders (order_number,customer_id,email,status,currency,subtotal,grand_total,customer_note) VALUES (?,?,?,'reseller_request_pending','ZAR',?,?,?)")
    ->execute([$orderNumber,(int)$customer['id'],(string)$customer['email'],$subtotal,$subtotal,substr(trim((string)($data['note'] ?? '')),0,1000) ?: null]);
   $orderId=(int)$db()->lastInsertId();
   $insert=$db()->prepare('INSERT INTO order_items (order_id,product_name_snapshot,sku_snapshot,quantity,unit_price,line_total,metadata) VALUES (?,?,?,?,?,?,?)');
   foreach($normal as $line) $insert->execute([$orderId,$line['name'],$line['sku'],$line['quantity'],$line['price'],$line['line'],json_encode(['reseller_account_id'=>(int)$account['id']],JSON_THROW_ON_ERROR)]);
   $db()->prepare('INSERT INTO audit_logs (actor_customer_id,action,entity_type,entity_id,details) VALUES (?,?,?,?,?)')
    ->execute([(int)$customer['id'],'reseller.order_request_submitted','order',$orderId,json_encode(['order_number'=>$orderNumber,'subtotal'=>$subtotal,'currency'=>'ZAR'],JSON_THROW_ON_ERROR)]);
   $db()->commit();
  } catch(Throwable $e) { if($db()->inTransaction()) $db()->rollBack(); throw $e; }
  $json(['ok'=>true,'message'=>'reseller_order_request_received','order_number'=>$orderNumber,'status'=>'reseller_request_pending','currency'=>'ZAR','subtotal'=>number_format($subtotal,2,'.',''),'items'=>$normal],201);
 }
 if ($method==='GET' && preg_match('~/api/admin/resellers/?$~',$path)) {
  $adminCustomer=$adminCustomer();
  $q=$db()->query("SELECT r.id,r.reseller_type,r.status,r.referral_code,r.created_at,r.approved_at,c.email,c.first_name,c.last_name,c.phone FROM reseller_accounts r JOIN customers c ON c.id=r.customer_id ORDER BY FIELD(r.status,'pending','approved','rejected','suspended'),r.created_at ASC LIMIT 200");
  $json(['ok'=>true,'resellers'=>$q->fetchAll()]);
 }
 if ($method==='POST' && preg_match('~/api/admin/resellers/review/?$~',$path)) {
  $adminCustomer=$adminCustomer(); Auth::verifyCsrf();
  $data=$body(); $id=(int)($data['reseller_id'] ?? 0); $decision=(string)($data['decision'] ?? '');
  if ($id<1 || !in_array($decision,['approve','reject','suspend'],true)) $json(['ok'=>false,'error'=>'invalid_review_request'],422);
  $status=['approve'=>'approved','reject'=>'rejected','suspend'=>'suspended'][$decision];
  $db()->beginTransaction();
  try {
   $q=$db()->prepare('SELECT id,status FROM reseller_accounts WHERE id=? FOR UPDATE'); $q->execute([$id]); $row=$q->fetch();
   if (!$row) { $db()->rollBack(); $json(['ok'=>false,'error'=>'reseller_not_found'],404); }
   $code=null;
   if ($decision==='approve') {
    $code='EKH'.strtoupper(bin2hex(random_bytes(4)));
    $db()->prepare('UPDATE reseller_accounts SET status=?,approved_at=UTC_TIMESTAMP(),referral_code=? WHERE id=?')->execute([$status,$code,$id]);
   } else {
    $db()->prepare('UPDATE reseller_accounts SET status=?,approved_at=NULL WHERE id=?')->execute([$status,$id]);
   }
   $db()->prepare('INSERT INTO audit_logs (actor_customer_id,action,entity_type,entity_id,details) VALUES (?,?,?,?,?)')
    ->execute([(int)$adminCustomer['id'],'reseller.application_'.$decision,'reseller_account',$id,json_encode(['previous_status'=>$row['status'],'new_status'=>$status],JSON_THROW_ON_ERROR)]);
   $db()->commit();
  } catch (Throwable $e) { if ($db()->inTransaction()) $db()->rollBack(); throw $e; }
  $json(['ok'=>true,'status'=>$status,'referral_code'=>$code]);
 }
 if ($method==='GET' && preg_match('~/api/categories/?$~',$path)) {
  $categories=$db()->query("SELECT c.id,c.name,c.slug,c.description,c.parent_id,c.sort_order,COUNT(DISTINCT pc.product_id) AS product_count FROM categories c LEFT JOIN product_categories pc ON pc.category_id=c.id LEFT JOIN products p ON p.id=pc.product_id AND p.status='published' WHERE c.is_active=1 GROUP BY c.id,c.name,c.slug,c.description,c.parent_id,c.sort_order ORDER BY c.sort_order,c.name")->fetchAll();
  $json(['ok'=>true,'categories'=>$categories]);
 }
 if ($method==='GET' && preg_match('~/api/products/?$~',$path)) {
  $category=trim((string)($_GET['category'] ?? ''));
  $search=trim((string)($_GET['search'] ?? ''));
  $limit=max(1,min(100,(int)($_GET['limit'] ?? 60)));
  $sql="SELECT DISTINCT p.id,p.sku,p.name,p.slug,p.product_type,p.short_description,p.regular_price,p.sale_price,p.stock_status,p.stock_quantity,pi.source_path AS image_path,(SELECT GROUP_CONCAT(CONCAT(c.id,':',c.name,':',c.slug) SEPARATOR '|') FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=p.id AND c.is_active=1) AS category_data FROM products p LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 WHERE p.status='published'";
  $params=[];
  if ($category!=='') { $sql.=" AND EXISTS (SELECT 1 FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=p.id AND c.slug=? AND c.is_active=1)"; $params[]=$category; }
  if ($search!=='') { $sql.=" AND (p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?)"; $term='%'.$search.'%'; array_push($params,$term,$term,$term); }
  $sql.=" ORDER BY p.created_at DESC,p.id DESC LIMIT ".$limit;
  $q=$db()->prepare($sql); $q->execute($params); $products=$q->fetchAll();
  foreach ($products as &$product) {
   $product['categories']=[];
   if (!empty($product['category_data'])) foreach (explode('|',$product['category_data']) as $part) {
    $bits=explode(':',$part,3);
    if (count($bits)===3) $product['categories'][]=['id'=>(int)$bits[0],'name'=>$bits[1],'slug'=>$bits[2]];
   }
   unset($product['category_data']);
  }
  unset($product);
  $json(['ok'=>true,'products'=>$products]);
 }
 if ($method==='GET' && preg_match('~/api/products/([a-zA-Z0-9-]+)/?$~',$path,$match)) {
  $slug=$match[1];
  $q=$db()->prepare("SELECT p.id,p.sku,p.name,p.slug,p.product_type,p.description,p.short_description,p.brand_line,p.regular_price,p.sale_price,p.stock_status,p.stock_quantity,p.weight_kg,p.requires_shipping FROM products p WHERE p.slug=? AND p.status='published' LIMIT 1");
  $q->execute([$slug]); $product=$q->fetch();
  if (!$product) $json(['ok'=>false,'error'=>'product_not_found'],404);
  $q=$db()->prepare('SELECT source_path,alt_text,sort_order,is_primary FROM product_images WHERE product_id=? ORDER BY is_primary DESC,sort_order,id');
  $q->execute([(int)$product['id']]); $product['images']=$q->fetchAll();
  $q=$db()->prepare('SELECT v.id,v.sku,v.option_values,v.regular_price,v.sale_price,v.stock_quantity,v.stock_status FROM product_variants v WHERE v.product_id=? ORDER BY v.id');
  $q->execute([(int)$product['id']]); $product['variants']=$q->fetchAll();
  $q=$db()->prepare('SELECT c.id,c.name,c.slug FROM categories c JOIN product_categories pc ON pc.category_id=c.id WHERE pc.product_id=? AND c.is_active=1 ORDER BY c.sort_order,c.name');
  $q->execute([(int)$product['id']]); $product['categories']=$q->fetchAll();
  $json(['ok'=>true,'product'=>$product]);
 }
 $json(['ok'=>false,'error'=>'not_found'],404);
} catch (Throwable $e) {
 error_log('[ekhaya-api] '.get_class($e).': '.$e->getMessage());
 Response::json(['ok'=>false,'error'=>'server_error'],500);
}
