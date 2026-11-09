<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class ModuleRegistrar
{
    public function __construct(
        private readonly Application $app,
        private readonly ModuleDiscovery $discovery,
        private readonly ModuleDependencyResolver $dependencies,
    ) {}

    public function register(): void
    {
        $modules = $this->discovery->all();
 
        // Validate requirements and calculate provider registration order.
        $ordered = $this->dependencies->resolve($modules);

        // Ensure module PSR-4 namespaces are registered in autoloader if newly scaffolded
        $this->ensureAutoloading($ordered);

        // Validate all providers before registering any of them.
        foreach ($ordered as $module) {
            $provider = $module['provider'];

            if (! class_exists($provider)) {
                throw new LogicException(
                    "Service provider [{$provider}] for module "
                        . "[{$module['name']}] does not exist."
                );
            }

            if (! is_subclass_of($provider, ServiceProvider::class)) {
                throw new LogicException(
                    "Service provider [{$provider}] must extend "
                        . ServiceProvider::class . '.'
                );
            }
        }

        $loadedProviders = $this->app->getLoadedProviders();

        foreach ($ordered as $module) {
            $provider = $module['provider'];

            // Avoid registering the same provider twice.
            if (isset($loadedProviders[$provider])) {
                continue;
            }

            $this->app->register($provider);
            $loadedProviders[$provider] = true;
        }
    }

    /**
     * Dynamically register module PSR-4 paths into Composer's autoloader
     * so newly scaffolded modules work immediately without requiring dump-autoload.
     *
     * @param array<string, array<string, mixed>> $modules
     */
    private function ensureAutoloading(array $modules): void
    {
        $loader = $this->resolveComposerLoader();

        foreach ($modules as $module) {
            $provider = $module['provider'] ?? '';

            if (is_string($provider) && ! class_exists($provider)) {
                if ($loader !== null) {
                    $basePath = rtrim((string) ($module['path'] ?? ''), '/\\');
                    $moduleComposer = $basePath . '/composer.json';
                    $hasRegisteredManifest = false;

                    // 1. Read module composer.json to register all custom PSR-4 paths if defined
                    if (is_file($moduleComposer)) {
                        $manifest = json_decode((string) file_get_contents($moduleComposer), true);
                        $psr4 = $manifest['autoload']['psr-4'] ?? null;

                        if (is_array($psr4)) {
                            foreach ($psr4 as $prefix => $relativePaths) {
                                foreach ((array) $relativePaths as $relPath) {
                                    $loader->addPsr4(
                                        rtrim((string) $prefix, '\\') . '\\',
                                        $basePath . '/' . trim((string) $relPath, '/\\') . '/'
                                    );
                                }
                            }
                            $hasRegisteredManifest = true;
                        }
                    }

                    // 2. Default standard module PSR-4 conventions
                    $namespace = rtrim((string) ($module['namespace'] ?? ''), '\\') . '\\';
                    $loader->addPsr4($namespace, $basePath . '/app/');
                    $loader->addPsr4($namespace . 'Database\\Factories\\', $basePath . '/database/factories/');
                    $loader->addPsr4($namespace . 'Database\\Seeders\\', $basePath . '/database/seeders/');
                }

                if (! class_exists($provider)) {
                    $providerFile = ($module['path'] ?? '') . '/app/Providers/' . class_basename($provider) . '.php';
                    if (is_file($providerFile)) {
                        require_once $providerFile;
                    }
                }
            }
        }
    }

    private function resolveComposerLoader(): ?\Composer\Autoload\ClassLoader
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && isset($autoloader[0]) && $autoloader[0] instanceof \Composer\Autoload\ClassLoader) {
                return $autoloader[0];
            }
        }

        return null;
    }
}
