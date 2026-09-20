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
        
        return $this->fallbackPhpUnitVersion(); 

        throw new InvalidArgumentException(
            'PHPUnit is not installed in composer.lock.'
        );
    }

    protected function fallbackPhpUnitVersion(): string
    {
        // PHP 8.4+ -> assume PHPUnit 13 / Pest 5
        // PHP 8.3  -> assume PHPUnit 12 / Pest 4
        // below 8.3 -> let resolve() throw a clear error
        return match (true) {
            PHP_VERSION_ID >= 80400 => '13.0.0',
            PHP_VERSION_ID >= 80300 => '12.0.0',
            default                 => '0.0.0',
        };
    }

    protected function majorVersion(string $version): int
    {
        return (int) ltrim(
            explode('.', $version)[0],
            'v'
        );
    }
}
