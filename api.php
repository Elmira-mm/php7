<?php
declare(strict_types=1);


ob_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/QueryCounter.php';
require_once __DIR__ . '/lib/products.php';
require_once __DIR__ . '/lib/cache.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body): void
{
    ob_end_clean();
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $resource = $_GET['resource'] ?? null;
    $mode = $_GET['mode'] ?? 'optimized';

    if ($resource !== 'products') {
        respond(404, ['success' => false, 'error' => 'Невідомий ресурс. Підтримується лише resource=products.']);
    }

    if ($method !== 'GET') {
        respond(405, ['success' => false, 'error' => 'Метод не підтримується для цього ресурсу.']);
    }

    // Крок 1. Профілювання — час і пам'ять до/після.
    QueryCounter::reset();
    $startTime = microtime(true);

    $products = getAllProducts($pdo);
    $cacheStatus = 'n/a';

    if ($mode === 'naive') {
        // Крок 3. "До" — N+1: окремий запит на кожен товар.
        $totalStockValue = getTotalStockValueNaive($pdo, $products);
    } else {
        // Крок 5+6. "Після" — один агрегатний запит, з файловим кешем (TTL 60с).
        $cached = cachedValue('total_stock_value', 60, fn() => totalStockValue($pdo));
        $totalStockValue = $cached['value'];
        $cacheStatus = $cached['cache'];
    }

    $elapsedMs = (microtime(true) - $startTime) * 1000;
    $peakMemoryMb = memory_get_peak_usage(true) / 1024 / 1024;

    respond(200, [
        'success' => true,
        'data' => [
            'products' => $products,
            'total_stock_value' => round($totalStockValue, 2),
        ],
        'meta' => [
            'mode' => $mode,
            'query_count' => QueryCounter::get(),
            'elapsed_ms' => round($elapsedMs, 2),
            'memory_mb' => round($peakMemoryMb, 3),
            'cache' => $cacheStatus,
        ],
    ]);
} catch (Throwable $e) {
    respond(500, ['success' => false, 'error' => 'Внутрішня помилка сервера.']);
}
