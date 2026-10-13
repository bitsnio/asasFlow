<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Composer\Semver\Semver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

final class ModuleReleaseInstaller
{
    /**
     * Install a configured module.
     *
     * Development fallback is only attempted when explicitly allowed
     * and the release is unavailable, not when validation fails.
     */
    public function install(
        string $alias,
        array $definition,
        bool $allowDevFallback = false,
        bool $dryRun = false,
    ): array {
        $alias = strtolower(trim($alias));

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $alias)) {
            throw new RuntimeException("Invalid module alias [{$alias}].");
        }

        $release = $definition['release'] ?? null;
        $releaseUrl = is_array($release)
            ? ($release['url'] ?? null)
            : null;

        $devUrl = $definition['dev_url'] ?? null;

        if (! $this->validUrl($releaseUrl) && ! $this->validUrl($devUrl)) {
            throw new RuntimeException(
                "Module [{$alias}] must define release.url or dev_url."
            );
        }

        if ($this->validUrl($releaseUrl)) {
            try {
                return $this->downloadAndInstall(
                    alias: $alias,
                    url: $releaseUrl,
                    source: 'release',
                    versionConstraint: $release['version'] ?? null,
                    dryRun: $dryRun,
                );
            } catch (ReleaseUnavailableException $e) {
                if (! $allowDevFallback || ! $this->validUrl($devUrl)) {
                    throw $e;
                }

                // A failed checksum, invalid ZIP, invalid manifest or
                // incompatible version must never trigger fallback.
            }
        }

        if (!$this->validUrl($devUrl)) {
            throw new RuntimeException(
                "No usable release or development URL is configured for [{$alias}]."
            );
        }

        if ($this->validUrl($releaseUrl) && !$allowDevFallback) {
            throw new RuntimeException(
                "The release for [{$alias}] is unavailable. "
                . 'Use --allow-dev-fallback to permit development installation.'
            );
        }

        return $this->downloadAndInstall(
            alias: $alias,
            url: $devUrl,
            source: 'development',
            versionConstraint: null,
            dryRun: $dryRun,
        );
    }

    private function downloadAndInstall(
        string $alias,
        string $url,
        string $source,
        ?string $versionConstraint,
        bool $dryRun,
    ): array {
        $this->assertHttpsUrl($url);

        $workDir = storage_path(
            'framework/asasflow-module-install/' . bin2hex(random_bytes(12))
        );

        File::ensureDirectoryExists($workDir);

        try {
            $archivePath = $workDir . DIRECTORY_SEPARATOR . 'module.zip';

            try {
                $response = Http::timeout(120)
                    ->withOptions(['sink' => $archivePath])
                    ->get($url);
            } catch (ConnectionException $e) {
                throw new ReleaseUnavailableException(
                    "Could not connect to the {$source} URL for [{$alias}].",
                    previous: $e,
                );
            }

            if (in_array($response->status(), [404, 410], true)) {
                throw new ReleaseUnavailableException(
                    "The {$source} archive for [{$alias}] is unavailable "
                    . "(HTTP {$response->status()})."
                );
            }

            if (!$response->successful()) {
                throw new RuntimeException(
                    "The {$source} download for [{$alias}] failed "
                    . "(HTTP {$response->status()})."
                );
            }

            if (
                !File::exists($archivePath)
                || File::size($archivePath) === 0
            ) {
                throw new RuntimeException(
                    "The downloaded archive for [{$alias}] is empty."
                );
            }

            $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extracted';
            File::ensureDirectoryExists($extractDir);

            $moduleRoot = $this->extractModule($archivePath, $extractDir);
            $manifest = $this->readManifest($moduleRoot);

            $manifestAlias = strtolower((string) ($manifest['alias'] ?? ''));

            if ($manifestAlias !== $alias) {
                throw new RuntimeException(
                    "Module alias mismatch: expected [{$alias}], "
                    . "received [{$manifestAlias}]."
                );
            }

            $name = $manifest['name'] ?? null;

            if (
                !is_string($name)
                || $name === ''
                || basename($name) !== $name
                || in_array($name, ['.', '..'], true)
            ) {
                throw new RuntimeException(
                    "The module [{$alias}] has an invalid name in module.json."
                );
            }

            $version = $manifest['version'] ?? null;

            if ($source === 'release') {
                if (
                    !is_string($version)
                    || $version === ''
                    || !is_string($versionConstraint)
                    || $versionConstraint === ''
                ) {
                    throw new RuntimeException(
                        "Release module [{$alias}] must define a version "
                        . 'and a release.version constraint.'
                    );
                }

                if (!Semver::satisfies(ltrim($version, 'v'), $versionConstraint)) {
                    throw new RuntimeException(
                        "Module [{$alias}] version [{$version}] does not "
                        . "satisfy [{$versionConstraint}]."
                    );
                }
            }

            $modulesPath = rtrim(
                config('asasflow.modules.path', base_path('Modules')),
                DIRECTORY_SEPARATOR,
            );

            $destination = $modulesPath . DIRECTORY_SEPARATOR . $name;

            if (File::exists($destination)) {
                throw new RuntimeException(
                    "Module [{$name}] already exists at [{$destination}]. "
                    . 'Automatic overwrite is disabled.'
                );
            }

            if ($dryRun) {
                return [
                    'alias' => $alias,
                    'name' => $name,
                    'version' => $version,
                    'source' => $source,
                    'path' => $destination,
                    'installed' => false,
                    'dry_run' => true,
                ];
            }

            File::ensureDirectoryExists($modulesPath);

            // Stage under Modules/ so promotion stays on the same filesystem.
            $stagingPath = $modulesPath
                . DIRECTORY_SEPARATOR
                . '.asasflow-install-' . bin2hex(random_bytes(8));

            if (!File::copyDirectory($moduleRoot, $stagingPath)) {
                throw new RuntimeException(
                    "Could not stage module [{$alias}] for installation."
                );
            }

            try {
                // Check again immediately before promotion.
                if (File::exists($destination)) {
                    throw new RuntimeException(
                        "Module destination [{$destination}] already exists."
                    );
                }

                if (!@rename($stagingPath, $destination)) {
                    throw new RuntimeException(
                        "Could not promote module into [{$destination}]."
                    );
                }
            } catch (\Throwable $e) {
                File::deleteDirectory($stagingPath);
                throw $e;
            }

            return [
                'alias' => $alias,
                'name' => $name,
                'version' => $version,
                'source' => $source,
                'path' => $destination,
                'installed' => true,
                'dry_run' => false,
            ];
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function readManifest(string $moduleRoot): array
    {
        $path = $moduleRoot . DIRECTORY_SEPARATOR . 'module.json';

        if (!File::exists($path)) {
            throw new RuntimeException('Module archive has no module.json.');
        }

        try {
            $manifest = json_decode(
                File::get($path),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $e) {
            throw new RuntimeException(
                'Module archive contains invalid module.json.',
                previous: $e,
            );
        }

        if (!is_array($manifest)) {
            throw new RuntimeException('Invalid module.json structure.');
        }

        return $manifest;
    }

    private function extractModule(string $archivePath, string $extractDir): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'PHP ext-zip is required to install module archives.'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Downloaded file is not a valid ZIP archive.');
        }

        try {
            $root = $extractDir . DIRECTORY_SEPARATOR . 'archive';
            File::ensureDirectoryExists($root);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                if (!is_string($entry) || $entry === '') {
                    throw new RuntimeException('Invalid ZIP entry.');
                }

                $normalized = str_replace('\\', '/', $entry);
                $segments = explode('/', $normalized);

                if (
                    str_starts_with($normalized, '/')
                    || preg_match('/^[a-zA-Z]:/', $normalized)
                    || in_array('..', $segments, true)
                ) {
                    throw new RuntimeException(
                        "Unsafe path in module archive: [{$entry}]."
                    );
                }

                // Reject Unix symbolic links where ZIP metadata exposes them.
                $opsys = 0;
                $attributes = 0;

                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    $unixMode = ($attributes >> 16) & 0170000;

                    if (
                        $opsys === ZipArchive::OPSYS_UNIX
                        && $unixMode === 0120000
                    ) {
                        throw new RuntimeException(
                            'Symbolic links are not allowed in module archives.'
                        );
                    }
                }

                $target = $root . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $normalized);

                if (str_ends_with($normalized, '/')) {
                    File::ensureDirectoryExists($target);
                    continue;
                }

                File::ensureDirectoryExists(dirname($target));

                $input = $zip->getStream($entry);

                if ($input === false) {
                    throw new RuntimeException(
                        "Could not read archive entry [{$entry}]."
                    );
                }

                $output = fopen($target, 'wb');

                if ($output === false) {
                    fclose($input);

                    throw new RuntimeException(
                        "Could not write archive entry [{$entry}]."
                    );
                }

                try {
                    if (stream_copy_to_stream($input, $output) === false) {
                        throw new RuntimeException(
                            "Could not extract archive entry [{$entry}]."
                        );
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
            }

            if (File::exists($root . DIRECTORY_SEPARATOR . 'module.json')) {
                return $root;
            }

            $matches = [];

            foreach (File::directories($root) as $directory) {
                if (File::exists($directory . DIRECTORY_SEPARATOR . 'module.json')) {
                    $matches[] = $directory;
                }
            }

            if (count($matches) !== 1) {
                throw new RuntimeException(
                    'Archive must contain module.json at its root or in '
                    . 'exactly one top-level directory.'
                );
            }

            return $matches[0];
        } finally {
            $zip->close();
        }
    }

    private function validUrl(mixed $url): bool
    {
        return is_string($url) && trim($url) !== '';
    }

    private function assertHttpsUrl(string $url): void
    {
        $parts = parse_url($url);

        if (
            !is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
        ) {
            throw new RuntimeException(
                'Module archive URLs must use HTTPS.'
            );
        }
    }
}

/**
 * Only this exception permits development fallback.
 */
final class ReleaseUnavailableException extends RuntimeException
{
}
