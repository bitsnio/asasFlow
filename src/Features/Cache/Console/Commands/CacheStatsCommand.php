<?php

namespace Bitsnio\AsasFlow\Features\Cache\Console\Commands;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Illuminate\Console\Command;

class CacheStatsCommand extends Command
{
    protected $signature = 'asasflow:cache:stats
                            {--entries : Show cached entries}';

    protected $description = 'Show ASASFLOW cache statistics';

    public function handle(CacheManager $manager): int
    {
        $stats = $manager->getStats();

        $this->info('=== ASASFLOW Cache Statistics ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Hits', $stats['hits']],
                ['Total Misses', $stats['misses']],
                ['Hit Ratio', $stats['hit_ratio'] . '%'],
                ['Total Requests', $stats['total_requests']],
                ['Cached Entries', $stats['entries_count']],
            ]
        );

        if ($this->option('entries')) {
            $entries = $manager->getEntries();
            $this->newLine();
            $this->info('=== Cached Entries ===');
            
            $rows = [];
            foreach ($entries as $key => $meta) {
                $rows[] = [
                    \Illuminate\Support\Str::limit($key, 50),
                    $meta['cached_at'] ?? 'N/A',
                    $meta['status_code'] ?? 'N/A',
                ];
            }
            
            $this->table(['Key', 'Cached At', 'Status'], $rows);
        }

        return 0;
    }
}
