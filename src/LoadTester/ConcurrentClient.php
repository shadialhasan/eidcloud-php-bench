<?php

declare(strict_types=1);

namespace EidCloud\PhpBench\LoadTester;

use EidCloud\PhpBench\Metrics\MetricsCollector;
use CurlMultiHandle;
use CurlHandle;

final class ConcurrentClient
{
    private string $targetUrl;
    private int $concurrency;
    private int $totalRequests;
    private float $timeoutSec;
    private array $customHeaders;
    private ?string $method;
    private ?string $body;

    public function __construct(
        string $targetUrl,
        int $concurrency = 10,
        int $totalRequests = 100,
        float $timeoutSec = 10.0,
        string $method = 'GET',
        ?string $body = null,
        array $customHeaders = []
    ) {
        $this->targetUrl = $targetUrl;
        $this->concurrency = max(1, $concurrency);
        $this->totalRequests = max(1, $totalRequests);
        $this->timeoutSec = $timeoutSec;
        $this->method = strtoupper($method);
        $this->body = $body;
        $this->customHeaders = $customHeaders;
    }

    /**
     * Executes concurrent non-blocking curl requests and returns aggregated metrics.
     *
     * @param (callable(int $completed, int $total): void)|null $progressCallback
     */
    public function run(?callable $progressCallback = null): MetricsCollector
    {
        $collector = new MetricsCollector();
        $collector->start();

        if (!extension_loaded('curl')) {
            throw new \RuntimeException('cURL extension is required for concurrent load testing.');
        }

        $multiHandle = curl_multi_init();
        $runningHandles = [];
        $startTimeMap = [];
        $queuedRequests = $this->totalRequests;
        $completedRequests = 0;

        // Fill initial worker pool
        $initialBatch = min($this->concurrency, $queuedRequests);
        for ($i = 0; $i < $initialBatch; $i++) {
            $ch = $this->createHandle();
            $id = (int)$ch;
            $runningHandles[$id] = $ch;
            $startTimeMap[$id] = microtime(true);
            curl_multi_add_handle($multiHandle, $ch);
            $queuedRequests--;
        }

        do {
            $status = curl_multi_exec($multiHandle, $active);
            if ($active) {
                curl_multi_select($multiHandle, 0.05);
            }

            while ($info = curl_multi_info_read($multiHandle)) {
                $completedHandle = $info['handle'];
                $completedId = (int)$completedHandle;

                $start = $startTimeMap[$completedId] ?? microtime(true);
                $latencyMs = (microtime(true) - $start) * 1000.0;

                $statusCode = (int)curl_getinfo($completedHandle, CURLINFO_HTTP_CODE);
                $bytes = (int)curl_getinfo($completedHandle, CURLINFO_SIZE_DOWNLOAD);
                $isError = ($info['result'] !== CURLE_OK);

                $collector->record($latencyMs, $statusCode, $bytes, $isError);
                $completedRequests++;

                if ($progressCallback !== null) {
                    $progressCallback($completedRequests, $this->totalRequests);
                }

                curl_multi_remove_handle($multiHandle, $completedHandle);
                curl_close($completedHandle);
                unset($runningHandles[$completedId], $startTimeMap[$completedId]);

                if ($queuedRequests > 0) {
                    $newHandle = $this->createHandle();
                    $newId = (int)$newHandle;
                    $runningHandles[$newId] = $newHandle;
                    $startTimeMap[$newId] = microtime(true);
                    curl_multi_add_handle($multiHandle, $newHandle);
                    $queuedRequests--;
                }
            }
        } while ($active > 0 || !empty($runningHandles));

        curl_multi_close($multiHandle);
        $collector->stop();

        return $collector;
    }

    private function createHandle(): CurlHandle
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, (int)($this->timeoutSec * 1000));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, (int)(min(5.0, $this->timeoutSec) * 1000));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'eidcloud-php-bench/1.0.0');

        $headers = array_merge([
            'Connection: keep-alive',
            'Accept: */*'
        ], $this->customHeaders);

        if ($this->method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($this->body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
            }
        } elseif ($this->method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
            if ($this->body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        return $ch;
    }
}
