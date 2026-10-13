<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands;

use Bitsnio\AsasFlow\Foundation\Support\ModuleReleaseInstaller;
use Illuminate\Console\Command;
use Throwable;

final class InstallModule extends Command
{
    protected $signature = 'asasflow:module-install
        {module : Configured module alias, e.g. system}
        {--allow-dev-fallback : Allow development fallback if a release is unavailable}
        {--dry-run : Download and validate without installing files}';

    protected $description = 'Download and install an AsasFlow module';

    public function handle(ModuleReleaseInstaller $installer): int
    {
        $alias = strtolower(trim((string) $this->argument('module')));

        $modules = config('asasflow.install.required_modules', []);
        $definition = $modules[$alias] ?? null;

        if (!is_array($definition)) {
            $this->components->error(
                "Module [{$alias}] is not configured under "
                . 'asasflow.install.required_modules.'
            );

            return self::FAILURE;
        }

        try {
            $result = $installer->install(
                alias: $alias,
                definition: $definition,
                allowDevFallback: (bool) $this->option('allow-dev-fallback'),
                dryRun: (bool) $this->option('dry-run'),
            );
        } catch (Throwable $e) {
            report($e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(
            sprintf(
                '%s module [%s], version [%s], source [%s].',
                $result['dry_run'] ? 'Validated' : 'Installed',
                $result['name'],
                $result['version'] ?? 'not specified',
                $result['source'],
            )
        );

        if ($result['dry_run']) {
            $this->components->warn(
                'Dry run complete. No module files were installed.'
            );

            return self::SUCCESS;
        }

        $this->line("Module path: {$result['path']}");

        $this->components->warn(
            'Run composer dump-autoload before using the new module. '
            . 'This does not install any missing third-party dependencies.'
        );

        return self::SUCCESS;
    }
}
