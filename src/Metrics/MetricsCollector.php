<?php

declare(strict_types=1);

namespace EidCloud\PhpBench\Metrics;

final class MetricsCollector
{
    /** @var float[] */
    private array $latenciesMs = [];

    /** @var int[] */
    private array $statusCodes = [];

    private int $totalErrors = 0;
    private int $totalBytes = 0;
    private float $startTime = 0.0;
    private float $endTime = 0.0;
    private int $startMemory = 0;
    private int $endMemory = 0;

    public function start(): void
    {
        $this->latenciesMs = [];
        $this->statusCodes = [];
        $this->totalErrors = 0;
        $this->totalBytes = 0;
        $this->startMemory = memory_get_usage(true);
        $this->startTime = microtime(true);
    }

    public function stop(): void
    {
        $this->endTime = microtime(true);
        $this->endMemory = memory_get_peak_usage(true);
    }

    public function record(float $latencyMs, int $statusCode, int $bytes = 0, bool $isError = false): void
    {
        $this->latenciesMs[] = $latencyMs;
        $this->statusCodes[$statusCode] = ($this->statusCodes[$statusCode] ?? 0) + 1;
        $this->totalBytes += $bytes;
        if ($isError || $statusCode >= 400 || $statusCode === 0) {
            $this->totalErrors++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $count = count($this->latenciesMs);
        $durationSec = max(0.0001, ($this->endTime > 0 ? $this->endTime : microtime(true)) - $this->startTime);
        $rps = $count > 0 ? round($count / $durationSec, 2) : 0.0;

        if ($count === 0) {
            return [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => $this->totalErrors,
                'duration_seconds' => round($durationSec, 4),
                'requests_per_second' => 0.0,
                'latency' => [
                    'avg_ms' => 0.0,
                    'min_ms' => 0.0,
                    'max_ms' => 0.0,
                    'p50_ms' => 0.0,
                    'p90_ms' => 0.0,
                    'p95_ms' => 0.0,
                    'p99_ms' => 0.0,
                ],
                'status_codes' => $this->statusCodes,
                'total_bytes' => $this->totalBytes,
                'throughput_mb_per_sec' => 0.0,
                'memory' => [
                    'start_bytes' => $this->startMemory,
                    'peak_bytes' => $this->endMemory,
                    'peak_formatted' => $this->formatBytes($this->endMemory),
                ]
            ];
        }

        $sorted = $this->latenciesMs;
        sort($sorted);

        $min = $sorted[0];
        $max = $sorted[$count - 1];
        $sum = array_sum($sorted);
        $avg = $sum / $count;

        $p50 = $this->percentile($sorted, 50);
        $p90 = $this->percentile($sorted, 90);
        $p95 = $this->percentile($sorted, 95);
        $p99 = $this->percentile($sorted, 99);

        $throughputMb = ($this->totalBytes / 1024 / 1024) / $durationSec;

        return [
            'total_requests' => $count,
            'successful_requests' => $count - $this->totalErrors,
            'failed_requests' => $this->totalErrors,
            'duration_seconds' => round($durationSec, 4),
            'requests_per_second' => $rps,
            'latency' => [
                'avg_ms' => round($avg, 3),
                'min_ms' => round($min, 3),
                'max_ms' => round($max, 3),
                'p50_ms' => round($p50, 3),
                'p90_ms' => round($p90, 3),
                'p95_ms' => round($p95, 3),
                'p99_ms' => round($p99, 3),
            ],
            'status_codes' => $this->statusCodes,
            'total_bytes' => $this->totalBytes,
            'throughput_mb_per_sec' => round($throughputMb, 3),
            'memory' => [
                'start_bytes' => $this->startMemory,
                'peak_bytes' => $this->endMemory,
                'peak_formatted' => $this->formatBytes($this->endMemory),
            ]
        ];
    }

    /**
     * @param float[] $sorted
     */
    private function percentile(array $sorted, float $p): float
    {
        $count = count($sorted);
        if ($count === 0) {
            return 0.0;
        }
        $index = ($p / 100) * ($count - 1);
        $floor = (int) floor($index);
        $ceil = (int) ceil($index);
        if ($floor === $ceil) {
            return $sorted[$floor];
        }
        $fraction = $index - $floor;
        return $sorted[$floor] + ($sorted[$ceil] - $sorted[$floor]) * $fraction;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
