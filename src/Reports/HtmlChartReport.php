<?php

declare(strict_types=1);

namespace EidCloud\PhpBench\Reports;

final class HtmlChartReport
{
    /**
     * Generate interactive standalone HTML report with Chart.js embedded via CDN.
     *
     * @param array<string, mixed> $loadMetrics
     * @param array<string, mixed> $microMetrics
     * @param array<string, mixed> $runtimeComparison
     * @param array<string, mixed> $metadata
     */
    public function generate(
        array $loadMetrics,
        array $microMetrics,
        array $runtimeComparison,
        array $metadata = []
    ): string {
        $timestamp = date('Y-m-d H:i:s T');
        $phpVersion = PHP_VERSION;
        $os = PHP_OS . ' (' . php_uname('m') . ')';

        $jsonLoadMetrics = json_encode($loadMetrics, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $jsonMicroMetrics = json_encode($microMetrics, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $jsonRuntimeComparison = json_encode($runtimeComparison, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        // Chart data preparations
        $runtimeLabels = [];
        $runtimeRps = [];
        $runtimeLatencies = [];
        $runtimeMemory = [];

        foreach ($runtimeComparison as $name => $item) {
            $runtimeLabels[] = $name;
            $runtimeRps[] = $item['rps'] ?? 0;
            $runtimeLatencies[] = $item['avg_latency_ms'] ?? 0;
            $runtimeMemory[] = $item['memory_kb'] ?? 0;
        }

        $latencyPercentiles = [
            'Min' => $loadMetrics['latency']['min_ms'] ?? 0,
            'Avg' => $loadMetrics['latency']['avg_ms'] ?? 0,
            'P50' => $loadMetrics['latency']['p50_ms'] ?? 0,
            'P90' => $loadMetrics['latency']['p90_ms'] ?? 0,
            'P95' => $loadMetrics['latency']['p95_ms'] ?? 0,
            'P99' => $loadMetrics['latency']['p99_ms'] ?? 0,
            'Max' => $loadMetrics['latency']['max_ms'] ?? 0,
        ];

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ EidCloud PHP Benchmark Report</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --card-border: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-blue: #38bdf8;
            --accent-purple: #a855f7;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            --accent-amber: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            line-height: 1.5;
            padding: 2rem 1.5rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .brand-title {
            font-size: 1.85rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .badge {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--accent-blue);
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.825rem;
            font-weight: 500;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 0.75rem;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .kpi-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .kpi-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .kpi-sub {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(480px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
            overflow-x: auto;
            margin-bottom: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th, td {
            padding: 0.85rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--card-border);
        }

        th {
            background-color: rgba(255, 255, 255, 0.03);
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        tr:hover {
            background-color: rgba(255, 255, 255, 0.02);
        }

        .tag-speedup {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--accent-emerald);
            padding: 0.2rem 0.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
        }

        footer {
            text-align: center;
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--card-border);
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        footer a {
            color: var(--accent-blue);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1 class="brand-title">⚡ eidcloud-php-bench</h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">
                    Objective PHP Runtime & Framework Benchmark Lab
                </p>
            </div>
            <div class="meta-badges">
                <span class="badge">PHP {$phpVersion}</span>
                <span class="badge">OS: {$os}</span>
                <span class="badge">Report: {$timestamp}</span>
            </div>
        </header>

        <!-- KPI Summary Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Throughput</div>
                <div class="kpi-value" style="color: var(--accent-emerald);">
                    {$loadMetrics['requests_per_second']} <span style="font-size: 1rem;">RPS</span>
                </div>
                <div class="kpi-sub">Total Requests: {$loadMetrics['total_requests']} in {$loadMetrics['duration_seconds']}s</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Avg Latency</div>
                <div class="kpi-value" style="color: var(--accent-blue);">
                    {$loadMetrics['latency']['avg_ms']} <span style="font-size: 1rem;">ms</span>
                </div>
                <div class="kpi-sub">Min: {$loadMetrics['latency']['min_ms']}ms | Max: {$loadMetrics['latency']['max_ms']}ms</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">P95 Latency</div>
                <div class="kpi-value" style="color: var(--accent-purple);">
                    {$loadMetrics['latency']['p95_ms']} <span style="font-size: 1rem;">ms</span>
                </div>
                <div class="kpi-sub">P50: {$loadMetrics['latency']['p50_ms']}ms | P99: {$loadMetrics['latency']['p99_ms']}ms</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Peak Memory</div>
                <div class="kpi-value" style="color: var(--accent-amber);">
                    {$loadMetrics['memory']['peak_formatted']}
                </div>
                <div class="kpi-sub">Throughput: {$loadMetrics['throughput_mb_per_sec']} MB/s</div>
            </div>
        </div>

        <!-- Charts Grid 1: Runtime Comparison -->
        <div class="grid-2">
            <div class="chart-card">
                <div class="chart-title">
                    <span>🚀 Multi-Runtime Throughput Comparison (RPS)</span>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="runtimeRpsChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-title">
                    <span>⏱️ Latency Distribution by Runtime (Lower is better)</span>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="runtimeLatencyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Grid 2: Latency Percentiles & Micro-benchmarks -->
        <div class="grid-2">
            <div class="chart-card">
                <div class="chart-title">
                    <span>📊 Measured Load Test Percentiles (ms)</span>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="percentileChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-title">
                    <span>🧠 Memory per Request (KB)</span>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="memoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Comparative Multi-Runtime Details Table -->
        <div class="table-card">
            <h2 class="chart-title">📋 Multi-Runtime Benchmark Comparative Matrix</h2>
            <table>
                <thead>
                    <tr>
                        <th>Runtime</th>
                        <th>Architecture</th>
                        <th>Max RPS</th>
                        <th>Avg Latency</th>
                        <th>Memory / Req</th>
                        <th>vs PHP-FPM</th>
                        <th>Efficiency Score</th>
                    </tr>
                </thead>
                <tbody>
HTML;

        foreach ($runtimeComparison as $name => $item) {
            $rpsFormatted = number_format($item['rps']);
            $latFormatted = $item['avg_latency_ms'] . ' ms';
            $memFormatted = number_format($item['memory_kb']) . ' KB';
            $speedup = $item['speedup_vs_fpm'];
            $efficiency = $item['efficiency_score'];
            $arch = $item['architecture'];

            $html .= <<<ROW
                    <tr>
                        <td><strong>{$name}</strong></td>
                        <td>{$arch}</td>
                        <td style="color: var(--accent-emerald); font-weight: 600;">{$rpsFormatted}</td>
                        <td>{$latFormatted}</td>
                        <td>{$memFormatted}</td>
                        <td><span class="tag-speedup">{$speedup}</span></td>
                        <td>{$efficiency}</td>
                    </tr>
ROW;
        }

        $html .= <<<HTML
                </tbody>
            </table>
        </div>

        <!-- Micro-benchmarks summary -->
        <div class="table-card">
            <h2 class="chart-title">🔬 Local Micro-Benchmark Results</h2>
            <table>
                <thead>
                    <tr>
                        <th>Benchmark Module</th>
                        <th>Test Scope</th>
                        <th>Iterations</th>
                        <th>Measured Operations / sec</th>
                        <th>Latency / Time</th>
                    </tr>
                </thead>
                <tbody>
HTML;

        if (isset($microMetrics['json'])) {
            $jsonOps = number_format($microMetrics['json']['encode_ops_sec']);
            $jsonTime = $microMetrics['json']['encode_time_sec'] . ' s';
            $jsonIter = number_format($microMetrics['json']['iterations']);
            $html .= <<<ROW
                    <tr>
                        <td><strong>JSON Serialization</strong></td>
                        <td>Nested associative payload encode</td>
                        <td>{$jsonIter}</td>
                        <td style="color: var(--accent-blue); font-weight: 600;">{$jsonOps} ops/s</td>
                        <td>{$jsonTime}</td>
                    </tr>
ROW;
        }

        if (isset($microMetrics['routing'])) {
            $routeOps = number_format($microMetrics['routing']['ops_per_second']);
            $routeLat = $microMetrics['routing']['avg_latency_microseconds'] . ' µs';
            $routeIter = number_format($microMetrics['routing']['iterations']);
            $html .= <<<ROW
                    <tr>
                        <td><strong>Regex URL Router</strong></td>
                        <td>REST path regex parameter dispatch</td>
                        <td>{$routeIter}</td>
                        <td style="color: var(--accent-purple); font-weight: 600;">{$routeOps} ops/s</td>
                        <td>{$routeLat}</td>
                    </tr>
ROW;
        }

        if (isset($microMetrics['database']) && ($microMetrics['database']['supported'] ?? false)) {
            $dbOps = number_format($microMetrics['database']['insert_ops_sec']);
            $dbTime = $microMetrics['database']['insert_time_sec'] . ' s';
            $dbIter = number_format($microMetrics['database']['iterations']);
            $html .= <<<ROW
                    <tr>
                        <td><strong>SQLite Memory Insert</strong></td>
                        <td>Prepared statement transaction write</td>
                        <td>{$dbIter}</td>
                        <td style="color: var(--accent-emerald); font-weight: 600;">{$dbOps} ops/s</td>
                        <td>{$dbTime}</td>
                    </tr>
ROW;
        }

        if (isset($microMetrics['cold_boot'])) {
            $bootAvg = $microMetrics['cold_boot']['avg_ms'] . ' ms';
            $bootIter = $microMetrics['cold_boot']['iterations'];
            $html .= <<<ROW
                    <tr>
                        <td><strong>PHP Cold-Boot Startup</strong></td>
                        <td>Subprocess CLI initialization</td>
                        <td>{$bootIter}</td>
                        <td>-</td>
                        <td style="color: var(--accent-amber); font-weight: 600;">{$bootAvg}</td>
                    </tr>
ROW;
        }

        $runtimeLabelsJson = json_encode($runtimeLabels);
        $runtimeRpsJson = json_encode($runtimeRps);
        $runtimeLatenciesJson = json_encode($runtimeLatencies);
        $runtimeMemoryJson = json_encode($runtimeMemory);
        $percentileLabelsJson = json_encode(array_keys($latencyPercentiles));
        $percentileValuesJson = json_encode(array_values($latencyPercentiles));

        $html .= <<<HTML
                </tbody>
            </table>
        </div>

        <footer>
            <p>Generated by <strong>eidcloud-php-bench v1.0.0</strong> &bull; Author: <strong>Eng. MHD. Shadi AL-Hasan</strong> &bull; MIT License</p>
        </footer>
    </div>

    <script>
        const ctxRps = document.getElementById('runtimeRpsChart').getContext('2d');
        new Chart(ctxRps, {
            type: 'bar',
            data: {
                labels: {$runtimeLabelsJson},
                datasets: [{
                    label: 'RPS (Requests Per Second)',
                    data: {$runtimeRpsJson},
                    backgroundColor: ['#64748b', '#3b82f6', '#10b981', '#a855f7', '#f43f5e'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                }
            }
        });

        const ctxLat = document.getElementById('runtimeLatencyChart').getContext('2d');
        new Chart(ctxLat, {
            type: 'bar',
            data: {
                labels: {$runtimeLabelsJson},
                datasets: [{
                    label: 'Avg Latency (ms)',
                    data: {$runtimeLatenciesJson},
                    backgroundColor: ['#f43f5e', '#f59e0b', '#38bdf8', '#10b981', '#a855f7'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                }
            }
        });

        const ctxPerc = document.getElementById('percentileChart').getContext('2d');
        new Chart(ctxPerc, {
            type: 'line',
            data: {
                labels: {$percentileLabelsJson},
                datasets: [{
                    label: 'Latency (ms)',
                    data: {$percentileValuesJson},
                    borderColor: '#38bdf8',
                    backgroundColor: 'rgba(56, 189, 248, 0.15)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#38bdf8',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#94a3b8' } }
                }
            }
        });

        const ctxMem = document.getElementById('memoryChart').getContext('2d');
        new Chart(ctxMem, {
            type: 'bar',
            data: {
                labels: {$runtimeLabelsJson},
                datasets: [{
                    label: 'Memory per Request (KB)',
                    data: {$runtimeMemoryJson},
                    backgroundColor: ['#f43f5e', '#fb923c', '#38bdf8', '#34d399', '#818cf8'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    </script>
</body>
</html>
HTML;

        return $html;
    }

    public function saveToFile(
        string $filePath,
        array $loadMetrics,
        array $microMetrics,
        array $runtimeComparison,
        array $metadata = []
    ): void {
        $html = $this->generate($loadMetrics, $microMetrics, $runtimeComparison, $metadata);
        file_put_contents($filePath, $html);
    }
}
