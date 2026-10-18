<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Route;

use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Illuminate\Support\Str;

class RouteFileSynchronizer
{
    public function __construct(
        protected GeneratorSupport $support,
        protected RouteFileBuilder $builder,
    ) {}

    public function sync($module, string $document, array $definitions): array
    {
        $added = [];

        foreach ($definitions as $definition) {
            $this->syncNodeRoutes($module, $definition, [], 0, $document, $added);
        }

        return [$document, $added];
    }

    protected function syncNodeRoutes(
        $module,
        MenuDefinition $definition,
        array $parentMiddleware,
        int $level,
        string &$document,
        array &$added,
    ): void {
        if ($definition->isResource() && $definition->hasController()) {
            $controllerClass = $definition->controllerClass;
            $fqcn = $this->support->controllerNamespace($module, $definition) . '\\' . $controllerClass;

            if (!$this->hasApiResourceRoute($document, $definition)) {
                $document = $this->ensureImportExists($document, $fqcn);
                $additionalMiddleware = array_values(array_diff($definition->middleware, $parentMiddleware));

                $resourceSnippet = trim($this->builder->buildResourceRoute(
                    $module,
                    $definition,
                    $additionalMiddleware,
                    $level,
                ));

                $document = $this->insertRouteIntoParentGroup($document, $definition, $resourceSnippet, $level);
                $added[] = Str::kebab($definition->name);
            }

            foreach ($definition->customActions() as $actionName => $config) {
                $methodName = Str::camel($actionName);

                if (!$this->hasCustomActionRoute($document, $controllerClass, $methodName)) {
                    $document = $this->ensureImportExists($document, $fqcn);
                    $additionalMiddleware = array_values(array_diff($definition->middleware, $parentMiddleware));

                    $actionRouteSnippet = trim($this->builder->buildResourceActionRoute(
                        $module,
                        $definition,
                        $actionName,
                        $additionalMiddleware,
                        $level,
                    ));

                    $document = $this->insertAfterControllerRoutes($document, $controllerClass, $actionRouteSnippet, $level);
                    $added[] = Str::kebab($definition->name) . '/' . Str::kebab($actionName);
                }
            }
        }

        foreach ($definition->children as $child) {
            $this->syncNodeRoutes($module, $child, $definition->middleware, $level + 1, $document, $added);
        }
    }

    protected function hasApiResourceRoute(string $document, MenuDefinition $definition): bool
    {
        $resourceUri = preg_quote(Str::kebab($definition->name), '/');
        $controller = preg_quote((string) $definition->controllerClass, '/');
        $pattern = '/Route::apiResource\(\s*[\'"]' . $resourceUri . '[\'"]\s*,\s*' . $controller . '::class\s*\)/';

        return preg_match($pattern, $document) === 1;
    }

    protected function hasCustomActionRoute(string $document, string $controllerClass, string $methodName): bool
    {
        $controller = preg_quote($controllerClass, '/');
        $method = preg_quote($methodName, '/');
        $pattern = '/\[\s*' . $controller . '::class\s*,\s*[\'"]' . $method . '[\'"]\s*\]/';

        return preg_match($pattern, $document) === 1;
    }

    protected function insertAfterControllerRoutes(
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

    protected function insertRouteIntoParentGroup(
        string $document,
        MenuDefinition $definition,
        string $routeSnippet,
        int $level,
    ): string {
        $indent = str_repeat('    ', $level);
        $pathSegments = $definition->path;
        array_pop($pathSegments);
        $parentSegment = !empty($pathSegments) ? Str::kebab((string) end($pathSegments)) : null;

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

    protected function ensureImportExists(string $document, string $fqcn): string
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