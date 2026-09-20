<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands;

use Bitsnio\AsasFlow\Console\Commands\TestCommands\PestVersionResolver;
use Bitsnio\AsasFlow\Console\Commands\TestCommands\TestDiscovery;
use Bitsnio\AsasFlow\Console\Commands\TestCommands\TestEnvironment;
use Bitsnio\AsasFlow\Console\Commands\TestCommands\TestManifest;
use Bitsnio\AsasFlow\Console\Commands\TestCommands\TestSynchronizer;
use Illuminate\Console\Command;
use RuntimeException;

class InstallTests extends Command
{
    protected $signature = 'asasflow:tests
        {--feature= : Only synchronize a specific feature}
        {--path= : Custom source path containing feature directories}
        {--update : Explicitly update changed host tests}
        {--force : Force overwrite existing host tests}
        {--dry-run : Show changes without modifying files}
        {--upgrade-pest : Upgrade Pest to the compatible major version}';

    protected $description =
    'Install and synchronize AsasFlow feature tests';

    public function handle(
        PestVersionResolver $versions,
        TestEnvironment $environment,
        TestDiscovery $discovery,
        TestManifest $manifest,
        TestSynchronizer $synchronizer
    ): int {
        $this->info('AsasFlow test environment');
        $this->line(
            'PHP: ' . $environment->phpVersion()
        );

        $pestMajor = $versions->resolve();

        $this->line(
            "Required Pest: {$pestMajor}"
        );

        /*
         * ---------------------------------------------------------
         * Install / verify Pest
         * ---------------------------------------------------------
         */

        if (
            $this->option('upgrade-pest')
            || ! $this->pestInstalled()
        ) {
            $this->installPest(
                $environment,
                $versions,
                $pestMajor
            );
        } else {
            $this->info('Pest is already installed.');
        }

        /*
         * ---------------------------------------------------------
         * Initialize Pest
         * ---------------------------------------------------------
         */

        if (! $this->pestInitialized()) {
            $this->info('Initializing Pest...');

            if (! $this->option('dry-run')) {
                $process = $environment->composerExecPest([
                    '--init',
                ]);

                if (! $process->isSuccessful()) {
                    $this->error(
                        $process->getErrorOutput()
                            ?: $process->getOutput()
                    );

                    return self::FAILURE;
                }
            }
        }

        /*
         * ---------------------------------------------------------
         * Discover feature tests
         * ---------------------------------------------------------
         */

        try {
            $features = $discovery->features(
                $this->option('path'),
                $this->option('feature')
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($features === []) {
            $this->warn(
                'No AsasFlow feature tests were found.'
            );

            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($features as $feature => $files) {
            $this->line(
                sprintf(
                    '%s: %d test file(s)',
                    $feature,
                    count($files)
                )
            );
            foreach ($files as $f) {
                $this->line('   ' . $f['relative']);   // 👈 debug
            }
        }

        /*
         * ---------------------------------------------------------
         * Synchronize
         * ---------------------------------------------------------
         */

        $result = $synchronizer->synchronize(
            $features,
            (bool) $this->option('update'),
            (bool) $this->option('force'),
            (bool) $this->option('dry-run')
        );

        $this->displayResult(
            $result
        );

        /*
         * ---------------------------------------------------------
         * Final instructions
         * ---------------------------------------------------------
         */

        if (! $this->option('dry-run')) {
            $this->newLine();

            $this->info(
                'AsasFlow tests are ready.'
            );

            $this->line(
                'Run all AsasFlow tests:'
            );

            $this->line(
                '  composer exec pest -- tests/Feature/AsasFlow'
            );

            if ($this->option('feature')) {
                $feature = $this->option('feature');

                $this->line(
                    'Run this feature:'
                );

                $this->line(
                    "  composer exec pest -- tests/Feature/AsasFlow/{$feature}"
                );
            }
        }

        return empty($result['conflicts']) && empty($result['errors'])
            ? self::SUCCESS
            : self::FAILURE;
    }

    protected function pestInstalled(): bool
    {
        $composerPath = base_path('composer.lock');

        if (! file_exists($composerPath)) {
            return false;
        }

        $lock = json_decode(
            file_get_contents($composerPath),
            true
        );

        foreach (
            array_merge(
                $lock['packages'] ?? [],
                $lock['packages-dev'] ?? []
            ) as $package
        ) {
            if (
                ($package['name'] ?? null)
                === 'pestphp/pest'
            ) {
                return true;
            }
        }

        return false;
    }

    protected function pestInitialized(): bool
    {
        return file_exists(
            base_path('tests/Pest.php')
        );
    }

    protected function installPest(
        TestEnvironment $environment,
        PestVersionResolver $versions,
        int $pestMajor
    ): void {
        $this->info(
            "Installing Pest {$pestMajor}..."
        );

        $packages = [
            $versions->package($pestMajor),
            $versions->laravelPlugin($pestMajor),
        ];

        $arguments = [
            'require',
            '--dev',
            ...$packages,
        ];

        $process = $environment->runComposer(
            $arguments
        );

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                $process->getErrorOutput()
                    ?: $process->getOutput()
            );
        }

        $this->info(
            'Pest installation completed.'
        );
    }

    protected function displayResult(
        array $result
    ): void {
        $this->newLine();

        foreach (
            [
                'added' => 'Added',
                'updated' => 'Updated',
                'skipped' => 'Skipped',
                'conflicts' => 'Conflicts',
                'errors' => 'Errors',
            ] as $key => $label
        ) {
            if (empty($result[$key])) {
                continue;
            }

            $this->line(
                "{$label}: " . count($result[$key])
            );

            foreach ($result[$key] as $file) {
                $this->line(
                    "  - {$file}"
                );
            }
        }

        if (! empty($result['conflicts'])) {
            $this->newLine();

            $this->warn(
                'Some host tests were modified locally and were not overwritten.'
            );

            $this->line(
                'Use --update to explicitly replace them.'
            );

            $this->line(
                'Use --force to force all overwrites.'
            );
        }
    }
}
