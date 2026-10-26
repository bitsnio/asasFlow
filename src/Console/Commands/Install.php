<?php

namespace Bitsnio\AsasFlow\Console\Commands;

use Bitsnio\AsasFlow\Console\Commands\Installation\ComposerAutoloadInstaller;
use Bitsnio\AsasFlow\Console\Commands\Installation\DocumentationInstaller;
use Bitsnio\AsasFlow\Console\Commands\Installation\RequiredModulesInstaller;
use Bitsnio\AsasFlow\Foundation\Services\JwtSetupService;
use Bitsnio\AsasFlow\Foundation\Support\ModuleReleaseInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class Install extends Command
{
    protected $signature = 'asasflow:install
        {--migrate : Run database migrations after installation}
        {--allow-dev-fallback : Allow development module fallback when release is unavailable}
        {--dry-run : Validate required module installation without writing module files}';

    protected $description = 'Install and configure the AsasFlow package environment';

    public function handle(): int
    {
        $this->info('Starting AsasFlow installation...');

        if (! File::exists(config_path('larecipe.php'))) {
            $this->info('Setting up LaRecipe documentation engine...');
            if ($this->call('larecipe:install') !== self::SUCCESS) {
                $this->error('LaRecipe installation failed.');
                return self::FAILURE;
            }
        }

        // Do not force-publish over an existing host config.
        $this->call('vendor:publish', ['--tag' => 'asasflow-config']);
        $this->call('config:clear');

        if (! app(DocumentationInstaller::class)->install($this)) return self::FAILURE;
        // if (! app(RequiredModulesInstaller::class)->install($this, app(ModuleReleaseInstaller::class))) {
        //     $this->error('One or more required modules could not be installed.');
        //     return self::FAILURE;
        // }
        if (! app(ComposerAutoloadInstaller::class)->install($this)) return self::FAILURE;

        $this->setupJwt();
        $this->call('config:clear');
        if ($this->option('migrate')) {
            $this->info('Running database migrations...');
            if ($this->call('migrate') !== self::SUCCESS) {
                $this->error('Database migrations failed.');
                return self::FAILURE;
            }
        }

        $this->info('AsasFlow installation complete!');
        return self::SUCCESS;
    }

    private function setupJwt(): void
    {
        $this->newLine();
        $this->components->info('Checking host JWT configuration...');
        $result = app(JwtSetupService::class)->setup();
        foreach ([
            'dependency' => 'JWT package is available.',
            'jwt_config' => 'JWT configuration is ready.',
            'jwt_secret' => 'JWT secret is configured.',
            'auth_config' => 'JWT API guard and user provider are configured.',
        ] as $key => $message) {
            if (! empty($result[$key])) $this->components->info($message);
        }
        foreach (($result['messages'] ?? []) as $message) $this->line("  - {$message}");
        foreach (($result['errors'] ?? []) as $error) $this->components->warn($error);
        $this->newLine();
    }
}
