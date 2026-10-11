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
    ) {}

    public function register(): void
    {
        foreach ($this->discovery->all() as $module) {
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

            $this->app->register($provider);
        }
    }
}