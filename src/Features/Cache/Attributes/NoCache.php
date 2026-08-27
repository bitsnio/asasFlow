<?php

namespace Bitsnio\AsasFlow\Features\Cache\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class NoCache
{
    public function __construct(
        public ?string $reason = null,
    ) {}
}
