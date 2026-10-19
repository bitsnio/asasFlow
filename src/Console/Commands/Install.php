<?php

namespace Bitsnio\AsasFlow\Console\Commands;

use Bitsnio\AsasFlow\Foundation\Services\JwtSetupService;
use Bitsnio\AsasFlow\Foundation\Support\ModuleReleaseInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

class Install extends Command
{
    protected $signature = 'asasflow:install
        {--migrate : Run database migrations after installation}
        {--allow-dev-fallback : Allow development module fallback when release is unavailable}
        {--dry-run : Validate required module installation without writing module files}';

    protected $description =
        'Install and configure the AsasFlow package environment';

    public function handle(): int
    {
        $this->info('Starting AsasFlow installation...');

        // 1. Install LaRecipe only if it has not been configured.
        if (! File::exists(config_path('larecipe.php'))) {
            $this->info('Setting up LaRecipe documentation engine...');

            if ($this->call('larecipe:install') !== self::SUCCESS) {
                $this->error('LaRecipe installation failed.');

                return self::FAILURE;
            }
        }

        // 2. Publish package config without forcing an overwrite.
        $this->call('vendor:publish', [
            '--tag' => 'asasflow-config',
        ]);

        // Clear cached config before relying on newly published defaults.
        $this->call('config:clear');

        // 3. Resolve documentation paths.
        $version = (string) config('asasflow.docs.default_version', '1.0');
        $larecipeBase = base_path(
            config('larecipe.docs.path', 'resources/docs')
        );
        $hostVersionPath = $larecipeBase . DIRECTORY_SEPARATOR . $version;
        $ourDocsSource = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'docs'
            . DIRECTORY_SEPARATOR . $version;

        if (! File::isDirectory($ourDocsSource)) {
            $this->error("Package docs source not found at: {$ourDocsSource}");

            return self::FAILURE;
        }

        // 4. Preserve the documentation mode chosen on the first install.
        $alreadyInstalled = (bool) config('asasflow.installed', false);

        if ($alreadyInstalled) {
            $shouldAdoptAsDefault =
                ! (bool) config('asasflow.enabled', true)
                && (bool) config('asasflow.redirect_root', true);
        } else {
            $shouldAdoptAsDefault = $this->isCleanDocsDirectory(
                $hostVersionPath
            );
        }

        // 5. Copy documentation without overwriting files.
        if ($shouldAdoptAsDefault) {
            $this->info(
                'Using AsasFlow documentation as the default /docs content...'
            );

            $this->copyMissingFiles($ourDocsSource, $hostVersionPath);
            $this->info("Documentation destination: {$hostVersionPath}");
        } else {
            $packageDocsPath = $larecipeBase
                . DIRECTORY_SEPARATOR . 'asasflow'
                . DIRECTORY_SEPARATOR . $version;

            $this->info(
                'Existing host documentation detected; using a separate AsasFlow folder...'
            );

            $this->copyMissingFiles($ourDocsSource, $packageDocsPath);
            $this->info("Documentation destination: {$packageDocsPath}");

            $this->injectSidebarLink(
                $hostVersionPath,
                $version
            );
        }

        // 6. Update only the specific AsasFlow flags we own.
        $configPath = config_path('asasflow.php');

        if (File::exists($configPath)) {
            $content = File::get($configPath);

            $enabled = $shouldAdoptAsDefault ? 'false' : 'true';
            $redirectRoot = $shouldAdoptAsDefault ? 'true' : 'false';

            $content = $this->replaceTopLevelBoolean(
                $content,
                'installed',
                'true'
            );

            $content = $this->replaceTopLevelBoolean(
                $content,
                'enabled',
                $enabled
            );

            $content = $this->replaceTopLevelBoolean(
                $content,
                'redirect_root',
                $redirectRoot
            );

            File::put($configPath, $content);
        } else {
            $this->warn(
                'config/asasflow.php was not found; package configuration flags were not updated.'
            );
        }

        // 7. Install configured required modules.
        // Do this before JWT auth configuration so Core can be installed first.
        if (! $this->installRequiredModules(
            app(ModuleReleaseInstaller::class)
        )) {
            $this->error('One or more required modules could not be installed.');

            return self::FAILURE;
        }

        // 8. Ensure Composer module autoloading is configured.
        if (! $this->registerAutoloading()) {
            return self::FAILURE;
        }

        // 9. Configure host-level JWT prerequisites and auth guard/provider.
        $this->setupJwt();

        // 10. Clear config cache after all configuration changes.
        $this->call('config:clear');

        $this->info('AsasFlow installation complete!');

        if ($this->option('migrate')) {
            $this->info('Running database migrations...');

            if ($this->call('migrate') !== self::SUCCESS) {
                $this->error('Database migrations failed.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function isCleanDocsDirectory(string $path): bool
    {
        if (! File::isDirectory($path)) {
            return true;
        }

        $files = array_values(array_diff(
            scandir($path) ?: [],
            ['.', '..']
        ));

        $scaffoldOnly = collect($files)->every(
            fn (string $file): bool => in_array(
                $file,
                ['index.md', 'overview.md'],
                true
            )
        );

        return $files === [] || $scaffoldOnly;
    }

    /**
     * Copy only missing files, preserving any host-edited documentation.
     */
    private function copyMissingFiles(
        string $source,
        string $destination
    ): void {
        File::ensureDirectoryExists($destination);

        foreach (File::allFiles($source) as $file) {
            $relativePath = ltrim(
                substr($file->getPathname(), strlen($source)),
                DIRECTORY_SEPARATOR
            );

            $target = $destination . DIRECTORY_SEPARATOR . $relativePath;

            if (File::exists($target)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($file->getPathname(), $target);
        }
    }

    private function injectSidebarLink(
        string $hostVersionPath,
        string $version
    ): void {
        $hostIndexFile = $hostVersionPath
            . DIRECTORY_SEPARATOR . 'index.md';

        if (! File::exists($hostIndexFile)) {
            $this->warn(
                "Could not find {$hostIndexFile}; add the AsasFlow sidebar link manually."
            );

            return;
        }

        $content = File::get($hostIndexFile);
        $page = (string) config('asasflow.docs.default_page', 'overview');

        $link = "/docs/asasflow/{$version}/{$page}";

        // Stable URL check prevents duplicate links on repeated runs.
        if (str_contains($content, $link)) {
            $this->line('AsasFlow sidebar link already exists; skipping.');

            return;
        }

        $injection = "\n\n- ## AsasFlow Docs\n"
            . "    - [AsasFlow Overview]({$link})\n";

        File::put($hostIndexFile, rtrim($content) . $injection);

        $this->info('Injected AsasFlow link into host sidebar.');
    }

    /**
     * Replace only the first matching top-level boolean config entry.
     */
    private function replaceTopLevelBoolean(
        string $content,
        string $key,
        string $value
    ): string {
        $pattern = '/([\'"]' . preg_quote($key, '/') . '[\'"]\s*=>\s*)(true|false)\b/';

        $count = 0;

        $updated = preg_replace(
            $pattern,
            '$1' . $value,
            $content,
            1,
            $count
        );

        if ($count === 0) {
            $this->warn(
                "Could not locate the [{$key}] boolean in config/asasflow.php."
            );

            return $content;
        }

        return $updated ?? $content;
    }

    private function registerAutoloading(): bool
    {
        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            $this->error('Host composer.json was not found.');

            return false;
        }

        try {
            $composer = json_decode(
                File::get($composerPath),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            $this->error('Host composer.json contains invalid JSON.');

            return false;
        }

        if (! is_array($composer)) {
            $this->error('Host composer.json must contain a JSON object.');

            return false;
        }

        $modified = false;

        $composer['autoload'] ??= [];
        $composer['autoload']['psr-4'] ??= [];

        // Remove the global Modules\ mapping that conflicts with module mappings.
        if (array_key_exists('Modules\\', $composer['autoload']['psr-4'])) {
            unset($composer['autoload']['psr-4']['Modules\\']);
            $modified = true;

            $this->info('Removed conflicting Modules\\ PSR-4 catch-all.');
        }

        $composer['extra'] ??= [];
        $mergePlugin = $composer['extra']['merge-plugin'] ?? [];
        $mergePlugin['include'] ??= [];

        if (! is_array($mergePlugin['include'])) {
            $this->error(
                'composer.json extra.merge-plugin.include must be an array.'
            );

            return false;
        }

        $include = 'Modules/*/composer.json';

        if (! in_array($include, $mergePlugin['include'], true)) {
            $mergePlugin['include'][] = $include;
            $modified = true;

            $this->info(
                'Added Modules/*/composer.json to composer-merge-plugin.'
            );
        }

        $composer['extra']['merge-plugin'] = $mergePlugin;

        if (! $modified) {
            $this->info('Composer autoload entries already present; skipping.');

            return true;
        }

        File::put(
            $composerPath,
            json_encode(
                $composer,
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            ) . PHP_EOL
        );

        $this->newLine();
        $this->warn(
            'composer.json was updated. Run the following to refresh autoloading:'
        );
        $this->line('  composer dump-autoload');
        $this->newLine();

        return true;
    }

    private function setupJwt(): void
    {
        $this->newLine();
        $this->components->info('Checking host JWT configuration...');

        $result = app(JwtSetupService::class)->setup();

        if ($result['dependency']) {
            $this->components->info('JWT package is available.');
        }

        if ($result['jwt_config']) {
            $this->components->info('JWT configuration is ready.');
        }

        if ($result['jwt_secret']) {
            $this->components->info('JWT secret is configured.');
        }

        if ($result['auth_config']) {
            $this->components->info(
                'JWT API guard and user provider are configured.'
            );
        }

        foreach ($result['messages'] as $message) {
            $this->line("  - {$message}");
        }

        foreach ($result['errors'] as $error) {
            $this->components->warn($error);
        }

        $this->newLine();
    }

    private function installRequiredModules(
        ModuleReleaseInstaller $installer
    ): bool {
        $modules = config('asasflow.install.required_modules', []);

        if (! is_array($modules)) {
            $this->components->error(
                'Invalid asasflow.install.required_modules configuration.'
            );

            return false;
        }

        foreach ($modules as $alias => $definition) {
            if (! is_array($definition)) {
                $this->components->error(
                    "Invalid configuration for module [{$alias}]."
                );

                return false;
            }

            try {
                $result = $installer->install(
                    alias: (string) $alias,
                    definition: $definition,
                    allowDevFallback: (bool) $this->option('allow-dev-fallback'),
                    dryRun: (bool) $this->option('dry-run'),
                );

                $this->components->info(
                    sprintf(
                        '%s module [%s] using [%s].',
                        $result['dry_run'] ? 'Validated' : 'Installed',
                        $result['name'],
                        $result['source'],
                    )
                );
            } catch (\Throwable $e) {
                report($e);

                $this->components->error(
                    "Failed to install module [{$alias}]: {$e->getMessage()}"
                );

                return false;
            }
        }

        return true;
    }
}
