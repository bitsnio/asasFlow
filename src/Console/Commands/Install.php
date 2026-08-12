<?php

namespace Bitsnio\AsasFlow\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class Install extends Command
{
    protected $signature = 'asasflow:install';
    protected $description = 'Install and configure the AsasFlow package environment';

    public function handle()
    {
        $this->info('Starting AsasFlow installation...');

        // 1. Ensure LaRecipe core engine is installed first so paths exist
        if (!file_exists(\config_path('larecipe.php'))) {
            $this->info('Setting up LaRecipe documentation engine...');
            $this->call('larecipe:install');
        }

        // 2. Publish only the config file — docs are handled manually
        //    below based on clean/non-clean detection, not via vendor:publish
        $this->call('vendor:publish', [
            '--tag'   => 'asasflow-config',
            '--force' => true,
        ]);

        // 3. Resolve paths
        $version         = config('asasflow.docs.default_version', '1.0');
        $larecipeBase    = base_path(config('larecipe.docs.path', 'resources/docs'));
        $hostVersionPath = $larecipeBase . '/' . $version;

        // Source: package's own bundled markdown files for this version
        $ourDocsSource = __DIR__ . '/../../../docs/' . $version;

        if (!is_dir($ourDocsSource)) {
            $this->error("Package docs source not found at: {$ourDocsSource}");
            return self::FAILURE;
        }

        // 4. Detect whether the host already has real documentation
        $shouldAdoptAsDefault = false;

        if (!is_dir($hostVersionPath)) {
            // No docs folder at all — definitely clean
            $shouldAdoptAsDefault = true;
        } else {
            $files = array_diff(scandir($hostVersionPath), ['.', '..']);
            $scaffoldOnly = collect($files)->every(
                fn($f) => in_array($f, ['index.md', 'overview.md'])
            );
            $shouldAdoptAsDefault = count($files) === 0 || $scaffoldOnly;
        }

        if ($shouldAdoptAsDefault) {
            // 5a. CLEAN INSTALL — copy our docs directly into LaRecipe's
            //     default path so larecipe.show serves them natively at /docs
            $this->info('Clean environment detected. Adopting AsasFlow docs as default /docs content...');

            File::ensureDirectoryExists($hostVersionPath);
            File::copyDirectory($ourDocsSource, $hostVersionPath);

            $this->info("Copied docs into: {$hostVersionPath}");
        } else {
            // 5b. NON-CLEAN INSTALL — host has real docs, copy ours into
            //     a separate namespaced subfolder and inject a sidebar link
            $packageDocsPath = $larecipeBase . '/asasflow/' . $version;

            $this->info('Existing docs detected. Copying AsasFlow docs into separate folder...');

            File::ensureDirectoryExists($packageDocsPath);
            File::copyDirectory($ourDocsSource, $packageDocsPath);

            $this->info("Copied docs into: {$packageDocsPath}");

            // Inject sidebar link into the host's index.md
            $hostIndexFile = $hostVersionPath . '/index.md';

            if (file_exists($hostIndexFile)) {
                $sidebarContent = file_get_contents($hostIndexFile);

                if (!str_contains($sidebarContent, 'AsasFlow Docs')) {
                    $page      = config('asasflow.docs.default_page', 'overview');
                    $injection = "\n\n- ## AsasFlow Docs\n    - [AsasFlow Overview](/docs/asasflow/{$version}/{$page})\n";
                    file_put_contents($hostIndexFile, $sidebarContent . $injection);
                    $this->info('Injected AsasFlow link into host sidebar.');
                }
            } else {
                $this->warn("Could not find {$hostIndexFile} — add the sidebar link manually.");
            }
        }

        // 6. Write config flags
        // 6. Write config flags — dynamic regex arrays handle re-runs cleanly
        $configPath = \config_path('asasflow.php');

        if (file_exists($configPath)) {
            $content = file_get_contents($configPath);

            // Define search patterns and their respective dynamic replacements
            $replacements = [
                "/'installed'\s*=>\s*(true|false)/"     => "'installed' => true",
                "/'enabled'\s*=>\s*(true|false)/"       => $shouldAdoptAsDefault ? "'enabled' => false" : "'enabled' => true",
                "/'redirect_root'\s*=>\s*(true|false)/" => $shouldAdoptAsDefault ? "'redirect_root' => true" : "'redirect_root' => false",
            ];

            $content = preg_replace(array_keys($replacements), array_values($replacements), $content);

            file_put_contents($configPath, $content);
        }

        $this->registerAutoloading();

        // 7. Clear config cache so nothing is served stale
        $this->call('config:clear');

        $this->info('AsasFlow installation complete!');

        return self::SUCCESS;
    }

    protected function registerAutoloading(): void
    {
        $composerPath = base_path('composer.json');
        $composer = json_decode(file_get_contents($composerPath), true);
        $modified = false;

        // Remove global Modules\ catch-all if present — it conflicts
        // with per-module app/ folder mapping
        if (isset($composer['autoload']['psr-4']['Modules\\'])) {
            unset($composer['autoload']['psr-4']['Modules\\']);
            $modified = true;
            $this->info('Removed conflicting Modules\\ PSR-4 catch-all.');
        }

        // Configure wikimedia/composer-merge-plugin to auto-merge
        // each module's composer.json into the host autoloader
        $mergePlugin = $composer['extra']['merge-plugin'] ?? [];

        if (
            !isset($mergePlugin['include']) ||
            !in_array('Modules/*/composer.json', $mergePlugin['include'])
        ) {

            $composer['extra']['merge-plugin'] = array_merge($mergePlugin, [
                'include' => ['Modules/*/composer.json']
            ]);
            $modified = true;
            $this->info('Configured composer-merge-plugin for Modules/*/composer.json.');
        }


        if ($modified) {
            file_put_contents(
                $composerPath,
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
            );

            $this->newLine();
            $this->warn('composer.json was updated. Run the following to complete installation:');
            $this->line('   <fg=yellow>composer dump-autoload</>');
            $this->newLine();
        } else {
            $this->info('Autoload entries already present, skipping.');
        }
    }
}
