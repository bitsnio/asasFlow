<?php

namespace Bitsnio\AsasFlow\Features\Cache\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CacheMiss
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $key,
        public ?string $reason = null,
        public float $responseTime = 0,
    ) {}
}
