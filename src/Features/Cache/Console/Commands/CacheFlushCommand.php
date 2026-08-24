<?php

namespace Bitsnio\AsasFlow\Features\Cache\Console\Commands;

use Bitsnio\AsasFlow\Features\Cache\Services\ModuleCacheManager;
use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Console\Command;

class CacheFlushCommand extends Command
{
    protected $signature = 'asasflow:cache:flush
                            {module? : Module name to flush (optional)}
                            {--tenant= : Tenant ID for scoped flush}
                            {--all : Flush all cache}';

    protected $description = 'Flush ASASFLOW module cache';

    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        parent::__construct();
        $this->cacheManager = $cacheManager;
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            $this->cacheManager->flushAll();
            $this->info('✅ All ASASFLOW cache flushed');
            return 0;
        }

        $module = $this->argument('module');
        
        if (!$module) {
            $this->error('Please provide a module name or use --all');
            return 1;
        }

        $tenantId = $this->option('tenant');
        
        $this->cacheManager->invalidateModule($module, $tenantId);
        
        $this->info("✅ Cache flushed for module: {$module}");
        
        if ($tenantId) {
            $this->info("   Tenant: {$tenantId}");
        }

        return 0;
    }
}
