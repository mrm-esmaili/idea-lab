<?php

/*
 * عکس‌برداری از داده پیش از ارتقا و مقایسه پس از آن.
 *   php snapshot.php save   before.json   (پوشه جاری: برنامه)
 *   php snapshot.php compare before.json
 * مقایسه: هر ردیفی که پیش از ارتقا بود، با همان ستون‌ها، بدون تغییر باقی مانده است.
 */

require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

[$mode, $file] = [$argv[1] ?? 'save', $argv[2] ?? 'snapshot.json'];

// هر خطای پیش‌بینی‌نشده باید آزمون را ناموفق کند، نه بی‌صدا رد شود
set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, 'FAIL snapshot: ' . $e->getMessage() . "\n");
    exit(1);
});

// داده گذرا، و تغییرهای عمدی ارتقا (پارامتر اختیاری «لینک» قالب پرداخت)
$skipTables = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];
$allowedChanges = ['sms_templates' => ['parameters', 'updated_at']];

$rows = function (string $table, array $columns): array {
    $key = in_array('id', $columns, true) ? 'id' : $columns[0];
    $result = [];

    foreach (DB::table($table)->select($columns)->orderBy($key)->get() as $row) {
        $result[(string) $row->{$key}] = md5(json_encode((array) $row, JSON_UNESCAPED_UNICODE));
    }

    return $result;
};

if ($mode === 'save') {
    $snapshot = [];

    // فقط جدول‌های همین پایگاه داده (سرور ممکن است پایگاه‌های دیگری هم داشته باشد)
    foreach (Schema::getTableListing(DB::connection()->getDatabaseName(), false) as $table) {
        if (in_array($table, $skipTables, true)) {
            continue;
        }

        $columns = array_values(array_diff(Schema::getColumnListing($table), $allowedChanges[$table] ?? []));
        $snapshot[$table] = ['columns' => $columns, 'rows' => $rows($table, $columns)];
    }

    file_put_contents($file, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo 'saved ' . count($snapshot) . ' tables, ' . array_sum(array_map(fn ($t) => count($t['rows']), $snapshot)) . " rows\n";
    exit(0);
}

$before = is_file($file) ? json_decode(file_get_contents($file), true) : null;

if (! is_array($before) || $before === []) {
    fwrite(STDERR, "FAIL snapshot file missing or empty: {$file}\n");
    exit(1);
}

$problems = 0;

foreach ($before as $table => $data) {
    if (! Schema::hasTable($table)) {
        echo "FAIL table removed: {$table}\n";
        $problems++;
        continue;
    }

    $after = $rows($table, $data['columns']);

    foreach ($data['rows'] as $id => $hash) {
        if (! isset($after[$id])) {
            echo "FAIL {$table}#{$id} missing\n";
            $problems++;
        } elseif ($after[$id] !== $hash) {
            echo "FAIL {$table}#{$id} changed\n";
            $problems++;
        }
    }
}

echo $problems === 0 ? 'PASS all ' . array_sum(array_map(fn ($t) => count($t['rows']), $before)) . " pre-upgrade rows unchanged\n" : "{$problems} problem(s)\n";
exit($problems === 0 ? 0 : 1);
