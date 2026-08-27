<?php

namespace Bitsnio\AsasFlow\Generators\Controller;

use Illuminate\Support\Str;

class RouteNameGenerator
{
    protected const MAX_ROUTE_LENGTH = 64;
    protected const MAX_NESTING_LEVEL = 3;

    public function generateRoutePath(array $pathParts): string
    {
        if (count($pathParts) > self::MAX_NESTING_LEVEL) {
            $pathParts = $this->reduceNestingDepth($pathParts);
        }

        $fullPath = implode("/", array_map([Str::class, "kebab"], $pathParts));
        
        if (strlen($fullPath) <= self::MAX_ROUTE_LENGTH) {
            return $fullPath;
        }

        return $this->generateTraceablePath($pathParts);
    }

    protected function generateTraceablePath(array $pathParts): string
    {
        $prefix = implode("_", array_map(function($part) {
            return substr(Str::kebab($part), 0, 1);
        }, $pathParts));
        
        $hash = $this->generateDeterministicHash($pathParts);
        
        $path = $prefix . "_" . $hash;
        
        if (strlen($path) > self::MAX_ROUTE_LENGTH) {
            $hash = substr($hash, 0, 8);
            $path = $prefix . "_" . $hash;
        }
        
        return $path;
    }

    protected function generateDeterministicHash(array $pathParts): string
    {
        $fullPath = implode("|", array_map([Str::class, "kebab"], $pathParts));
        return substr(md5($fullPath), 0, 10);
    }

    protected function reduceNestingDepth(array $parts): array
    {
        $length = count($parts);
        if ($length <= self::MAX_NESTING_LEVEL) {
            return $parts;
        }

        $reduced = [$parts[0]];
        
        if ($length > 3) {
            $middleIndex = floor($length / 2);
            $reduced[] = $parts[$middleIndex];
        }
        
        $reduced[] = end($parts);
        
        return $reduced;
    }

    public function generateRouteName(array $pathParts): string
    {
        $fullName = implode(".", array_map([Str::class, "kebab"], $pathParts));
        
        if (strlen($fullName) <= 60) {
            return $fullName;
        }

        $prefix = implode("_", array_map(function($part) {
            return substr(Str::kebab($part), 0, 1);
        }, $pathParts));
        
        $hash = substr(md5(implode("|", $pathParts)), 0, 8);
        
        return $prefix . "_" . $hash;
    }

    public function getTraceInfo(string $generatedPath, array $pathParts): array
    {
        return [
            "generated" => $generatedPath,
            "original_parts" => $pathParts,
            "hash" => $this->generateDeterministicHash($pathParts),
            "nesting_level" => count($pathParts),
            "was_truncated" => $generatedPath !== implode("/", array_map([Str::class, "kebab"], $pathParts)),
        ];
    }
}
