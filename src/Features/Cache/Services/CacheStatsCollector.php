<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Illuminate\Support\Facades\Cache;

class CacheStatsCollector
{
    protected array $hits = [];
    protected array $misses = [];
    protected array $entries = [];

    public function recordHit(string $key): void
    {
        $this->hits[] = ['key' => $key, 'at' => now()];
        $this->incrementCounter('hits');
    }

    public function recordMiss(string $key): void
    {
        $this->misses[] = ['key' => $key, 'at' => now()];
        $this->incrementCounter('misses');
    }

    public function recordEntry(string $key, array $meta): void
    {
        $this->entries[$key] = array_merge($meta, [
            'cached_at' => now()->toIso8601String(),
        ]);
    }

    public function all(): array
    {
        return [
            'hits' => count($this->hits),
            'misses' => count($this->misses),
            'hit_ratio' => $this->calculateHitRatio(),
            'total_requests' => count($this->hits) + count($this->misses),
            'entries_count' => count($this->entries),
        ];
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    protected function incrementCounter(string $type): void
    {
        $key = "asasflow-cache:stats:{$type}:" . now()->format('Y-m-d-H');
        Cache::increment($key);
    }

    protected function calculateHitRatio(): float
    {
        $total = count($this->hits) + count($this->misses);
        return $total > 0 ? round(count($this->hits) / $total * 100, 2) : 0;
    }
}
