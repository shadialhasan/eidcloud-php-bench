<?php

declare(strict_types=1);

namespace EidCloud\PhpBench\Runtime;

final class MultiRuntimeComparator
{
    /**
     * Known typical baseline specifications and comparative characteristics.
     * Includes PHP-CLI, PHP-FPM, Swoole, RoadRunner, and FrankPHP.
     */
    public static function getRuntimeProfiles(): array
    {
        return [
            'PHP-CLI' => [
                'architecture' => 'Single Process / Process per invocation',
                'shared_memory' => 'None (Cold state per run)',
                'concurrency_model' => 'Synchronous / Forking',
                'typical_cold_boot_ms' => 15.0,
                'typical_max_rps' => 850,
                'typical_avg_latency_ms' => 8.2,
                'typical_memory_per_req_kb' => 4500,
                'strengths' => 'Zero memory leaks across requests, full state reset, simplicity.',
            ],
            'PHP-FPM' => [
                'architecture' => 'Pre-forked FastCGI worker pool',
                'shared_memory' => 'OPcache shared, isolated request memory',
                'concurrency_model' => 'Worker Pool (1 process per concurrent request)',
                'typical_cold_boot_ms' => 4.5,
                'typical_max_rps' => 4200,
                'typical_avg_latency_ms' => 3.1,
                'typical_memory_per_req_kb' => 2048,
                'strengths' => 'Battle-tested, standard web architecture, isolated request lifecycles.',
            ],
            'RoadRunner' => [
                'architecture' => 'Golang Application Server + Persistent PHP Workers',
                'shared_memory' => 'Persistent runtime state via IPC pipes / Goroutines',
                'concurrency_model' => 'High-concurrency Go proxy + Event-driven dispatch',
                'typical_cold_boot_ms' => 0.4,
                'typical_max_rps' => 28500,
                'typical_avg_latency_ms' => 0.65,
                'typical_memory_per_req_kb' => 120,
                'strengths' => 'Persistent framework bootstrap, zero boot latency per request, robust Go supervisor.',
            ],
            'Swoole' => [
                'architecture' => 'C/C++ Async Event Loop Extension',
                'shared_memory' => 'Coroutines with in-memory persistent table storage',
                'concurrency_model' => 'Epoll / Kqueue Async Coroutines (CSP)',
                'typical_cold_boot_ms' => 0.3,
                'typical_max_rps' => 38000,
                'typical_avg_latency_ms' => 0.45,
                'typical_memory_per_req_kb' => 85,
                'strengths' => 'Native coroutine multitasking, non-blocking async I/O, extreme raw throughput.',
            ],
            'FrankPHP' => [
                'architecture' => 'Caddy Web Server with Embedded C-Go PHP SAPI',
                'shared_memory' => 'In-memory persistent worker threads via Cgo',
                'concurrency_model' => 'Early Hints + HTTP/3 + Native Go Goroutines to PHP Threads',
                'typical_cold_boot_ms' => 0.25,
                'typical_max_rps' => 45000,
                'typical_avg_latency_ms' => 0.38,
                'typical_memory_per_req_kb' => 65,
                'strengths' => 'Embedded SAPI, native 103 Early Hints, modern TLS, HTTP/3, zero reverse-proxy overhead.',
            ],
        ];
    }

    /**
     * Compute comparative scoring index relative to PHP-FPM baseline (FPM = 1.0x).
     */
    public static function computeRelativePerformanceIndex(array $measuredOrProfileData = []): array
    {
        $profiles = self::getRuntimeProfiles();
        $baseRps = (float)$profiles['PHP-FPM']['typical_max_rps'];

        $comparison = [];
        foreach ($profiles as $name => $profile) {
            $rps = isset($measuredOrProfileData[$name]['rps']) ? (float)$measuredOrProfileData[$name]['rps'] : (float)$profile['typical_max_rps'];
            $lat = isset($measuredOrProfileData[$name]['avg_latency_ms']) ? (float)$measuredOrProfileData[$name]['avg_latency_ms'] : (float)$profile['typical_avg_latency_ms'];
            $mem = isset($measuredOrProfileData[$name]['memory_kb']) ? (float)$measuredOrProfileData[$name]['memory_kb'] : (float)$profile['typical_memory_per_req_kb'];

            $speedMultiplier = round($rps / $baseRps, 2);
            $efficiencyScore = round(($rps / max(1.0, $lat * ($mem / 100))), 2);

            $comparison[$name] = [
                'name' => $name,
                'architecture' => $profile['architecture'],
                'concurrency_model' => $profile['concurrency_model'],
                'rps' => $rps,
                'avg_latency_ms' => $lat,
                'memory_kb' => $mem,
                'speedup_vs_fpm' => $speedMultiplier . 'x',
                'efficiency_score' => $efficiencyScore,
                'strengths' => $profile['strengths'],
            ];
        }

        return $comparison;
    }
}
