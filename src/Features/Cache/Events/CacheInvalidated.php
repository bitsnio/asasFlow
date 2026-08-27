<?php

namespace Bitsnio\AsasFlow\Features\Cache\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CacheInvalidated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $type,
        public string $target,
        public ?string $modelClass = null,
        public ?string $modelId = null,
    ) {}
}
