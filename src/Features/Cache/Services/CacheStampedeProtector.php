<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheStampedeProtector
{
    public function acquireLock(string $key): bool
    {
        $lockKey = $this->lockKey($key);
        $ttl = config('asasflow-cache.stampede_protection.lock_ttl', 10);

        return Cache::lock($lockKey, $ttl)->get();
    }

    public function releaseLock(string $key): void
    {
        Cache::lock($this->lockKey($key))->forceRelease();
    }

    public function getStale(string $key): ?array
    {
        if (!config('asasflow-cache.stampede_protection.stale_while_revalidate', true)) {
            return null;
        }

        $staleKey = $this->staleKey($key);
        return Cache::get($staleKey);
    }

    public function storeStale(string $key, array $data, int $ttl): void
    {
        $staleKey = $this->staleKey($key);
        Cache::put($staleKey, $data, now()->addSeconds($ttl));
    }

    public function dispatchRefresh(string $key, callable $callback, int $ttl): void
    {
        \Bitsnio\AsasFlow\Features\Cache\Jobs\RefreshStaleCache::dispatch($key, $callback, $ttl);
    }

    protected function lockKey(string $key): string
    {
        return "lock:cache:{$key}";
    }

    protected function staleKey(string $key): string
    {
        return "stale:{$key}";
    }
}
