<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

class GeneratorSupport
{
    public function moduleNamespace($module): string
    {
        return "Modules\\{$module->getName()}";
    }

    public function controllerNamespace(
        $module,
        MenuDefinition $definition
    ): string {
        return $this->appendNamespace(
            $this->moduleNamespace($module) . '\\Http\\Controllers',
            $definition->controllerNamespace()
        );
    }

    public function requestNamespace(
        $module,
        MenuDefinition $definition
    ): string {
        return $this->appendNamespace(
            $this->moduleNamespace($module) . '\\Http\\Requests',
            $definition->controllerNamespace()
        );
    }

    public function resourceNamespace(
        $module,
        MenuDefinition $definition
    ): string {
        return $this->appendNamespace(
            $this->moduleNamespace($module) . '\\Http\\Resources',
            $definition->resourceNamespace()
        );
    }

    public function modelNamespace($module): string
    {
        return $this->moduleNamespace($module) . '\\Models';
    }

    protected function appendNamespace(
        string $base,
        ?string $relative
    ): string {
        $relative = trim((string) $relative, '\\');

        return $relative !== ''
            ? $base . '\\' . $relative
            : $base;
    }

    /**
     * Return normalized, unique fully qualified class names.
     */
    public function normalizeClasses(array $classes): array
    {
        $normalized = [];

        foreach ($classes as $class) {
            if (!is_string($class) || trim($class) === '') {
                continue;
            }

            $class = ltrim(trim($class), '\\');

            if (!in_array($class, $normalized, true)) {
                $normalized[] = $class;
            }
        }

        return $normalized;
    }

    /**
     * Format fully qualified class names as PHP import statements.
     */
    public function imports(array $classes): string
    {
        $lines = array_map(
            static fn(string $class): string => "use {$class};",
            $this->normalizeClasses($classes)
        );

        return implode(PHP_EOL, $lines);
    }

    public function configuredImports(string $generator): array
    {
        $imports = config(
            "asasFlow.generators.{$generator}.imports",
            []
        );

        return is_array($imports)
            ? $this->normalizeClasses($imports)
            : [];
    }

    /**
     * Required imports are supplied by the generator.
     * Configured imports are optional project-wide additions.
     */
    public function generatorImports(
        string $generator,
        array $requiredImports = []
    ): string {
        return $this->imports([
            ...$requiredImports,
            ...$this->configuredImports($generator),
        ]);
    }

    public function configuredTraits(string $generator): array
    {
        $traits = config(
            "asasFlow.generators.{$generator}.traits",
            []
        );

        return is_array($traits)
            ? $this->normalizeClasses($traits)
            : [];
    }

    public function traitImports(string $generator): string
    {
        return $this->imports(
            $this->configuredTraits($generator)
        );
    }

    /**
     * Generate trait usage statements for inside a PHP class.
     */
    public function traitUsage(string $generator): string
    {
        $traits = $this->configuredTraits($generator);

        if ($traits === []) {
            return '';
        }

        $shortNames = [];

        foreach ($traits as $trait) {
            $shortName = class_basename($trait);

            if (in_array($shortName, $shortNames, true)) {
                throw new \InvalidArgumentException(
                    "Configured {$generator} traits contain duplicate "
                        . "short name [{$shortName}]. Use traits with unique names."
                );
            }

            $shortNames[] = $shortName;
        }

        return implode(
            PHP_EOL,
            array_map(
                static fn(string $trait): string =>
                '    use ' . class_basename($trait) . ';',
                $traits
            )
        );
    }

    public function featureEnabled(
        string $generator,
        string $feature
    ): bool {
        return (bool) config(
            "asasFlow.generators.{$generator}.features.{$feature}.enabled",
            false
        );
    }

    public function featureConfig(
        string $generator,
        string $feature,
        mixed $default = []
    ): mixed {
        return config(
            "asasFlow.generators.{$generator}.features.{$feature}",
            $default
        );
    }
}
