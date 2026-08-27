<?php

namespace Bitsnio\AsasFlow\Features\Cache\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class AutoCache
{
    public function __construct(
        public ?int $ttl = null,
        public ?string $tags = null,
        public bool $ignoreQueryParams = false,
        public ?string $keyStrategy = null,
        public bool $stampedeProtection = true,
    ) {}
}
