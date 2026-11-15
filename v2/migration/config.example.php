<?php
declare(strict_types=1);
return [
    'source' => [
        'host' => getenv('EKHAYA_SOURCE_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('EKHAYA_SOURCE_DB_PORT') ?: '3306',
        'database' => getenv('EKHAYA_SOURCE_DB_NAME') ?: '',
        'username' => getenv('EKHAYA_SOURCE_DB_USER') ?: '',
        'password' => getenv('EKHAYA_SOURCE_DB_PASS') ?: '',
        'prefix' => getenv('EKHAYA_SOURCE_DB_PREFIX') ?: 'wp_',
    ],
    'target' => [
        'host' => getenv('EKHAYA_TARGET_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('EKHAYA_TARGET_DB_PORT') ?: '3306',
        'database' => getenv('EKHAYA_TARGET_DB_NAME') ?: '',
        'username' => getenv('EKHAYA_TARGET_DB_USER') ?: '',
        'password' => getenv('EKHAYA_TARGET_DB_PASS') ?: '',
    ],
    'snapshot' => '2026-11-14',
];
