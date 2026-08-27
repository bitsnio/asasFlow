<?php

namespace AsasFlow\Features\ControllerGeneration\Services;

use Illuminate\Support\Str;

class RouteNameTruncator
{
    public const MAX_ROUTE_LENGTH = 64;
    public const MAX_NAME_LENGTH = 60;
    public const MAX_NESTING_LEVEL = 3;

    public function truncateRouteName(array $pathParts, string $separator = '-'): string
    {
        // Limit nesting depth
        if (count($pathParts) > self::MAX_NESTING_LEVEL) {
            $pathParts = $this->reduceNestingDepth($pathParts);
        }

        $fullPath = implode($separator, array_map([Str::class, 'kebab'], $pathParts));
        
        if (strlen($fullPath) <= self::MAX_ROUTE_LENGTH) {
            return $fullPath;
        }

        return $this->intelligentTruncate($pathParts, $separator);
    }

    protected function reduceNestingDepth(array $parts): array
    {
        // Keep first, middle (if exists), and last
        $length = count($parts);
        if ($length <= self::MAX_NESTING_LEVEL) {
            return $parts;
        }

        $reduced = [$parts[0]];
        
        // Keep middle if it's meaningful (not just an action)
        if ($length > 3) {
            $middleIndex = floor($length / 2);
            $reduced[] = $parts[$middleIndex];
        }
        
        $reduced[] = end($parts);
        
        return $reduced;
    }

    protected function intelligentTruncate(array $parts, string $separator): string
    {
        // Use first and last parts
        $first = Str::kebab($parts[0]);
        $last = Str::kebab(end($parts));
        
        $shortPath = $first . $separator . $last;
        
        if (strlen($shortPath) <= self::MAX_ROUTE_LENGTH) {
            return $shortPath;
        }

        // Further truncate using hash
        return $this->useHash($parts);
    }

    protected function useHash(array $parts): string
    {
        // Use first character of each part + hash of full string
        $prefix = implode('', array_map(function($part) {
            return substr(Str::kebab($part), 0, 1);
        }, $parts));
        
        $hash = substr(md5(implode('', $parts)), 0, 8);
        return $prefix . '-' . $hash;
    }

    public function generateRouteName(array $parts): string
    {
        $name = implode('.', array_map([Str::class, 'kebab'], $parts));
        
        if (strlen($name) <= self::MAX_NAME_LENGTH) {
            return $name;
        }

        // Use first and last with hash
        $shortName = Str::kebab($parts[0]) . '.' . Str::kebab(end($parts));
        
        if (strlen($shortName) <= self::MAX_NAME_LENGTH) {
            return $shortName;
        }

        return substr(md5($name), 0, 8);
    }
}
