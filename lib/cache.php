<?php
declare(strict_types=1);
function cachedValue(string $key, int $ttlSeconds, callable $compute): array
{
    $file = sys_get_temp_dir() . '/practicum07_cache_' . md5($key) . '.json';

    if (is_file($file) && (time() - filemtime($file)) < $ttlSeconds) {
        $cached = json_decode(file_get_contents($file), true);

        return ['value' => $cached, 'cache' => 'hit'];
    }

    $value = $compute();
    file_put_contents($file, json_encode($value));

    return ['value' => $value, 'cache' => 'miss'];
}


function invalidateCache(string $key): void
{
    $file = sys_get_temp_dir() . '/practicum07_cache_' . md5($key) . '.json';

    if (is_file($file)) {
        unlink($file);
    }
}
