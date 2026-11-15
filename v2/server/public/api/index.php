<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Database.php';
require_once dirname(__DIR__, 2) . '/src/Response.php';
require_once dirname(__DIR__, 2) . '/src/Auth.php';

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
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)$db()->lastInsertId();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $json(['ok'=>true,'customer'=>['id'=>(int)$_SESSION['customer_id'],'email'=>$email,'first_name'=>$first,'last_name'=>$last,'account_status'=>'pending'],'csrf_token'=>$_SESSION['csrf']], 201);
    }
    if ($method === 'POST' && preg_match('~/api/auth/login/?$', $path)) {
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
