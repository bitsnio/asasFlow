<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Bitsnio\AsasFlow\Features\Cache\Jobs\RefreshStaleCache;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

class CacheStampedeProtector
{
    public function acquireLock(string $key): bool
    {
        $lockKey = $this->lockKey($key);
        $ttl = (int) config('asasflow-cache.stampede_protection.lock_ttl', 10);
        $store = $this->store()->getStore();

        if ($store instanceof LockProvider) {
            return $store->lock($lockKey, $ttl)->get();
        }

        return Cache::lock($lockKey, $ttl)->get();
    }

    public function releaseLock(string $key): void
    {
        $lockKey = $this->lockKey($key);
        $store = $this->store()->getStore();

        if ($store instanceof LockProvider) {
            $store->lock($lockKey)->forceRelease();

            return;
        }

        Cache::lock($lockKey)->forceRelease();
    }

    public function getStale(string $key): ?array
    {
        if (!config('asasflow-cache.stampede_protection.stale_while_revalidate', true)) {
            return null;
        }

        $stale = $this->store()->get($this->staleKey($key));

        return is_array($stale) ? $stale : null;
    }

    public function storeStale(string $key, array $data, int $ttl): void
    {
        $this->store()->put($this->staleKey($key), $data, now()->addSeconds($ttl));
    }

    public function clearStale(string $key): void
    {
        $this->store()->forget($this->staleKey($key));
    }

    public function dispatchRefresh(string $key, callable $callback, int $ttl): void
    {
        RefreshStaleCache::dispatch($key, $callback, $ttl);
    }

    protected function store()
    {
        return Cache::store(config('asasflow-cache.store', config('cache.default', 'array')));
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
