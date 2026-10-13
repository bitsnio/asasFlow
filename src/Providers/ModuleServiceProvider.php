<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Providers;

use Illuminate\Support\ServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use LogicException;
use UnexpectedValueException;

abstract class ModuleServiceProvider extends ServiceProvider
{
    protected string $name;

    protected string $nameLower;

    protected array $commands = [];

    protected array $providers = [];

    public function __construct($app)
    {
        if (! isset($this->name, $this->nameLower)) {
            throw new LogicException(
                'Module service provider must define both $name and $nameLower properties.'
            );
        }

        parent::__construct($app);
    }

    public function register(): void
    {
        foreach ($this->providers as $provider) {
            $this->app->register($provider);
        }

        $this->registerModuleConfig();
    }

    protected function registerModuleConfig(): void
    {
        $configPath = $this->modulePath('config');

        if (! is_dir($configPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $configPath,
                FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getPathname();
            $relativePath = substr($filePath, strlen($configPath) + 1);
            $relativeKey = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);

            // config/config.php serves as the module's primary configuration.
            if ($relativeKey === 'config') {
                $key = $this->nameLower;
            } else {
                $segments = explode('.', 'modules.' . $this->nameLower . '.' . $relativeKey);

                // Avoid adjacent duplicate segments.
                $normalized = [];
                foreach ($segments as $segment) {
                    if (end($normalized) !== $segment) {
                        $normalized[] = $segment;
                    }
                }

                $key = implode('.', $normalized);
            }

            $moduleConfig = require $filePath;

            if (! is_array($moduleConfig)) {
                throw new UnexpectedValueException(
                    "Module config must return an array: {$filePath}"
                );
            }

            // Preserve existing config values and recursively merge defaults.
            $existing = config($key, []);

            config([
                $key => array_replace_recursive(
                    is_array($existing) ? $existing : [],
                    $moduleConfig,
                ),
            ]);
        }
    }

    protected function modulePath(string $path = ''): string
    {
        $basePath = config('asasflow.modules.path', base_path('Modules'));

        return rtrim($basePath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $this->name
            . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    protected function registerTranslations(): void
    {
        $langPath = $this->modulePath('lang');

        if (! is_dir($langPath)) {
            return;
        }

        $this->loadTranslationsFrom($langPath, $this->nameLower);
        $this->loadJsonTranslationsFrom($langPath);
    }

    protected function registerRoutes(): void
    {
        $routeFiles = [
            'routes/api.php',
            'routes/custom.php',
        ];

        foreach ($routeFiles as $routeFile) {
            $routesPath = $this->modulePath($routeFile);

            if (is_file($routesPath)) {
                $this->loadRoutesFrom($routesPath);
            }
        }
    }

    protected function registerMigrations(): void
    {
        $migrationsPath = $this->modulePath('database/migrations');

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    public function boot(): void
    {
        $this->commands($this->commands);

        $this->registerTranslations();

        $this->registerRoutes();

        $this->registerMigrations();
    }
}
