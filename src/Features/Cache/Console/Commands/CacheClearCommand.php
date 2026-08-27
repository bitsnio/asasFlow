<?php

namespace Bitsnio\AsasFlow\Features\Cache\Console\Commands;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Illuminate\Console\Command;

class CacheClearCommand extends Command
{
    protected $signature = 'asasflow:cache:clear
                            {--model= : Model class to invalidate}
                            {--tag= : Specific tag to invalidate}
                            {--all : Flush all cache}';

    protected $description = 'Clear ASASFLOW cache';

    public function handle(CacheManager $manager, CacheInvalidator $invalidator): int
    {
        if ($this->option('all')) {
            $manager->flush();
            $this->info('✅ All cache flushed');
            return 0;
        }

        if ($model = $this->option('model')) {
            $invalidator->invalidate($model);
            $this->info("✅ Cache invalidated for model: {$model}");
            return 0;
        }

        if ($tag = $this->option('tag')) {
            $invalidator->invalidateTags([$tag]);
            $this->info("✅ Cache invalidated for tag: {$tag}");
            return 0;
        }

        $this->error('Please specify --model, --tag, or --all');
        return 1;
    }
}
