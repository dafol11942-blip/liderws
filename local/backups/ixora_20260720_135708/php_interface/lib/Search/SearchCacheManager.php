<?php
namespace Lider\Search;

class SearchCacheManager
{
    private string $cacheDir;
    private int $defaultTtl;

    public function __construct(string $cacheDir = '/search/supplier', int $defaultTtl = 900)
    {
        $this->cacheDir   = $cacheDir;
        $this->defaultTtl = $defaultTtl;
    }

    public function get(string $key): ?array
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) return null;

        $mtime = @filemtime($file);
        if ($mtime && (time() - $mtime) > $this->defaultTtl) {
            @unlink($file);
            return null;
        }

        $json = @file_get_contents($file);
        if ($json === false) return null;

        $data = json_decode($json, true);
        return $data['items'] ?? null;
    }

    public function set(string $key, array $items, ?int $ttl = null): void
    {
        $file = $this->getFilePath($key);
        $dir  = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $json = json_encode(['items' => $items, 't' => time()], JSON_UNESCAPED_UNICODE);
        @file_put_contents($file, $json, LOCK_EX);
    }

    public function clear(string $prefix = ''): void
    {
        $dir = $this->getBasePath();
        if (!is_dir($dir)) return;

        $files = glob($dir . '/*.json');
        foreach ($files as $file) {
            if ($prefix === '' || strpos(basename($file), $prefix) === 0) {
                @unlink($file);
            }
        }
    }

    public static function buildKey(string $query, string $source): string
    {
        return md5(trim(mb_strtolower($query))) . '_' . $source;
    }

    private function getFilePath(string $key): string
    {
        $safe = preg_replace('/[^a-f0-9_\-]/', '', $key);
        return $this->getBasePath() . '/' . $safe . '.json';
    }

    private function getBasePath(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/upload/cache' . $this->cacheDir;
    }
}
