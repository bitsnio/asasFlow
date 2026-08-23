<?php

namespace AsasFlow\Features\Cache\Console\Commands;

use AsasFlow\Features\Cache\Services\ModuleCacheManager;
use Illuminate\Console\Command;

class CacheStatusCommand extends Command
{
    protected $signature = 'asasflow:cache:status';

    protected $description = 'Show ASASFLOW cache status';

    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        parent::__construct();
        $this->cacheManager = $cacheManager;
    }

    public function handle(): int
    {
        $store = config('asasflow.cache.store', config('cache.default'));
        $driver = config("cache.stores.{$store}.driver", 'file');

        $this->info('=== ASASFLOW Cache Status ===');
        $this->table(
            ['Property', 'Value'],
            [
                ['Enabled', config('asasflow.cache.enabled', true) ? '✅ Yes' : '❌ No'],
                ['Store', $store],
                ['Driver', $driver],
                ['Tag Support', $this->cacheManager->supportsTags() ? '✅ Yes' : '❌ No'],
                ['Prefix', config('asasflow.cache.prefix', 'asasflow')],
                ['Default TTL', config('asasflow.cache.default_ttl', 3600) . 's'],
                ['Strict Isolation', config('asasflow.cache.strict_isolation', true) ? '✅ Yes' : '❌ No'],
                ['Fallback Registry', config('asasflow.cache.fallback_registry', true) ? '✅ Yes' : '❌ No'],
            ]
        );

        return 0;
    }
}
