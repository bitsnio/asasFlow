<?php

namespace Bitsnio\AsasFlow\Features\Cache\Console\Commands;

use Illuminate\Console\Command;

class CacheWarmCommand extends Command
{
    protected $signature = 'asasflow:cache:warm
                            {endpoint : URL endpoint to warm}
                            {--times=1 : Number of requests}';

    protected $description = 'Warm cache by hitting endpoints';

    public function handle(): int
    {
        $endpoint = $this->argument('endpoint');
        $times = (int) $this->option('times');

        for ($i = 0; $i < $times; $i++) {
            $response = \Illuminate\Support\Facades\Http::get($endpoint);
            $this->info("Warmed {$endpoint} - Status: {$response->status()}");
        }

        return 0;
    }
}
