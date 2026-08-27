<?php

namespace Bitsnio\AsasFlow\Features\Cache\Jobs;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheResponseSerializer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshStaleCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $key,
        public $callback,
        public int $ttl,
    ) {}

    public function handle(
        CacheManager $cacheManager,
        CacheResponseSerializer $serializer,
    ): void {
        $store = Cache::store(config('asasflow-cache.store', config('cache.default', 'redis')));

        $response = ($this->callback)();
        $data = $serializer->serialize($response);

        $store->put($this->key, $data, $this->ttl);

        Log::debug("[AsasFlowCache] Background refresh completed for {$this->key}");
    }
}
