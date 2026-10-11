<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ModuleScaffolder
{
    public function __construct(
        private readonly StubRenderer $stubs,
        private readonly FileHandler $files,
    ) {}

    /**
     * Create all enabled scaffold directories.
     */
    public function createDirectories(ModuleDefinition $module): void
    {
        $this->files->ensureDirectoryExists($module->path);

        foreach ($this->scaffold() as $name => $definition) {
            if (! ($definition['enabled'] ?? false)) {
                continue;
            }

            $this->assertDirectoryDefinition($name, $definition);

            $relativePath = $this->resolvePath(
                (string) $definition['path'],
                $this->replacements($module),
            );

            $this->files->ensureDirectoryExists(
                $this->modulePath($module, $relativePath),
            );
        }
    }

    /**
     * Generate enabled starter files.
     *
     * @return array<int, array{path: string, action: string}>
     */
    public function createFiles(
        ModuleDefinition $module,
        bool $force = false,
    ): array {
        $this->createDirectories($module);

        $baseReplacements = $this->replacements($module);
        $results = [];

        foreach ($this->scaffold() as $name => $definition) {
            if (! ($definition['enabled'] ?? false)) {
                continue;
            }

            $this->assertDirectoryDefinition($name, $definition);

            $stubDefinition = $definition['stub'] ?? [];

            if (! is_array($stubDefinition)) {
                throw new InvalidArgumentException(
                    "Invalid stub configuration for scaffold entry [{$name}].",
                );
            }

            if (! ($stubDefinition['enabled'] ?? false)) {
                continue;
            }

            $stubName = trim((string) ($stubDefinition['stub'] ?? ''));
            $configuredPath = trim((string) ($stubDefinition['path'] ?? ''));

            if ($stubName === '' || $configuredPath === '') {
                throw new InvalidArgumentException(
                    "Enabled stub for scaffold entry [{$name}] "
                    . 'must define both path and stub.',
                );
            }

            $this->assertSafeStubName($stubName);

            $relativePath = $this->resolvePath(
                $configuredPath,
                $baseReplacements,
            );

            $path = $this->modulePath($module, $relativePath);

            if ($this->files->exists($path) && ! $force) {
                $results[] = [
                    'path' => $path,
                    'action' => 'skipped-existing',
                ];

                continue;
            }

            $customReplacements = $stubDefinition['replacements'] ?? [];

            if (! is_array($customReplacements)) {
                throw new InvalidArgumentException(
                    "Replacements for scaffold entry [{$name}] must be an array.",
                );
            }

            $replacements = array_replace(
                $baseReplacements,
                $customReplacements,
            );

            $content = $this->stubs->renderFile(
                $stubName,
                $replacements,
            );

            $this->files->writeFile(
                $path,
                $content,
                force: $force,
            );

            $results[] = [
                'path' => $path,
                'action' => 'generated',
            ];
        }

        return $results;
    }

    /**
     * Return the configured scaffold entries.
     */
    private function scaffold(): array
    {
        $scaffold = config('asasflow.modules.scaffold', []);

        if (! is_array($scaffold)) {
            throw new RuntimeException(
                'The asasflow.modules.scaffold configuration must be an array.',
            );
        }

        return $scaffold;
    }

    /**
     * Validate the common directory-level structure.
     */
    private function assertDirectoryDefinition(
        string $name,
        array $definition,
    ): void {
        if (($definition['type'] ?? null) !== 'directory') {
            throw new InvalidArgumentException(
                "Scaffold entry [{$name}] must have type [directory].",
            );
        }

        if (
            ! isset($definition['path'])
            || ! is_string($definition['path'])
            || trim($definition['path']) === ''
        ) {
            throw new InvalidArgumentException(
                "Scaffold entry [{$name}] must define a directory path.",
            );
        }
    }

    /**
     * Resolve placeholders in a configured relative path.
     */
    private function resolvePath(
        string $path,
        array $replacements,
    ): string {
        foreach ($replacements as $key => $value) {
            $path = str_replace(
                [
                    '{{ ' . $key . ' }}',
                    '{{' . $key . '}}',
                ],
                (string) $value,
                $path,
            );
        }

        $path = str_replace('\\', '/', trim($path));

        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path)
        ) {
            throw new InvalidArgumentException(
                "Scaffold path must be relative: [{$path}].",
            );
        }

        $segments = explode('/', $path);

        if (in_array('..', $segments, true)) {
            throw new InvalidArgumentException(
                "Scaffold path cannot traverse outside the module: [{$path}].",
            );
        }

        $segments = array_values(array_filter(
            $segments,
            static fn (string $segment): bool =>
                $segment !== '' && $segment !== '.',
        ));

        return implode('/', $segments);
    }

    /**
     * Resolve a validated relative path under the module root.
     */
    private function modulePath(
        ModuleDefinition $module,
        string $relativePath,
    ): string {
        if ($relativePath === '') {
            return rtrim($module->path, '/\\');
        }

        return rtrim($module->path, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath,
            );
    }

    /**
     * Prevent configured stub paths from escaping the stubs directory.
     */
    private function assertSafeStubName(string $stubName): void
    {
        $stubName = str_replace('\\', '/', $stubName);

        if (
            str_starts_with($stubName, '/')
            || preg_match('/^[A-Za-z]:/', $stubName)
            || in_array('..', explode('/', $stubName), true)
        ) {
            throw new InvalidArgumentException(
                "Stub path must be relative to the stubs directory: [{$stubName}].",
            );
        }
    }

    /**
     * Standard replacements available to every module stub.
     */
    private function replacements(ModuleDefinition $module): array
    {
        $name = $module->name;
        $lower = Str::kebab($name);
        $title = Str::headline($name);
        $provider = $module->providerClass();

        return [
            'MODULE' => $name,
            'MODULE_LOWER' => $lower,
            'MODULE_TITLE' => $title,
            'MODULE_NAMESPACE' => $module->namespace,

            'MODULE_COMPOSER_NAMESPACE' => str_replace(
                '\\',
                '\\\\',
                $module->namespace . '\\',
            ),

            'MODULE_PROVIDER' => $provider,

            'AUTHOR_NAME' => (string) config(
                'asasflow.modules.composer.author',
                'Bitsnio',
            ),

            'AUTHOR_EMAIL' => (string) config(
                'asasflow.modules.composer.email',
                '',
            ),

            'MODULE_NAMESPACE_JSON' => str_replace(
                '\\',
                '\\\\',
                $module->namespace,
            ),

            'MODULE_PROVIDER_JSON' => str_replace(
                '\\',
                '\\\\',
                $provider,
            ),
        ];
    }
}
