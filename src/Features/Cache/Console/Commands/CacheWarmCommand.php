<?php

namespace Bitsnio\AsasFlow\Features\Cache\Console\Commands;

use Bitsnio\AsasFlow\Features\Cache\Services\ModuleCacheManager;
use Illuminate\Console\Command;

class CacheWarmCommand extends Command
{
    protected $signature = 'asasflow:cache:warm
                            {module : Module name to warm}
                            {--tenant= : Tenant ID for scoped warming}';

    protected $description = 'Warm ASASFLOW module cache';

    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        parent::__construct();
        $this->cacheManager = $cacheManager;
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $tenantId = $this->option('tenant');

        $this->info("🔥 Warming cache for module: {$module}...");
        
        $this->cacheManager->warmModuleCache($module, $tenantId);
        
        $this->info('✅ Cache warming initiated');
        
        return 0;
    }
}
