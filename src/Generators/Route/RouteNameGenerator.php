<?php

namespace Bitsnio\AsasFlow\Generators\Route;

use Illuminate\Support\Str;

class RouteNameGenerator
{
    protected const MAX_ROUTE_NAME_LENGTH = 60;

    protected const HASH_LENGTH = 8;

    public function generateRouteName(
        array $pathParts,
        ?string $action = null
    ): string {
        $parts = array_map(
            [Str::class, 'kebab'],
            $pathParts
        );

        $full =
            implode('.', $parts)
            . (
                $action
                    ? '.' . Str::kebab($action)
                    : ''
            );

        /*
         * URL hierarchy is NEVER shortened.
         *
         * Only route names are shortened when necessary.
         */
        if (
            strlen($full) <=
            self::MAX_ROUTE_NAME_LENGTH
        ) {
            return $full;
        }

        $prefix = implode(
            '_',
            array_map(
                fn ($part) =>
                    Str::substr(
                        $part,
                        0,
                        1
                    ),
                $parts
            )
        );

        $hash =
            substr(
                md5(
                    implode(
                        '|',
                        $parts
                    )
                ),
                0,
                self::HASH_LENGTH
            );

        return $prefix
            . '_'
            . $hash
            . (
                $action
                    ? '.' . Str::kebab($action)
                    : ''
            );
    }

    public function getTraceInfo(
        string $generatedName,
        array $pathParts,
        ?string $action = null
    ): array {
        return [
            'generated' =>
                $generatedName,

            'original_parts' =>
                $pathParts,

            'action' =>
                $action,

            'hash' =>
                substr(
                    md5(
                        implode(
                            '|',
                            array_map(
                                [Str::class, 'kebab'],
                                $pathParts
                            )
                        )
                    ),
                    0,
                    self::HASH_LENGTH
                ),

            'nesting_level' =>
                count($pathParts),

            'was_shortened' =>
                $generatedName !==
                $this->generateRouteName(
                    $pathParts,
                    $action
                ),
        ];
    }
}