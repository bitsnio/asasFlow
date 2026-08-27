<?php

namespace Bitsnio\AsasFlow\Features\Cache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Symfony\Component\HttpFoundation\Response remember(\Illuminate\Http\Request $request, callable $callback, ?int $ttl = null, array $tags = [])
 * @method static void invalidateByModel(string $modelClass, ?string $modelId = null)
 * @method static void invalidateByTag(string $tag)
 * @method static void invalidateByTags(array $tags)
 * @method static void flush()
 * @method static array getStats()
 * @method static array getEntries()
 *
 * @see \Bitsnio\AsasFlow\Features\Cache\Services\CacheManager
 */
class MicroCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'asasflow.cache';
    }
}
