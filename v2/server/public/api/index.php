<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Database.php';
require_once dirname(__DIR__, 2) . '/src/Response.php';
require_once dirname(__DIR__, 2) . '/src/Auth.php';
require_once dirname(__DIR__, 2) . '/src/Mailer.php';

$configPath = dirname(__DIR__, 2) . '/config.local.php';
if (!is_file($configPath)) {
    Response::json(['ok'=>false,'error'=>'application_not_configured'], 503);
}
$config = require $configPath;
date_default_timezone_set($config['app']['timezone'] ?? 'Africa/Johannesburg');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $config['app']['allowed_origins'] ?? [];
if ($origin !== '' && in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $pdo = null;
    $db = static function () use (&$pdo, $config): PDO {
        if (!$pdo) $pdo = Database::connection($config);
        return $pdo;
    };
    $body = static function (): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        return is_array($data) ? $data : [];
    };
    $json = static function (array $data, int $status = 200): never {
        Response::json($data, $status);
    };

    if ($method === 'GET' && preg_match('~/api/?$~', $path)) {
        $json(['ok'=>true,'service'=>'ekhaya-scents-api','environment'=>$config['app']['environment'] ?? 'staging','version'=>'0.2.0']);
    }
    if ($method === 'GET' && preg_match('~/api/health/?$~', $path)) {
        $db()->query('SELECT 1');
        $json(['ok'=>true,'database'=>'connected','time'=>gmdate('c')]);
    }
    if ($method === 'GET' && preg_match('~/api/auth/csrf/?$~', $path)) {
        Auth::start();
        $json(['ok'=>true,'csrf_token'=>Auth::csrfToken()]);
    }
    if ($method === 'POST' && preg_match('~/api/auth/register/?$~', $path)) {
        Auth::start(); Auth::verifyCsrf();
        $data = $body();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $first = trim((string)($data['first_name'] ?? ''));
        $last = trim((string)($data['last_name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || strlen($password) < 12 || strlen($password) > 200) {
            $json(['ok'=>false,'error'=>'invalid_registration_details'], 422);
        }
        if (strlen($first) > 100 || strlen($last) > 100) $json(['ok'=>false,'error'=>'invalid_registration_details'], 422);
        Auth::rateLimit($db(), 'register', $email, 5);
        $stmt = $db()->prepare('INSERT INTO customers (email,password_hash,first_name,last_name,account_status) VALUES (?,?,?,?,?)');
        try {
            $stmt->execute([$email,password_hash($password, PASSWORD_DEFAULT),$first ?: null,$last ?: null,'pending']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') $json(['ok'=>false,'error'=>'account_already_exists'], 409);
            throw $e;
        }
        $customerId = (int)$db()->lastInsertId();
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires = gmdate('Y-m-d H:i:s', time() + 86400);
        $db()->prepare('INSERT INTO email_verification_tokens (customer_id,token_hash,expires_at) VALUES (?,?,?)')->execute([$customerId,$tokenHash,$expires]);
        $verifyUrl = rtrim($config['app']['base_url'], '/') . '/verify-email?token=' . urlencode($rawToken);
        Mailer::send($config, $email, 'Verify your Ekhaya Scents account', Mailer::verificationHtml($first, $verifyUrl));
        $json(['ok'=>true,'message'=>'account_created_verification_sent'], 201);
    }
    if ($method === 'POST' && preg_match('~/api/auth/verify-email/?
        Auth::start(); Auth::verifyCsrf();
        $data = $body();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        Auth::rateLimit($db(), 'login', $email, 8);
        $stmt = $db()->prepare('SELECT id,email,password_hash,first_name,last_name,account_status FROM customers WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
        if (!$customer || !$customer['password_hash'] || !password_verify($password, $customer['password_hash'])) {
            $json(['ok'=>false,'error'=>'invalid_email_or_password'], 401);
        }
        if ($customer['account_status'] !== 'active' || empty($customer['email_verified_at'])) {
            $json(['ok'=>false,'error'=>'account_not_active'], 403);
        }
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)$customer['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $json(['ok'=>true,'customer'=>['id'=>(int)$customer['id'],'email'=>$customer['email'],'first_name'=>$customer['first_name'],'last_name'=>$customer['last_name']],'csrf_token'=>$_SESSION['csrf']]);
    }
    if ($method === 'GET' && preg_match('~/api/auth/me/?$', $path)) {
        Auth::start();
        $id = (int)($_SESSION['customer_id'] ?? 0);
        if (!$id) $json(['ok'=>true,'customer'=>null]);
        $stmt = $db()->prepare('SELECT id,email,first_name,last_name,account_status FROM customers WHERE id=?');
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        if (!$customer || $customer['account_status'] !== 'active' || empty($customer['email_verified_at'])) $json(['ok'=>true,'customer'=>null]);
        $json(['ok'=>true,'customer'=>$customer]);
    }
    if ($method === 'POST' && preg_match('~/api/auth/logout/?$', $path)) {
        Auth::start(); Auth::verifyCsrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']);
        }
        session_destroy();
        $json(['ok'=>true]);
    }
    if ($method === 'GET' && preg_match('~/api/products/?$', $path)) {
        $products = $db()->query("SELECT p.id,p.sku,p.name,p.slug,p.product_type,p.short_description,p.regular_price,p.sale_price,p.stock_status,p.stock_quantity FROM products p WHERE p.status='published' ORDER BY p.created_at DESC,p.id DESC LIMIT 100")->fetchAll();
        $json(['ok'=>true,'products'=>$products]);
    }
    $json(['ok'=>false,'error'=>'not_found'], 404);
} catch (Throwable $e) {
    error_log('[ekhaya-api] ' . get_class($e) . ': ' . $e->getMessage());
    Response::json(['ok'=>false,'error'=>'server_error'], 500);
}
, $path)) {
        Auth::start(); Auth::verifyCsrf();
        $rawToken = (string)($body()['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) $json(['ok'=>false,'error'=>'invalid_or_expired_token'], 400);
        $stmt = $db()->prepare('SELECT customer_id FROM email_verification_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() LIMIT 1');
        $stmt->execute([hash('sha256',$rawToken)]); $token = $stmt->fetch();
        if (!$token) $json(['ok'=>false,'error'=>'invalid_or_expired_token'], 400);
        $db()->beginTransaction();
        try {
            $db()->prepare("UPDATE customers SET email_verified_at=UTC_TIMESTAMP(),account_status='active' WHERE id=? AND account_status='pending'")->execute([(int)$token['customer_id']]);
            $db()->prepare('UPDATE email_verification_tokens SET used_at=UTC_TIMESTAMP() WHERE customer_id=? AND used_at IS NULL')->execute([(int)$token['customer_id']]);
            $db()->commit();
        } catch (Throwable $e) { if ($db()->inTransaction()) $db()->rollBack(); throw $e; }
        $json(['ok'=>true,'message'=>'email_verified_account_activated']);
    }
    if ($method === 'POST' && preg_match('~/api/auth/password/forgot/?
        Auth::start(); Auth::verifyCsrf();
        $data = $body();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        Auth::rateLimit($db(), 'login', $email, 8);
        $stmt = $db()->prepare('SELECT id,email,password_hash,first_name,last_name,account_status FROM customers WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
        if (!$customer || !$customer['password_hash'] || !password_verify($password, $customer['password_hash'])) {
            $json(['ok'=>false,'error'=>'invalid_email_or_password'], 401);
        }
        if ($customer['account_status'] !== 'active') {
            $json(['ok'=>false,'error'=>'account_not_active'], 403);
        }
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)$customer['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $json(['ok'=>true,'customer'=>['id'=>(int)$customer['id'],'email'=>$customer['email'],'first_name'=>$customer['first_name'],'last_name'=>$customer['last_name']],'csrf_token'=>$_SESSION['csrf']]);
    }
    if ($method === 'GET' && preg_match('~/api/auth/me/?$', $path)) {
        Auth::start();
        $id = (int)($_SESSION['customer_id'] ?? 0);
        if (!$id) $json(['ok'=>true,'customer'=>null]);
        $stmt = $db()->prepare('SELECT id,email,first_name,last_name,account_status FROM customers WHERE id=?');
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        if (!$customer || $customer['account_status'] !== 'active') $json(['ok'=>true,'customer'=>null]);
        $json(['ok'=>true,'customer'=>$customer]);
    }
    if ($method === 'POST' && preg_match('~/api/auth/logout/?$', $path)) {
        Auth::start(); Auth::verifyCsrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']);
        }
        session_destroy();
        $json(['ok'=>true]);
    }
    if ($method === 'GET' && preg_match('~/api/products/?$', $path)) {
        $products = $db()->query("SELECT p.id,p.sku,p.name,p.slug,p.product_type,p.short_description,p.regular_price,p.sale_price,p.stock_status,p.stock_quantity FROM products p WHERE p.status='published' ORDER BY p.created_at DESC,p.id DESC LIMIT 100")->fetchAll();
        $json(['ok'=>true,'products'=>$products]);
    }
    $json(['ok'=>false,'error'=>'not_found'], 404);
} catch (Throwable $e) {
    error_log('[ekhaya-api] ' . get_class($e) . ': ' . $e->getMessage());
    Response::json(['ok'=>false,'error'=>'server_error'], 500);
}
, $path)) {
        Auth::start(); Auth::verifyCsrf();
        $email = strtolower(trim((string)($body()['email'] ?? '')));
        Auth::rateLimit($db(), 'password_forgot', $email, 5);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $db()->prepare('SELECT id,first_name FROM customers WHERE email=? LIMIT 1');
            $stmt->execute([$email]); $customer = $stmt->fetch();
            if ($customer) {
                $rawToken = bin2hex(random_bytes(32));
                $db()->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE customer_id=? AND used_at IS NULL')->execute([(int)$customer['id']]);
                $db()->prepare('INSERT INTO password_reset_tokens (customer_id,token_hash,expires_at) VALUES (?,?,?)')->execute([(int)$customer['id'],hash('sha256',$rawToken),gmdate('Y-m-d H:i:s',time()+3600)]);
                $resetUrl = rtrim($config['app']['base_url'], '/') . '/reset-password?token=' . urlencode($rawToken);
                Mailer::send($config,$email,'Reset your Ekhaya Scents password',Mailer::resetHtml((string)$customer['first_name'],$resetUrl));
            }
        }
        $json(['ok'=>true,'message'=>'if_account_exists_reset_email_sent']);
    }
    if ($method === 'POST' && preg_match('~/api/auth/password/reset/?
        Auth::start(); Auth::verifyCsrf();
        $data = $body();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        Auth::rateLimit($db(), 'login', $email, 8);
        $stmt = $db()->prepare('SELECT id,email,password_hash,first_name,last_name,account_status FROM customers WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
        if (!$customer || !$customer['password_hash'] || !password_verify($password, $customer['password_hash'])) {
            $json(['ok'=>false,'error'=>'invalid_email_or_password'], 401);
        }
        if ($customer['account_status'] !== 'active') {
            $json(['ok'=>false,'error'=>'account_not_active'], 403);
        }
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)$customer['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $json(['ok'=>true,'customer'=>['id'=>(int)$customer['id'],'email'=>$customer['email'],'first_name'=>$customer['first_name'],'last_name'=>$customer['last_name']],'csrf_token'=>$_SESSION['csrf']]);
    }
    if ($method === 'GET' && preg_match('~/api/auth/me/?$', $path)) {
        Auth::start();
        $id = (int)($_SESSION['customer_id'] ?? 0);
        if (!$id) $json(['ok'=>true,'customer'=>null]);
        $stmt = $db()->prepare('SELECT id,email,first_name,last_name,account_status FROM customers WHERE id=?');
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        if (!$customer || $customer['account_status'] !== 'active') $json(['ok'=>true,'customer'=>null]);
        $json(['ok'=>true,'customer'=>$customer]);
    }
    if ($method === 'POST' && preg_match('~/api/auth/logout/?$', $path)) {
        Auth::start(); Auth::verifyCsrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']);
        }
        session_destroy();
        $json(['ok'=>true]);
    }
    if ($method === 'GET' && preg_match('~/api/products/?$', $path)) {
        $products = $db()->query("SELECT p.id,p.sku,p.name,p.slug,p.product_type,p.short_description,p.regular_price,p.sale_price,p.stock_status,p.stock_quantity FROM products p WHERE p.status='published' ORDER BY p.created_at DESC,p.id DESC LIMIT 100")->fetchAll();
        $json(['ok'=>true,'products'=>$products]);
    }
    $json(['ok'=>false,'error'=>'not_found'], 404);
} catch (Throwable $e) {
    error_log('[ekhaya-api] ' . get_class($e) . ': ' . $e->getMessage());
    Response::json(['ok'=>false,'error'=>'server_error'], 500);
}
, $path)) {
        Auth::start(); Auth::verifyCsrf();
        $data=$body(); $rawToken=(string)($data['token'] ?? ''); $password=(string)($data['password'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/',$rawToken) || strlen($password)<12 || strlen($password)>200) $json(['ok'=>false,'error'=>'invalid_reset_details'],422);
        $stmt=$db()->prepare('SELECT customer_id FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() LIMIT 1');
        $stmt->execute([hash('sha256',$rawToken)]); $token=$stmt->fetch();
        if (!$token) $json(['ok'=>false,'error'=>'invalid_or_expired_token'],400);
        $db()->beginTransaction();
        try {
            $db()->prepare('UPDATE customers SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$token['customer_id']]);
            $db()->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE customer_id=? AND used_at IS NULL')->execute([(int)$token['customer_id']]);
            $db()->commit();
        } catch (Throwable $e) { if ($db()->inTransaction()) $db()->rollBack(); throw $e; }
        $json(['ok'=>true,'message'=>'password_reset']);
    }
    if ($method === 'POST' && preg_match('~/api/auth/login/?
        Auth::start(); Auth::verifyCsrf();
        $data = $body();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        Auth::rateLimit($db(), 'login', $email, 8);
        $stmt = $db()->prepare('SELECT id,email,password_hash,first_name,last_name,account_status FROM customers WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
        if (!$customer || !$customer['password_hash'] || !password_verify($password, $customer['password_hash'])) {
            $json(['ok'=>false,'error'=>'invalid_email_or_password'], 401);
        }
        if ($customer['account_status'] !== 'active') {
            $json(['ok'=>false,'error'=>'account_not_active'], 403);
        }
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)$customer['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $json(['ok'=>true,'customer'=>['id'=>(int)$customer['id'],'email'=>$customer['email'],'first_name'=>$customer['first_name'],'last_name'=>$customer['last_name']],'csrf_token'=>$_SESSION['csrf']]);
    }
    if ($method === 'GET' && preg_match('~/api/auth/me/?$', $path)) {
        Auth::start();
        $id = (int)($_SESSION['customer_id'] ?? 0);
        if (!$id) $json(['ok'=>true,'customer'=>null]);
        $stmt = $db()->prepare('SELECT id,email,first_name,last_name,account_status FROM customers WHERE id=?');
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        if (!$customer || $customer['account_status'] !== 'active') $json(['ok'=>true,'customer'=>null]);
        $json(['ok'=>true,'customer'=>$customer]);
    }
    if ($method === 'POST' && preg_match('~/api/auth/logout/?$', $path)) {
        Auth::start(); Auth::verifyCsrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']);
        }
        session_destroy();
        $json(['ok'=>true]);
    }
    if ($method === 'GET' && preg_match('~/api/products/?$', $path)) {
        $products = $db()->query("SELECT p.id,p.sku,p.name,p.slug,p.product_type,p.short_description,p.regular_price,p.sale_price,p.stock_status,p.stock_quantity FROM products p WHERE p.status='published' ORDER BY p.created_at DESC,p.id DESC LIMIT 100")->fetchAll();
        $json(['ok'=>true,'products'=>$products]);
    }
    $json(['ok'=>false,'error'=>'not_found'], 404);
} catch (Throwable $e) {
    error_log('[ekhaya-api] ' . get_class($e) . ': ' . $e->getMessage());
    Response::json(['ok'=>false,'error'=>'server_error'], 500);
}
, $path)) {
        Auth::start(); Auth::verifyCsrf();
        $data = $body();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        Auth::rateLimit($db(), 'login', $email, 8);
        $stmt = $db()->prepare('SELECT id,email,password_hash,first_name,last_name,account_status FROM customers WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
        if (!$customer || !$customer['password_hash'] || !password_verify($password, $customer['password_hash'])) {
            $json(['ok'=>false,'error'=>'invalid_email_or_password'], 401);
        }
        if ($customer['account_status'] !== 'active') {
            $json(['ok'=>false,'error'=>'account_not_active'], 403);
        }
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)$customer['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $json(['ok'=>true,'customer'=>['id'=>(int)$customer['id'],'email'=>$customer['email'],'first_name'=>$customer['first_name'],'last_name'=>$customer['last_name']],'csrf_token'=>$_SESSION['csrf']]);
    }
    if ($method === 'GET' && preg_match('~/api/auth/me/?$', $path)) {
        Auth::start();
        $id = (int)($_SESSION['customer_id'] ?? 0);
        if (!$id) $json(['ok'=>true,'customer'=>null]);
        $stmt = $db()->prepare('SELECT id,email,first_name,last_name,account_status FROM customers WHERE id=?');
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        if (!$customer || $customer['account_status'] !== 'active') $json(['ok'=>true,'customer'=>null]);
        $json(['ok'=>true,'customer'=>$customer]);
    }
    if ($method === 'POST' && preg_match('~/api/auth/logout/?$', $path)) {
        Auth::start(); Auth::verifyCsrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']);
        }
        session_destroy();
        $json(['ok'=>true]);
    }
    if ($method === 'GET' && preg_match('~/api/products/?$', $path)) {
        $products = $db()->query("SELECT p.id,p.sku,p.name,p.slug,p.product_type,p.short_description,p.regular_price,p.sale_price,p.stock_status,p.stock_quantity FROM products p WHERE p.status='published' ORDER BY p.created_at DESC,p.id DESC LIMIT 100")->fetchAll();
        $json(['ok'=>true,'products'=>$products]);
    }
    $json(['ok'=>false,'error'=>'not_found'], 404);
} catch (Throwable $e) {
    error_log('[ekhaya-api] ' . get_class($e) . ': ' . $e->getMessage());
    Response::json(['ok'=>false,'error'=>'server_error'], 500);
}
