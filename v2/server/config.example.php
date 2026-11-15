<?php
declare(strict_types=1);

/*
 * Copy to config.local.php outside the public directory.
 * Prefer environment variables on the hosting account.
 */
return [
    'app' => [
        'environment' => getenv('EKHAYA_ENV') ?: 'staging',
        'base_url' => getenv('EKHAYA_BASE_URL') ?: 'https://dev.ekhayascents.co.za',
        'timezone' => 'Africa/Johannesburg',
        'allowed_origins' => array_filter(array_map('trim', explode(',', getenv('EKHAYA_ALLOWED_ORIGINS') ?: 'https://dev.ekhayascents.co.za'))),
    ],
    'database' => [
        'host' => getenv('EKHAYA_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('EKHAYA_DB_PORT') ?: '3306',
        'name' => getenv('EKHAYA_DB_NAME') ?: '',
        'user' => getenv('EKHAYA_DB_USER') ?: '',
        'password' => getenv('EKHAYA_DB_PASS') ?: '',
    ],
];
