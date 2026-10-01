<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/PhpBenchTest.php';

use EidCloud\PhpBench\Tests\PhpBenchTest;

echo "\033[1;36m";
echo "=================================================================\n";
echo "  ⚡ EIDCLOUD-PHP-BENCH - Automated Test Suite\n";
echo "=================================================================\n";
echo "\033[0m\n";

$tester = new PhpBenchTest();
$success = $tester->runAll();

exit($success ? 0 : 1);
