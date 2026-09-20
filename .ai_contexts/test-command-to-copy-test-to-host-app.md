# Laravel Project Context

**Task:** Test Command to copy test to host app

## Background and Purpose

building a package in laravel which provides modular approach of code generated through laravel-module package. this package generate basic strucure along with menu.php file which contain full information based on what routes and controller are generated. i have designe the package in a way that each function is created as feature.
---

## Directory Structure

```
.
config
database
database/migrations
routes
scripts
src
src/Console
src/Console/Commands
src/Console/Commands/ControllerCommands
src/Console/Commands/ControllerCommands/Contracts
src/Console/Commands/ControllerCommands/Services
src/Console/Commands/ControllerCommands/Services/Parsers
src/Console/Commands/ModuleCommands
src/Console/Commands/Stubs
src/Console/Commands/TestCommands
src/Console/Commands/Traits
src/Features
src/Features/Cache
src/Features/Cache/Attributes
src/Features/Cache/Console
src/Features/Cache/Console/Commands
src/Features/Cache/Console/Stubs
src/Features/Cache/Contracts
src/Features/Cache/Events
src/Features/Cache/Facades
src/Features/Cache/Http
src/Features/Cache/Http/Controllers
src/Features/Cache/Http/Middleware
src/Features/Cache/Jobs
src/Features/Cache/Observers
src/Features/Cache/Services
src/Features/Cache/Traits
src/Features/Cache/routes
src/Features/ControllerGeneration
src/Features/ControllerGeneration/Commands
src/Features/ControllerGeneration/Contracts
src/Features/ControllerGeneration/Generators
src/Features/ControllerGeneration/Generators/Controller
src/Features/ControllerGeneration/Generators/Route
src/Features/ControllerGeneration/Generators/Stub
src/Features/ControllerGeneration/Parsers
src/Features/ControllerGeneration/Parsers/Extractors
src/Features/ControllerGeneration/Parsers/Validators
src/Features/ControllerGeneration/Services
src/Features/ControllerGeneration/Stubs
src/Features/ControllerGeneration/Traits
src/Features/ControllerGeneration/config
src/Features/ControllerGeneration/routes
src/Features/Settings
src/Features/Settings/Facades
src/Features/Settings/Http
src/Features/Settings/Http/Controllers
src/Features/Settings/Http/Requests
src/Features/Settings/Models
src/Features/Settings/Repositories
src/Features/Settings/Services
src/Features/Settings/Tests
src/Features/Settings/routes
src/Features/Tenancy
src/Features/Tenancy/Contracts
src/Features/Tenancy/Http
src/Features/Tenancy/Http/Middleware
src/Features/Tenancy/Models
src/Features/Tenancy/Services
src/Foundation
src/Foundation/Contracts
src/Foundation/Exceptions
src/Foundation/Support
src/Generators
src/Generators/Cache
src/Generators/Controller
src/routes
tests
tests/Feature
tests/Unit
```

---

## Project Files

### src/Console/Commands/TestCommands/PestVersionResolver.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\TestCommands;

use InvalidArgumentException;

class PestVersionResolver
{
    public function resolve(
        ?string $phpVersion = null,
        ?string $phpunitVersion = null
    ): int {
        $phpVersion ??= PHP_VERSION;
        $phpunitVersion ??= $this->installedPhpUnitVersion();

        $phpMajor = PHP_MAJOR_VERSION;
        $phpMinor = PHP_MINOR_VERSION;

        if ($phpMajor !== 8) {
            throw new InvalidArgumentException(
                "Unsupported PHP version [{$phpVersion}]."
            );
        }

        /*
         * PHPUnit 12 -> Pest 4
         */
        if ($this->majorVersion($phpunitVersion) === 12) {
            if ($phpMinor < 3) {
                throw new InvalidArgumentException(
                    "PHPUnit 12 requires PHP 8.3+ for this application."
                );
            }

            return 4;
        }

        /*
         * PHPUnit 13 -> Pest 5
         */
        if ($this->majorVersion($phpunitVersion) === 13) {
            if ($phpMinor < 4) {
                throw new InvalidArgumentException(
                    "PHPUnit 13 / Pest 5 requires PHP 8.4+."
                );
            }

            return 5;
        }

        throw new InvalidArgumentException(
            "Unsupported PHPUnit version [{$phpunitVersion}]. "
            . 'AsasFlow supports Pest 4 with PHPUnit 12 '
            . 'and Pest 5 with PHPUnit 13.'
        );
    }

    public function package(int $pestMajor): string
    {
        return match ($pestMajor) {
            4 => 'pestphp/pest:^4.0',
            5 => 'pestphp/pest:^5.0',
            default => throw new InvalidArgumentException(
                "Unsupported Pest major version [{$pestMajor}]."
            ),
        };
    }

    public function laravelPlugin(int $pestMajor): string
    {
        return match ($pestMajor) {
            4 => 'pestphp/pest-plugin-laravel:^4.0',
            5 => 'pestphp/pest-plugin-laravel:^5.0',
            default => throw new InvalidArgumentException(
                "Unsupported Pest major version [{$pestMajor}]."
            ),
        };
    }

    protected function installedPhpUnitVersion(): string
    {
        $composerLock = base_path('composer.lock');

        if (! file_exists($composerLock)) {
            throw new InvalidArgumentException(
                'composer.lock was not found. '
                . 'Unable to determine the installed PHPUnit version.'
            );
        }

        $lock = json_decode(
            file_get_contents($composerLock),
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
                === 'phpunit/phpunit'
            ) {
                return $package['version'];
            }
        }

        throw new InvalidArgumentException(
            'PHPUnit is not installed in composer.lock.'
        );
    }

    protected function majorVersion(string $version): int
    {
        return (int) ltrim(
            explode('.', $version)[0],
            'v'
        );
    }
}
```

### src/Console/Commands/TestCommands/TestDiscovery.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\TestCommands;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class TestDiscovery
{
    public function __construct(
        protected Filesystem $filesystem
    ) {}

    public function features(
        ?string $path = null,
        ?string $feature = null
    ): array {
        $basePath = $path
            ? $this->absolutePath($path)
            : $this->packageRoot() . '/src/Features';

        if (! $this->filesystem->isDirectory($basePath)) {
            throw new RuntimeException(
                "AsasFlow feature path does not exist: {$basePath}"
            );
        }

        $features = [];

        foreach ($this->filesystem->directories($basePath) as $directory) {
            $featureName = basename($directory);

            if (
                $feature !== null
                && strtolower($featureName) !== strtolower($feature)
            ) {
                continue;
            }

            $testsPath = $directory . '/Tests';

            if (! $this->filesystem->isDirectory($testsPath)) {
                continue;
            }

            foreach ($this->filesystem->allFiles($testsPath) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = $this->relativePath(
                    $testsPath,
                    $file->getPathname()
                );

                $features[$featureName][] = [
                    'source' => $file->getPathname(),
                    'relative' => $this->normalize($relative),
                ];
            }
        }

        ksort($features);

        return $features;
    }

    protected function relativePath(string $path, string $basePath): string
    {
        return ltrim(
            str_replace(
                rtrim($basePath, DIRECTORY_SEPARATOR),
                '',
                $path
            ),
            DIRECTORY_SEPARATOR
        );
    }

    protected function packageRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    protected function absolutePath(string $path): string
    {
        if (
            str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
        ) {
            return $path;
        }

        return base_path($path);
    }

    protected function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

```

### src/Console/Commands/TestCommands/TestEnvironment.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\TestCommands;

use Symfony\Component\Process\Process;

class TestEnvironment
{
    public function __construct(
        protected PestVersionResolver $versions
    ) {
    }

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function pestMajor(): int
    {
        return $this->versions->resolve();
    }

    public function composerCommand(): string
    {
        return PHP_OS_FAMILY === 'Windows'
            ? 'composer.bat'
            : 'composer';
    }

    public function runComposer(
        array $arguments,
        ?int $timeout = 300
    ): Process {
        $process = new Process([
            $this->composerCommand(),
            ...$arguments,
        ], base_path());

        $process->setTimeout($timeout);

        $process->run();

        return $process;
    }

    public function composerExecPest(
        array $arguments = [],
        ?int $timeout = 300
    ): Process {
        return $this->runComposer(
            [
                'exec',
                'pest',
                '--',
                ...$arguments,
            ],
            $timeout
        );
    }
}
```

### src/Console/Commands/TestCommands/TestManifest.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\TestCommands;

use Illuminate\Filesystem\Filesystem;

class TestManifest
{
    protected string $filename = '.asasflow-tests.json';

    public function __construct(
        protected Filesystem $filesystem
    ) {
    }

    public function path(): string
    {
        return base_path(
            'tests/Feature/AsasFlow/' . $this->filename
        );
    }

    public function load(): array
    {
        $path = $this->path();

        if (! $this->filesystem->exists($path)) {
            return [
                'version' => '1.0.0',
                'tests' => [],
            ];
        }

        $contents = $this->filesystem->get($path);

        $manifest = json_decode(
            $contents,
            true
        );

        if (! is_array($manifest)) {
            return [
                'version' => '1.0.0',
                'tests' => [],
            ];
        }

        $manifest['version'] ??= '1.0.0';
        $manifest['tests'] ??= [];

        return $manifest;
    }

    public function save(array $manifest): void
    {
        $path = $this->path();

        $this->filesystem->ensureDirectoryExists(
            dirname($path)
        );

        $this->filesystem->put(
            $path,
            json_encode(
                $manifest,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            ) . PHP_EOL
        );
    }

    public function hash(string $path): string
    {
        return hash_file('sha256', $path);
    }

    public function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
```

### src/Console/Commands/TestCommands/TestSynchronizer.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\TestCommands;

use Illuminate\Filesystem\Filesystem;

class TestSynchronizer
{
    public function __construct(
        protected Filesystem $filesystem,
        protected TestManifest $manifest
    ) {
    }

    public function synchronize(
        array $features,
        bool $update = false,
        bool $force = false,
        bool $dryRun = false
    ): array {
        $manifest = $this->manifest->load();

        $previousTests = $manifest['tests'] ?? [];

        $newTests = [];

        $result = [
            'added' => [],
            'updated' => [],
            'skipped' => [],
            'conflicts' => [],
            'errors' => [],
        ];

        foreach ($features as $featureName => $files) {
            foreach ($files as $file) {
                $logicalPath = $this->manifest->normalize(
                    $featureName . '/' . $file['relative']
                );

                $destination = base_path(
                    'tests/Feature/AsasFlow/' . $logicalPath
                );

                $source = $file['source'];

                $currentHash = $this->manifest->hash($source);

                $newTests[$logicalPath] = $currentHash;

                $previousHash = $previousTests[$logicalPath] ?? null;

                if (! $this->filesystem->exists($destination)) {
                    if (! $dryRun) {
                        $this->filesystem->ensureDirectoryExists(
                            dirname($destination)
                        );

                        $this->filesystem->copy(
                            $source,
                            $destination
                        );
                    }

                    $result['added'][] = $logicalPath;

                    continue;
                }

                $hostHash = $this->manifest->hash(
                    $destination
                );

                if ($hostHash === $currentHash) {
                    $result['skipped'][] = $logicalPath;

                    continue;
                }

                /*
                 * Host file has not been modified since the previous
                 * package version. Safe to update automatically.
                 */
                if (
                    $previousHash !== null
                    && $hostHash === $previousHash
                ) {
                    if (! $dryRun) {
                        $this->filesystem->copy(
                            $source,
                            $destination
                        );
                    }

                    $result['updated'][] = $logicalPath;

                    continue;
                }

                /*
                 * Explicit --update means:
                 * "I know package tests changed and I want to
                 * update the host copies."
                 */
                if ($update || $force) {
                    if (! $dryRun) {
                        $this->filesystem->copy(
                            $source,
                            $destination
                        );
                    }

                    $result['updated'][] = $logicalPath;

                    continue;
                }

                /*
                 * Host test was customized.
                 */
                $result['conflicts'][] = $logicalPath;
            }
        }

        /*
         * We deliberately do NOT delete tests that disappeared
         * from the package.
         *
         * Developer-owned host tests must never be deleted
         * automatically.
         */
        if (! $dryRun) {
            $manifest['version'] = '1.0.0';
            $manifest['tests'] = $newTests;

            $this->manifest->save($manifest);
        }

        return $result;
    }
}
```

### src/Console/Commands/InstallTests.php

```php
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

        return empty($result['conflicts'])
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
```

