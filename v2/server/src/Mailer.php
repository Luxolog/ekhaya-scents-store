<?php
declare(strict_types=1);
final class Mailer
{
    public static function send(array $config, string $to, string $subject, string $html): void
    {
        $mail = $config['mail'] ?? [];
        $from = (string)($mail['from_address'] ?? 'hello@ekhayascents.co.za');
        $name = (string)($mail['from_name'] ?? 'Ekhaya Scents');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid mail sender configuration.');
        $headers = ['MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','From: =?UTF-8?B?' . base64_encode($name) . '?= <' . $from . '>','Reply-To: ' . $from,'X-Mailer: EkhayaScents'];
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        if (!mail($to, $encodedSubject, $html, implode("\r\n", $headers))) throw new RuntimeException('Transactional email could not be handed to the host mail service.');
    }
    public static function verificationHtml(string $firstName, string $url): string
    {
        $name = htmlspecialchars($firstName !== '' ? $firstName : 'there', ENT_QUOTES, 'UTF-8');
        $link = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return '<div style="font-family:Arial,sans-serif;color:#24211d;max-width:600px;margin:auto"><h1 style="color:#9b773c">Welcome to Ekhaya Scents</h1><p>Hello ' . $name . ',</p><p>Please verify your email address to activate your account.</p><p><a href="' . $link . '" style="display:inline-block;background:#171512;color:#fff;padding:14px 22px;text-decoration:none">Verify email address</a></p><p>This link expires in 24 hours. If you did not create this account, ignore this email.</p><p>The Ekhaya Scents Team</p></div>';
    }
    public static function resetHtml(string $firstName, string $url): string
    {
        $name = htmlspecialchars($firstName !== '' ? $firstName : 'there', ENT_QUOTES, 'UTF-8');
        $link = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return '<div style="font-family:Arial,sans-serif;color:#24211d;max-width:600px;margin:auto"><h1 style="color:#9b773c">Reset your password</h1><p>Hello ' . $name . ',</p><p>We received a request to reset your Ekhaya Scents account password.</p><p><a href="' . $link . '" style="display:inline-block;background:#171512;color:#fff;padding:14px 22px;text-decoration:none">Choose a new password</a></p><p>This link expires in one hour. If you did not request this, ignore this email.</p><p>The Ekhaya Scents Team</p></div>';
    }
}
