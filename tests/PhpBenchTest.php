<?php

declare(strict_types=1);

namespace EidCloud\PhpBench\Tests;

use EidCloud\PhpBench\Metrics\MetricsCollector;
use EidCloud\PhpBench\Runtime\MicroBenchmarker;
use EidCloud\PhpBench\Runtime\MultiRuntimeComparator;
use EidCloud\PhpBench\Reports\HtmlChartReport;
use EidCloud\PhpBench\BenchmarkLab;

final class PhpBenchTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function runAll(): bool
    {
        $this->testMetricsCollector();
        $this->testMicroBenchmarker();
        $this->testMultiRuntimeComparator();
        $this->testHtmlReportGeneration();
        $this->testBenchmarkLabSuite();

        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Test Results: {$this->passed} Passed, {$this->failed} Failed\n";
        echo str_repeat('=', 50) . "\n";

        return $this->failed === 0;
    }

    private function assert(bool $condition, string $testName): void
    {
        if ($condition) {
            $this->passed++;
            echo " \033[32m✔\033[0m {$testName}\n";
        } else {
            $this->failed++;
            echo " \033[31m✖\033[0m {$testName}\n";
        }
    }

    private function testMetricsCollector(): void
    {
        $collector = new MetricsCollector();
        $collector->start();

        // Simulate 10 requests with varying latencies
        $latencies = [1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0, 10.0];
        foreach ($latencies as $lat) {
            $collector->record($lat, 200, 1024);
        }
        $collector->record(50.0, 500, 256, true);
        $collector->stop();

        $summary = $collector->getSummary();

        $this->assert($summary['total_requests'] === 11, 'MetricsCollector tracks total requests correctly');
        $this->assert($summary['successful_requests'] === 10, 'MetricsCollector tracks successful requests');
        $this->assert($summary['failed_requests'] === 1, 'MetricsCollector tracks failed requests');
        $this->assert($summary['latency']['min_ms'] === 1.0, 'MetricsCollector calculates min latency');
        $this->assert($summary['latency']['p50_ms'] >= 5.0, 'MetricsCollector calculates P50 percentile');
        $this->assert(isset($summary['status_codes'][200]) && $summary['status_codes'][200] === 10, 'MetricsCollector groups status codes');
    }

    private function testMicroBenchmarker(): void
    {
        $bench = new MicroBenchmarker();

        $coldBoot = $bench->measureColdBoot(2);
        $this->assert($coldBoot['iterations'] === 2 && $coldBoot['avg_ms'] > 0, 'MicroBenchmarker measures cold boot startup');

        $jsonPerf = $bench->measureJsonPerformance(1000);
        $this->assert($jsonPerf['encode_ops_sec'] > 0 && $jsonPerf['decode_ops_sec'] > 0, 'MicroBenchmarker measures JSON ops/sec');

        $routingPerf = $bench->measureRoutingLatency(1000);
        $this->assert($routingPerf['ops_per_second'] > 0 && $routingPerf['matches_found'] === 1000, 'MicroBenchmarker measures URL regex routing');

        $dbPerf = $bench->measureDatabaseThroughput(500);
        if ($dbPerf['supported']) {
            $this->assert($dbPerf['insert_ops_sec'] > 0 && $dbPerf['select_ops_sec'] > 0, 'MicroBenchmarker profiles SQLite DB throughput');
        } else {
            $this->assert(true, 'MicroBenchmarker safely handles systems without pdo_sqlite');
        }
    }

    private function testMultiRuntimeComparator(): void
    {
        $profiles = MultiRuntimeComparator::getRuntimeProfiles();
        $this->assert(isset($profiles['PHP-CLI'], $profiles['PHP-FPM'], $profiles['Swoole'], $profiles['RoadRunner'], $profiles['FrankPHP']), 'All required runtimes exist in comparison profile');

        $index = MultiRuntimeComparator::computeRelativePerformanceIndex();
        $this->assert($index['FrankPHP']['rps'] > $index['PHP-FPM']['rps'], 'FrankPHP speedup benchmark comparison computed correctly');
        $this->assert(str_ends_with($index['RoadRunner']['speedup_vs_fpm'], 'x'), 'Speedup multiplier formatted as Nx');
    }

    private function testHtmlReportGeneration(): void
    {
        $report = new HtmlChartReport();
        $collector = new MetricsCollector();
        $collector->start();
        $collector->record(2.5, 200, 1024);
        $collector->stop();

        $html = $report->generate(
            $collector->getSummary(),
            ['json' => ['iterations' => 100, 'encode_ops_sec' => 50000, 'encode_time_sec' => 0.002]],
            MultiRuntimeComparator::computeRelativePerformanceIndex()
        );

        $this->assert(str_contains($html, '<!DOCTYPE html>'), 'HTML report generates valid DOCTYPE');
        $this->assert(str_contains($html, 'chart.umd.min.js'), 'HTML report embeds Chart.js');
        $this->assert(str_contains($html, 'eidcloud-php-bench'), 'HTML report branding present');
    }

    private function testBenchmarkLabSuite(): void
    {
        $lab = new BenchmarkLab();
        $results = $lab->runSuite(null, 5, 20);

        $this->assert(isset($results['micro_benchmarks']['cold_boot']), 'BenchmarkLab executes cold boot tests');
        $this->assert(isset($results['micro_benchmarks']['json']), 'BenchmarkLab executes JSON tests');
        $this->assert(isset($results['micro_benchmarks']['routing']), 'BenchmarkLab executes routing tests');
        $this->assert(isset($results['runtime_comparison']['FrankPHP']), 'BenchmarkLab compiles runtime comparison');
        $this->assert(isset($results['load_test']['requests_per_second']), 'BenchmarkLab aggregates throughput metrics');
    }
}
