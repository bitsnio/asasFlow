<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Route;

class RouteFormatter
{
    public function phpArray(array $values): string
    {
        if (empty($values)) {
            return '[]';
        }

        $items = array_map(
            static fn($value) => var_export($value, true),
            $values,
        );

        return '[' . implode(', ', $items) . ']';
    }

    public function wrapInMiddlewareGroup(string $routeStatement, array $middleware, string $indent): string
    {
        if (!$middleware) {
            return $indent . $routeStatement . "\n\n";
        }

        return $indent
            . 'Route::middleware(' . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent . '    ' . $routeStatement . "\n"
            . $indent . "});\n\n";
    }
}