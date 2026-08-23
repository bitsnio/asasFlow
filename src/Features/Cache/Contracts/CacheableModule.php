<?php

namespace AsasFlow\Features\Cache\Contracts;

interface CacheableModule
{
    /**
     * Get cache tags for this module.
     * Returns array like ['module:users', 'module:users:list']
     */
    public static function getCacheTags(): array;

    /**
     * Get cache tags to invalidate when this model changes.
     */
    public static function getInvalidationTags(): array;

    /**
     * Cache TTL in seconds. Null = forever.
     */
    public static function getCacheTtl(): ?int;

    /**
     * Whether this model is tenant-aware.
     */
    public static function isTenantAware(): bool;
}
