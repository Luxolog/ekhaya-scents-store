<?php
declare(strict_types=1);

/** Read-only migration preflight. Usage: php audit.php [--json] */
$configPath = __DIR__ . '/config.local.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config.local.php. Copy config.example.php and configure credentials outside Git.\n");
    exit(2);
}
$config = require $configPath;

function db(array $c): PDO {
    foreach (['host','database','username','password'] as $key) {
        if (!array_key_exists($key, $c) || ($key !== 'password' && $c[$key] === '')) {
            throw new RuntimeException("Database configuration is incomplete: {$key}");
        }
    }
    $port = $c['port'] ?? '3306';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $port, $c['database']);
    return new PDO($dsn, $c['username'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
function qi(string $name): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) throw new InvalidArgumentException('Unsafe SQL identifier');
    return chr(96) . $name . chr(96);
}
try {
    $source = db($config['source']);
    $target = db($config['target']);
    $prefix = $config['source']['prefix'] ?? 'wp_';
    $required = [
        'users','usermeta','posts','postmeta','terms','term_taxonomy','term_relationships',
        'woocommerce_order_items','woocommerce_order_itemmeta','wc_orders','wc_orders_meta',
        'wc_order_addresses','wc_order_operational_data','wc_order_stats',
        'ekhaya_commissions','ekhaya_rewards_ledger','ekhaya_cm_subscribers',
        'ekhaya_bulk_jobs','ekhaya_bulk_items'
    ];
    $sourceTables = $source->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $tableSet = array_fill_keys($sourceTables, true);
    $checks = [];
    foreach ($required as $suffix) {
        $table = $prefix . $suffix;
        $exists = isset($tableSet[$table]);
        $count = null;
        $columnCount = 0;
        if ($exists) {
            $quoted = qi($table);
            $count = (int)$source->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
            $columnCount = count($source->query("SHOW COLUMNS FROM {$quoted}")->fetchAll(PDO::FETCH_COLUMN));
        }
        $checks[] = ['table'=>$table,'present'=>$exists,'row_count'=>$count,'column_count'=>$columnCount];
    }
    $targetTables = $target->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $report = [
        'tool'=>'Ekhaya Scents v2 migration preflight',
        'snapshot'=>$config['snapshot'] ?? null,
        'generated_at_utc'=>gmdate('c'),
        'source'=>['database_version'=>$source->query('SELECT VERSION()')->fetchColumn(),'table_count'=>count($sourceTables),'prefix'=>$prefix,'checks'=>$checks],
        'target'=>['database_version'=>$target->query('SELECT VERSION()')->fetchColumn(),'table_count'=>count($targetTables)],
        'safety'=>['mode'=>'read_only','customer_rows_read'=>false,'writes_performed'=>false,'email_queue_touched'=>false],
    ];
    if (in_array('--json', $argv, true)) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo "EKHAYA SCENTS V2 — READ-ONLY PREFLIGHT\n";
        echo "Snapshot: " . ($report['snapshot'] ?? 'not set') . "\n";
        echo "Source tables: {$report['source']['table_count']} | Target tables: {$report['target']['table_count']}\n\n";
        foreach ($checks as $item) {
            printf("%-42s %s", $item['table'], $item['present'] ? 'FOUND' : 'MISSING');
            if ($item['present']) printf(" | rows: %d | columns: %d", $item['row_count'], $item['column_count']);
            echo "\n";
        }
        echo "\nNo customer records were read. No data was changed.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Preflight failed: " . $e->getMessage() . "\n");
    exit(1);
}
