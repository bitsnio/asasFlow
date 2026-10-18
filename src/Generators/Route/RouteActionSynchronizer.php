<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Route;

use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Illuminate\Support\Str;

class RouteActionSynchronizer
{
    /**
     * Checks if the apiResource route already exists.
     */
    public function hasApiResourceRoute(string $document, MenuDefinition $definition): bool
    {
        $resourceUri = preg_quote(Str::kebab($definition->name), '/');
        $controller = preg_quote((string) $definition->controllerClass, '/');

        $pattern = '/Route::apiResource\(\s*[\'"]' . $resourceUri . '[\'"]\s*,\s*' . $controller . '::class\s*\)/';

        return preg_match($pattern, $document) === 1;
    }

    /**
     * Checks if a custom action route already exists.
     */
    public function hasCustomActionRoute(string $document, string $controllerClass, string $methodName): bool
    {
        $controller = preg_quote($controllerClass, '/');
        $method = preg_quote($methodName, '/');

        $pattern = '/\[\s*' . $controller . '::class\s*,\s*[\'"]' . $method . '[\'"]\s*\]/';

        return preg_match($pattern, $document) === 1;
    }

    /**
     * Inserts a new custom action route immediately after the last route statement
     * referencing the same Controller class.
     */
    public function insertAfterControllerRoutes(
        string $document,
        string $controllerClass,
        string $routeSnippet,
        int $level,
    ): string {
        $indent = str_repeat('    ', $level);
        $controller = preg_quote($controllerClass, '/');
        $pattern = '/[^\n]*' . $controller . '::class[^\n;]*;/';

        if (preg_match_all($pattern, $document, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            $insertPos = $lastMatch[1] + strlen($lastMatch[0]);

            return substr($document, 0, $insertPos)
                . "\n\n"
                . $indent . $routeSnippet
                . substr($document, $insertPos);
        }

        return $document;
    }

    /**
     * Inserts a newly added resource into its parent Route::prefix('...') group.
     */
    public function insertRouteIntoParentGroup(
        string $document,
        MenuDefinition $definition,
        string $routeSnippet,
        int $level,
    ): string {
        $indent = str_repeat('    ', $level);
        $pathSegments = $definition->path;
        array_pop($pathSegments);

        $parentSegment = !empty($pathSegments)
            ? Str::kebab((string) end($pathSegments))
            : null;

        if ($parentSegment !== null) {
            $pattern = '/Route::prefix\(\s*[\'"]' . preg_quote($parentSegment, '/') . '[\'"]\s*\)/';

            if (preg_match($pattern, $document, $match, PREG_OFFSET_CAPTURE)) {
                $groupStart = strpos($document, '{', $match[0][1]);

                if ($groupStart !== false) {
                    $groupEnd = $this->findMatchingClosingBrace($document, $groupStart);

                    if ($groupEnd !== null) {
                        $beforeEnd = rtrim(substr($document, 0, $groupEnd));
                        $afterEnd = substr($document, $groupEnd);
                        $parentIndent = str_repeat('    ', max(0, $level - 1));

                        return $beforeEnd
                            . "\n\n"
                            . $indent . $routeSnippet
                            . "\n\n"
                            . $parentIndent . $afterEnd;
                    }
                }
            }
        }

        return rtrim($document) . "\n\n" . $indent . $routeSnippet . "\n";
    }

    protected function findMatchingClosingBrace(string $document, int $openBracePos): ?int
    {
        $length = strlen($document);
        $depth = 0;

        for ($i = $openBracePos; $i < $length; $i++) {
            if ($document[$i] === '{') {
                $depth++;
            } elseif ($document[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Ensures controller FQCN import exists at the top of the route file.
     */
    public function ensureImportExists(string $document, string $fqcn): string
    {
        $importPattern = '/^use\s+' . preg_quote($fqcn, '/') . '\s*;/m';

        if (preg_match($importPattern, $document) === 1) {
            return $document;
        }

        $useStatement = "use {$fqcn};";

        if (preg_match_all('/^use\s+[^\n;]+;/m', $document, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            $insertPos = $lastMatch[1] + strlen($lastMatch[0]);

            return substr($document, 0, $insertPos)
                . PHP_EOL
                . $useStatement
                . substr($document, $insertPos);
        }

        return "<?php\n\n{$useStatement}\n" . ltrim($document, "<?php\n\r ");
    }
}