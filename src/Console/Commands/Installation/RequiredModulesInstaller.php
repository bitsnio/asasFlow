<?php

namespace Bitsnio\AsasFlow\Console\Commands\Installation;

use Bitsnio\AsasFlow\Foundation\Support\ModuleReleaseInstaller;
use Illuminate\Console\Command;
use Throwable;

class RequiredModulesInstaller
{
    public function install(Command $command, ModuleReleaseInstaller $installer): bool
    {
        $modules = config('asasflow.install.required_modules', []);
        if (! is_array($modules)) { $command->components->error('Invalid asasflow.install.required_modules configuration.'); return false; }
        foreach ($modules as $alias => $definition) {
            if (! is_array($definition)) { $command->components->error("Invalid configuration for module [{$alias}]."); return false; }
            try {
                $result = $installer->install(
                    alias: (string) $alias,
                    definition: $definition,
                    allowDevFallback: (bool) $command->option('allow-dev-fallback'),
                    dryRun: (bool) $command->option('dry-run'),
                );
                $command->components->info(sprintf('%s module [%s] using [%s].',
                    ($result['dry_run'] ?? false) ? 'Validated' : 'Installed',
                    $result['name'] ?? $alias,
                    $result['source'] ?? 'configured source'));
            } catch (Throwable $e) {
                report($e);
                $command->components->error("Failed to install module [{$alias}]: {$e->getMessage()}");
                return false;
            }
        }
        return true;
    }
}
