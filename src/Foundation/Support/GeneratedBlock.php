<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use RuntimeException;

class GeneratedBlock
{
    public const PREFIX = '@asasflow:';

    public static function wrap(
        string $name,
        string $content,
        string $indent = ''
    ): string {
        return $indent . '// @asasflow:' . $name . ':start'
            . PHP_EOL
            . $content
            . (str_ends_with($content, PHP_EOL) ? '' : PHP_EOL)
            . $indent . '// @asasflow:' . $name . ':end';
    }

    public static function replace(
        string $document,
        string $name,
        string $content
    ): string {
        $pattern = self::pattern($name);

        $updated = preg_replace_callback(
            $pattern,
            static function (array $matches) use ($content): string {
                return $matches[1]
                    . $content
                    . $matches[3];
            },
            $document,
            1
        );

        if ($updated === null) {
            throw new RuntimeException(
                "Unable to update generated block [{$name}]."
            );
        }

        return $updated;
    }

    public static function has(
        string $document,
        string $name
    ): bool {
        return preg_match(
            self::pattern($name),
            $document
        ) === 1;
    }

    protected static function pattern(string $name): string
    {
        $marker = preg_quote($name, '/');

        return '/(^[ \t]*\/\/ @asasflow:'
            . $marker
            . ':start[^\r\n]*\r?\n)'
            . '(.*?)'
            . '(^[ \t]*\/\/ @asasflow:'
            . $marker
            . ':end[^\r\n]*$)/ms';
    }
}