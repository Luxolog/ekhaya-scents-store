<?php
declare(strict_types=1);

final class Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_name('ekhaya_session');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(): void
    {
        self::start();
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
            Response::json(['ok'=>false,'error'=>'csrf_failed'], 419);
        }
    }

    public static function rateLimit(PDO $pdo, string $action, string $identity, int $limit = 8): void
    {
        $bucket = hash_hmac('sha256', strtolower(trim($identity)), self::rateSecret());
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM auth_rate_limits WHERE bucket_hash=? AND action=? FOR UPDATE');
            $stmt->execute([$bucket, $action]);
            $row = $stmt->fetch();
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            if ($row && $row['blocked_until'] && new DateTimeImmutable($row['blocked_until'], new DateTimeZone('UTC')) > $now) {
                $pdo->commit();
                Response::json(['ok'=>false,'error'=>'too_many_attempts'], 429);
            }
            if (!$row || (new DateTimeImmutable($row['window_started_at'], new DateTimeZone('UTC'))) < $now->modify('-15 minutes')) {
                $stmt = $pdo->prepare('INSERT INTO auth_rate_limits (bucket_hash,action,attempts,window_started_at,blocked_until) VALUES (?,?,1,UTC_TIMESTAMP(),NULL) ON DUPLICATE KEY UPDATE attempts=1,window_started_at=UTC_TIMESTAMP(),blocked_until=NULL');
                $stmt->execute([$bucket, $action]);
            } else {
                $attempts = (int)$row['attempts'] + 1;
                $blocked = $attempts >= $limit ? gmdate('Y-m-d H:i:s', time() + 900) : null;
                $stmt = $pdo->prepare('UPDATE auth_rate_limits SET attempts=?,blocked_until=? WHERE id=?');
                $stmt->execute([$attempts, $blocked, $row['id']]);
                if ($blocked) {
                    $pdo->commit();
                    Response::json(['ok'=>false,'error'=>'too_many_attempts'], 429);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function rateSecret(): string
    {
        $secret = getenv('EKHAYA_RATE_LIMIT_SECRET');
        if (!$secret) throw new RuntimeException('Rate-limit secret is not configured.');
        return $secret;
    }
}
