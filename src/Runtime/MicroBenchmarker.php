<?php

declare(strict_types=1);

namespace EidCloud\PhpBench\Runtime;

final class MicroBenchmarker
{
    /**
     * Measure cold boot startup time in milliseconds.
     */
    public function measureColdBoot(int $iterations = 5): array
    {
        $phpBin = PHP_BINARY;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $cmd = escapeshellarg($phpBin) . ' -r "echo 1;"';
            $t0 = microtime(true);
            exec($cmd, $out, $ret);
            $t1 = microtime(true);
            $times[] = ($t1 - $t0) * 1000.0;
        }

        sort($times);
        $avg = array_sum($times) / count($times);

        return [
            'iterations' => $iterations,
            'avg_ms' => round($avg, 3),
            'min_ms' => round($times[0], 3),
            'max_ms' => round($times[count($times) - 1], 3),
        ];
    }

    /**
     * Measure JSON serialization & parsing throughput.
     */
    public function measureJsonPerformance(int $iterations = 50000): array
    {
        $payload = [
            'id' => 9999,
            'uuid' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'user' => [
                'name' => 'Benchmark User',
                'email' => 'user@eidcloud.bench',
                'roles' => ['admin', 'developer', 'tester'],
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => true,
                    'nested' => [
                        'depth' => 3,
                        'tags' => ['perf', 'php8', 'cloud']
                    ]
                ]
            ],
            'timestamp' => 1764500000,
            'active' => true,
            'stats' => [12.5, 45.2, 99.9, 102.4, 5.0]
        ];

        $memStart = memory_get_usage();
        $t0 = microtime(true);
        $encoded = '';
        for ($i = 0; $i < $iterations; $i++) {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        }
        $encodeTime = microtime(true) - $t0;

        $t1 = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        }
        $decodeTime = microtime(true) - $t1;
        $memPeak = memory_get_peak_usage() - $memStart;

        return [
            'iterations' => $iterations,
            'encode_time_sec' => round($encodeTime, 4),
            'encode_ops_sec' => round($iterations / max(0.0001, $encodeTime), 2),
            'decode_time_sec' => round($decodeTime, 4),
            'decode_ops_sec' => round($iterations / max(0.0001, $decodeTime), 2),
            'memory_used_kb' => round($memPeak / 1024, 2),
        ];
    }

    /**
     * Measure regex and routing matching speed.
     */
    public function measureRoutingLatency(int $iterations = 50000): array
    {
        $routes = [
            '#^/api/v1/users$#' => 'UserController@index',
            '#^/api/v1/users/(?P<id>[0-9]+)$#' => 'UserController@show',
            '#^/api/v1/users/(?P<id>[0-9]+)/posts$#' => 'UserController@posts',
            '#^/api/v1/products/(?P<sku>[A-Z0-9\-]+)/reviews/(?P<reviewId>[0-9]+)$#' => 'ProductController@review',
            '#^/api/v1/checkout/process/(?P<session>[a-f0-9]{32})$#' => 'CheckoutController@process',
        ];

        $testPaths = [
            '/api/v1/users',
            '/api/v1/users/42',
            '/api/v1/users/42/posts',
            '/api/v1/products/SKU-990-PRO/reviews/108',
            '/api/v1/checkout/process/4a8c9b2e01df3487ac908123ef561234',
        ];

        $t0 = microtime(true);
        $matchesCount = 0;
        for ($i = 0; $i < $iterations; $i++) {
            $path = $testPaths[$i % count($testPaths)];
            foreach ($routes as $pattern => $handler) {
                if (preg_match($pattern, $path, $matches)) {
                    $matchesCount++;
                    break;
                }
            }
        }
        $totalTime = microtime(true) - $t0;

        return [
            'iterations' => $iterations,
            'total_time_sec' => round($totalTime, 4),
            'ops_per_second' => round($iterations / max(0.0001, $totalTime), 2),
            'avg_latency_microseconds' => round(($totalTime / $iterations) * 1_000_000, 3),
            'matches_found' => $matchesCount
        ];
    }

    /**
     * Measure SQLite database query throughput.
     */
    public function measureDatabaseThroughput(int $iterations = 10000): array
    {
        if (!extension_loaded('pdo_sqlite')) {
            return [
                'supported' => false,
                'message' => 'pdo_sqlite extension not loaded.'
            ];
        }

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE bench_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, score REAL)');

        // Benchmark inserts
        $t0 = microtime(true);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO bench_records (name, score) VALUES (:name, :score)');
        for ($i = 0; $i < $iterations; $i++) {
            $stmt->execute([':name' => 'Record_' . $i, ':score' => $i * 1.5]);
        }
        $pdo->commit();
        $insertTime = microtime(true) - $t0;

        // Benchmark selects
        $t1 = microtime(true);
        $readStmt = $pdo->prepare('SELECT * FROM bench_records WHERE id = :id');
        for ($i = 0; $i < $iterations; $i++) {
            $id = ($i % $iterations) + 1;
            $readStmt->execute([':id' => $id]);
            $row = $readStmt->fetch(\PDO::FETCH_ASSOC);
        }
        $selectTime = microtime(true) - $t1;

        return [
            'supported' => true,
            'iterations' => $iterations,
            'insert_time_sec' => round($insertTime, 4),
            'insert_ops_sec' => round($iterations / max(0.0001, $insertTime), 2),
            'select_time_sec' => round($selectTime, 4),
            'select_ops_sec' => round($iterations / max(0.0001, $selectTime), 2),
        ];
    }
}
