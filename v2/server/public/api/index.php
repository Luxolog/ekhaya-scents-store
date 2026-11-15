<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Database.php';
require_once dirname(__DIR__, 2) . '/src/Response.php';

$configPath = dirname(__DIR__, 2) . '/config.local.php';
if (!is_file($configPath)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'application_not_configured']);
    exit;
}
$config = require $configPath;
date_default_timezone_set($config['app']['timezone'] ?? 'Africa/Johannesburg');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $config['app']['allowed_origins'] ?? [];
if ($origin !== '' && in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET' && preg_match('~/api/?$~', $path)) {
        Response::json([
            'ok' => true,
            'service' => 'ekhaya-scents-api',
            'environment' => $config['app']['environment'] ?? 'staging',
            'version' => '0.1.0',
        ]);
    }

    if ($method === 'GET' && preg_match('~/api/health/?$~', $path)) {
        $pdo = Database::connection($config);
        $pdo->query('SELECT 1');
        Response::json(['ok'=>true,'database'=>'connected','time'=>gmdate('c')]);
    }

    if ($method === 'GET' && preg_match('~/api/products/?$~', $path)) {
        $pdo = Database::connection($config);
        $sql = "SELECT p.id, p.sku, p.name, p.slug, p.product_type, p.short_description,
                       p.regular_price, p.sale_price, p.stock_status, p.stock_quantity
                FROM products p
                WHERE p.status = 'published'
                ORDER BY p.created_at DESC, p.id DESC
                LIMIT 100";
        $products = $pdo->query($sql)->fetchAll();
        Response::json(['ok'=>true,'products'=>$products]);
    }

    Response::json(['ok'=>false,'error'=>'not_found'], 404);
} catch (Throwable $e) {
    error_log('[ekhaya-api] ' . get_class($e) . ': ' . $e->getMessage());
    Response::json(['ok'=>false,'error'=>'server_error'], 500);
}
