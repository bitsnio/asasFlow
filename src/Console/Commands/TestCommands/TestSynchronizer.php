<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\TestCommands;

use Illuminate\Filesystem\Filesystem;

class TestSynchronizer
{
    public function __construct(
        protected Filesystem $filesystem,
        protected TestManifest $manifest
    ) {}

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

                if (! $this->filesystem->isFile($file['source'])) {
                    continue;
                }

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

                        if (! $this->filesystem->exists($destination)) {
                            $result['errors'][] = $logicalPath;
                            continue;
                        }
                    }

                    $result['added'][] = $logicalPath;

                    continue;
                }

                $hostHash = $this->manifest->hash(
                    $destination
                );

                if ($currentHash === '' || $hostHash === '') {
                    $result['errors'][] = $logicalPath;
                    continue;
                }

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
