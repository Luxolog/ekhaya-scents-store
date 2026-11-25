<?php
declare(strict_types=1);

/**
 * PayFast hosted checkout and ITN verification.
 * Secrets are read from the private config.local.php only.
 */
final class PayFast
{
    public static function settings(array $config): array
    {
        $settings = $config['payfast'] ?? [];
        $sandbox = ($settings['environment'] ?? 'sandbox') !== 'live';
        return [
            'merchant_id' => trim((string)($settings['merchant_id'] ?? '')),
            'merchant_key' => trim((string)($settings['merchant_key'] ?? '')),
            'passphrase' => trim((string)($settings['passphrase'] ?? '')),
            'sandbox' => $sandbox,
            'host' => $sandbox ? 'sandbox.payfast.co.za' : 'www.payfast.co.za',
        ];
    }

    public static function checkoutFields(array $settings, array $order, array $buyer, string $baseUrl): array
    {
        if ($settings['merchant_id'] === '' || $settings['merchant_key'] === '') {
            throw new RuntimeException('PayFast Sandbox credentials are not configured.');
        }
        $fields = [
            'merchant_id' => $settings['merchant_id'],
            'merchant_key' => $settings['merchant_key'],
            'return_url' => rtrim($baseUrl, '/') . '/payment-return.html?order=' . rawurlencode($order['order_number']),
            'cancel_url' => rtrim($baseUrl, '/') . '/payment-cancelled.html?order=' . rawurlencode($order['order_number']),
            'notify_url' => rtrim($baseUrl, '/') . '/api/payments/payfast/itn',
            'name_first' => (string)$buyer['first_name'],
            'name_last' => (string)$buyer['last_name'],
            'email_address' => (string)$buyer['email'],
            'cell_number' => (string)($buyer['phone'] ?? ''),
            'm_payment_id' => (string)$order['order_number'],
            'amount' => number_format((float)$order['grand_total'], 2, '.', ''),
            'item_name' => 'Ekhaya Scents order ' . (string)$order['order_number'],
            'item_description' => 'Ekhaya Scents online order',
        ];
        $fields = array_filter($fields, static fn($value) => $value !== '');
        $fields['signature'] = self::signature($fields, $settings['passphrase']);
        return $fields;
    }

    public static function signature(array $fields, string $passphrase = ''): string
    {
        unset($fields['signature']);
        $parts = [];
        foreach ($fields as $key => $value) {
            if ($value === '' || $value === null) continue;
            $parts[] = $key . '=' . urlencode(trim((string)$value));
        }
        if ($passphrase !== '') $parts[] = 'passphrase=' . urlencode($passphrase);
        return md5(implode('&', $parts));
    }

    public static function validSourceIp(string $ip): bool
    {
        $hosts = ['www.payfast.co.za', 'sandbox.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'];
        $allowed = [];
        foreach ($hosts as $host) {
            $resolved = gethostbynamel($host);
            if (is_array($resolved)) $allowed = array_merge($allowed, $resolved);
        }
        return in_array($ip, array_unique($allowed), true);
    }

    public static function validateNotification(array $post, array $settings): bool
    {
        if (empty($post['signature']) || empty($post['m_payment_id']) || empty($post['payment_status'])) return false;
        $signature = (string)$post['signature'];
        if (!hash_equals(self::signature($post, $settings['passphrase']), $signature)) return false;
        if (!self::validSourceIp((string)($_SERVER['REMOTE_ADDR'] ?? ''))) return false;

        $host = $settings['host'];
        $ch = curl_init('https://' . $host . '/eng/query/validate');
        if ($ch === false) return false;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $status === 200 && trim((string)$response) === 'VALID';
    }
}
