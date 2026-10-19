#!/usr/bin/env bash
set -Eeuo pipefail

# Run from the root of the AsasFlow package repository.
ROOT="$(pwd)"
if [[ ! -f "$ROOT/composer.json" || ! -d "$ROOT/src" ]]; then
  echo "Run this script from the AsasFlow package root (composer.json and src/ must exist)." >&2
  exit 1
fi

INSTALL="$ROOT/src/Console/Commands/Install.php"
if [[ ! -f "$INSTALL" ]]; then
  echo "Could not find $INSTALL" >&2
  exit 1
fi
cp "$INSTALL" "$INSTALL.bak.$(date +%Y%m%d%H%M%S)"
mkdir -p "$ROOT/src/Foundation/Installation"

cat > "$ROOT/src/Foundation/Installation/DocumentationInstaller.php" <<'PHP'
<?php

namespace Bitsnio\AsasFlow\Foundation\Installation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DocumentationInstaller
{
    public function install(Command $command): bool
    {
        $version = (string) config('asasflow.docs.default_version', '1.0');
        $base = base_path(config('larecipe.docs.path', 'resources/docs'));
        $hostPath = $base . DIRECTORY_SEPARATOR . $version;
        $source = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $version;

        if (! File::isDirectory($source)) {
            $command->error("Package docs source not found at: {$source}");
            return false;
        }

        $installed = (bool) config('asasflow.installed', false);
        $useAsDefault = $installed
            ? (! (bool) config('asasflow.enabled', true) && (bool) config('asasflow.redirect_root', true))
            : $this->isCleanDirectory($hostPath);

        if ($useAsDefault) {
            $destination = $hostPath;
            $command->info('Using AsasFlow documentation as the default /docs content...');
        } else {
            $destination = $base . DIRECTORY_SEPARATOR . 'asasflow' . DIRECTORY_SEPARATOR . $version;
            $command->info('Existing host documentation detected; using a separate AsasFlow folder...');
            $this->addSidebarLink($hostPath, $version, $command);
        }

        $this->copyMissingFiles($source, $destination);
        $command->info("Documentation destination: {$destination}");
        $this->updateConfigFlags($useAsDefault, $command);
        return true;
    }

    private function isCleanDirectory(string $path): bool
    {
        if (! File::isDirectory($path)) return true;
        $files = array_values(array_diff(scandir($path) ?: [], ['.', '..']));
        return $files === [] || collect($files)->every(
            fn (string $file): bool => in_array($file, ['index.md', 'overview.md'], true)
        );
    }

    private function copyMissingFiles(string $source, string $destination): void
    {
        File::ensureDirectoryExists($destination);
        foreach (File::allFiles($source) as $file) {
            $relative = ltrim(substr($file->getPathname(), strlen($source)), DIRECTORY_SEPARATOR);
            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            if (File::exists($target)) continue;
            File::ensureDirectoryExists(dirname($target));
            File::copy($file->getPathname(), $target);
        }
    }

    private function addSidebarLink(string $hostPath, string $version, Command $command): void
    {
        $index = $hostPath . DIRECTORY_SEPARATOR . 'index.md';
        if (! File::exists($index)) {
            $command->warn("Could not find {$index}; add the AsasFlow sidebar link manually.");
            return;
        }
        $page = (string) config('asasflow.docs.default_page', 'overview');
        $link = "/docs/asasflow/{$version}/{$page}";
        $content = File::get($index);
        if (str_contains($content, $link)) {
            $command->line('AsasFlow sidebar link already exists; skipping.');
            return;
        }
        File::put($index, rtrim($content) . "\n\n- ## AsasFlow Docs\n    - [AsasFlow Overview]({$link})\n");
        $command->info('Injected AsasFlow link into host sidebar.');
    }

    private function updateConfigFlags(bool $useAsDefault, Command $command): void
    {
        $path = config_path('asasflow.php');
        if (! File::exists($path)) {
            $command->warn('config/asasflow.php was not found; package configuration flags were not updated.');
            return;
        }
        $content = File::get($path);
        $values = ['installed' => 'true', 'enabled' => $useAsDefault ? 'false' : 'true', 'redirect_root' => $useAsDefault ? 'true' : 'false'];
        foreach ($values as $key => $value) {
            $pattern = '/([\'\"]' . preg_quote($key, '/') . '[\'\"]\s*=>\s*)(true|false)\b/';
            $count = 0;
            $updated = preg_replace($pattern, '$1' . $value, $content, 1, $count);
            if ($count === 0) $command->warn("Could not locate the [{$key}] boolean in config/asasflow.php.");
            else $content = $updated ?? $content;
        }
        File::put($path, $content);
    }
}
PHP

cat > "$ROOT/src/Foundation/Installation/ComposerAutoloadInstaller.php" <<'PHP'
<?php

namespace Bitsnio\AsasFlow\Foundation\Installation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

class ComposerAutoloadInstaller
{
    public function install(Command $command): bool
    {
        $path = base_path('composer.json');
        if (! File::exists($path)) { $command->error('Host composer.json was not found.'); return false; }
        try { $composer = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException) { $command->error('Host composer.json contains invalid JSON.'); return false; }
        if (! is_array($composer)) { $command->error('Host composer.json must contain a JSON object.'); return false; }

        $modified = false;
        $composer['autoload'] ??= [];
        $composer['autoload']['psr-4'] ??= [];
        if (array_key_exists('Modules\\', $composer['autoload']['psr-4'])) {
            unset($composer['autoload']['psr-4']['Modules\\']);
            $modified = true;
            $command->info('Removed conflicting Modules\\ PSR-4 catch-all.');
        }

        $composer['extra'] ??= [];
        $composer['extra']['merge-plugin'] ??= [];
        $includes = $composer['extra']['merge-plugin']['include'] ?? [];
        if (! is_array($includes)) { $command->error('composer.json extra.merge-plugin.include must be an array.'); return false; }
        $pattern = 'Modules/*/composer.json';
        if (! in_array($pattern, $includes, true)) { $includes[] = $pattern; $composer['extra']['merge-plugin']['include'] = $includes; $modified = true; }
        if (! $modified) { $command->info('Composer autoload entries already present; skipping.'); return true; }

        try {
            File::put($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
        } catch (JsonException $e) { $command->error('Could not encode updated composer.json: ' . $e->getMessage()); return false; }
        $command->warn('composer.json was updated. Run: composer dump-autoload');
        return true;
    }
}
PHP

cat > "$ROOT/src/Foundation/Installation/RequiredModulesInstaller.php" <<'PHP'
<?php

namespace Bitsnio\AsasFlow\Foundation\Installation;

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
PHP

cat > "$INSTALL" <<'PHP'
<?php

namespace Bitsnio\AsasFlow\Console\Commands;

use Bitsnio\AsasFlow\Foundation\Installation\ComposerAutoloadInstaller;
use Bitsnio\AsasFlow\Foundation\Installation\DocumentationInstaller;
use Bitsnio\AsasFlow\Foundation\Installation\RequiredModulesInstaller;
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
        if (! app(RequiredModulesInstaller::class)->install($this, app(ModuleReleaseInstaller::class))) {
            $this->error('One or more required modules could not be installed.');
            return self::FAILURE;
        }
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
PHP

printf '\nRefactor files created. Backup of the previous Install.php was saved beside it.\n'
printf 'Run these checks from the package root:\n'
printf '  php -l src/Console/Commands/Install.php\n'
printf '  php -l src/Foundation/Installation/DocumentationInstaller.php\n'
printf '  php -l src/Foundation/Installation/ComposerAutoloadInstaller.php\n'
printf '  php -l src/Foundation/Installation/RequiredModulesInstaller.php\n'
printf '  composer dump-autoload\n'
printf '  php artisan asasflow:install --dry-run\n'
