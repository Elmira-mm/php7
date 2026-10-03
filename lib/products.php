<?php
declare(strict_types=1);

require_once __DIR__ . '/QueryCounter.php';

/**
 * Список усіх товарів. 1 запит.
 */
function getAllProducts(PDO $pdo): array
{
    $rows = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    QueryCounter::increment();

    return $rows;
}


function getTotalStockValueNaive(PDO $pdo, array $products): float
{
    $total = 0.0;

    foreach ($products as $product) {
        $stmt = $pdo->prepare('SELECT price * stock AS value FROM products WHERE id = :id');
        $stmt->execute([':id' => $product['id']]);
        QueryCounter::increment();

        $total += (float) $stmt->fetchColumn();
    }

    return $total;
}


function totalStockValue(PDO $pdo): float
{
    $row = $pdo->query('SELECT SUM(price * stock) AS total FROM products')->fetch();
    QueryCounter::increment();

    return (float) ($row['total'] ?? 0);
}


function findBySku(PDO $pdo, string $sku): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE sku = :sku');
    $stmt->execute([':sku' => $sku]);
    QueryCounter::increment();

    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}
