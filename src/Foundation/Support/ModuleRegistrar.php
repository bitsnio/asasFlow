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
}
