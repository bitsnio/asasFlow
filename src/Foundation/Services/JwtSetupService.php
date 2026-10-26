<?php

namespace Bitsnio\AsasFlow\Foundation\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class JwtSetupService
{
    private const JWT_PROVIDER =
    'PHPOpenSourceSaver\\JWTAuth\\Providers\\LaravelServiceProvider';

    public function setup(): array
    {
        $result = [
            'dependency' => false,
            'jwt_config' => false,
            'jwt_secret' => false,
            'auth_config' => false,
            'messages' => [],
            'errors' => [],
        ];

        if (
            ! class_exists(\PHPOpenSourceSaver\JWTAuth\JWT::class)
            || ! class_exists(self::JWT_PROVIDER)
        ) {
            $result['errors'][] =
                'JWT package is unavailable. Run: composer require '
                . 'php-open-source-saver/jwt-auth';

            return $result;
        }

        $result['dependency'] = true;

        if (! $this->ensureJwtConfig($result)) {
            return $result;
        }

        $result['jwt_config'] = true;

        if (! $this->ensureJwtSecret($result)) {
            return $result;
        }

        $result['jwt_secret'] = true;

        $this->ensureAuthConfig($result);

        return $result;
    }

    private function ensureJwtConfig(array &$result): bool
    {
        $path = config_path('jwt.php');

        if (File::exists($path)) {
            $result['messages'][] =
                'config/jwt.php already exists; preserved.';

            return true;
        }

        $exitCode = Artisan::call('vendor:publish', [
            '--provider' => self::JWT_PROVIDER,
        ]);

        if ($exitCode !== 0 || ! File::exists($path)) {
            $result['errors'][] =
                'Could not publish config/jwt.php. Publish it manually '
                . 'using the JWT service provider.';

            return false;
        }

        $result['messages'][] = 'Published config/jwt.php.';

        return true;
    }

    private function ensureJwtSecret(array &$result): bool
    {
        if ($this->hasJwtSecret()) {
            $result['messages'][] =
                'JWT_SECRET already exists; preserved.';

            return true;
        }

        if (! array_key_exists('jwt:secret', Artisan::all())) {
            $result['errors'][] =
                'The jwt:secret command is unavailable. Check the JWT '
                . 'package installation and run php artisan jwt:secret manually.';

            return false;
        }

        $exitCode = Artisan::call('jwt:secret');

        if ($exitCode !== 0 || ! $this->hasJwtSecret()) {
            $result['errors'][] =
                'JWT secret generation could not be verified. Check the '
                . 'console output and configure JWT_SECRET manually if needed.';

            return false;
        }

        $result['messages'][] =
            'Generated JWT_SECRET because no existing secret was found.';

        return true;
    }

    private function hasJwtSecret(): bool
    {
        foreach (
            [
                getenv('JWT_SECRET'),
                $_ENV['JWT_SECRET'] ?? null,
                $_SERVER['JWT_SECRET'] ?? null,
            ] as $secret
        ) {
            if (is_string($secret) && trim($secret) !== '') {
                return true;
            }
        }

        $path = base_path('.env');

        if (! File::exists($path)) {
            return false;
        }

        $content = File::get($path);

        if (! preg_match(
            '/^\s*JWT_SECRET\s*=\s*(.*?)\s*$/m',
            $content,
            $matches
        )) {
            return false;
        }

        return trim(trim($matches[1]), "\"'") !== '';
    }

    private function ensureAuthConfig(array &$result): void
    {
        $path = config_path('auth.php');

        if (! File::exists($path)) {
            $result['errors'][] =
                'config/auth.php was not found. JWT auth settings were not added.';

            return;
        }

        $model = config(
            'asasflow.install.jwt.user_model',
            'Modules\\Core\\Models\\Users'
        );

        $provider = config('asasflow.install.jwt.provider', 'users');
        $guard = config('asasflow.install.jwt.guard', 'api');

        $content = File::get($path);
        $original = $content;

        $providerEntry = $this->readEntry(
            $content,
            'providers',
            $provider
        );

        if ($providerEntry === null) {
            $entry = "'{$provider}' => [\n"
                . "            'driver' => 'eloquent',\n"
                . "            'model' => " . var_export($model, true) . ",\n"
                . '        ],';

            $updated = $this->insertEntry($content, 'providers', $entry);

            if ($updated === null) {
                $result['errors'][] =
                    "Could not safely add the [{$provider}] provider. "
                    . 'Review config/auth.php manually.';

                return;
            }

            $content = $updated;
        } elseif (
            ! $this->entryHasValue($providerEntry, 'driver', 'eloquent')
            || ! $this->entryHasModel($providerEntry, $model)
        ) {
            $result['errors'][] =
                "The existing [{$provider}] provider has different settings; "
                . 'it was preserved. Review config/auth.php manually.';

            return;
        }

        $guardEntry = $this->readEntry($content, 'guards', $guard);

        if ($guardEntry === null) {
            $entry = "'{$guard}' => [\n"
                . "            'driver' => 'jwt',\n"
                . "            'provider' => '{$provider}',\n"
                . '        ],';

            $updated = $this->insertEntry($content, 'guards', $entry);

            if ($updated === null) {
                $result['errors'][] =
                    'Could not safely add the API JWT guard. '
                    . 'Review config/auth.php manually.';

                return;
            }

            $content = $updated;
        } elseif (
            ! $this->entryHasValue($guardEntry, 'driver', 'jwt')
            || ! $this->entryHasValue($guardEntry, 'provider', $provider)
        ) {
            $result['errors'][] =
                'The existing API guard has different settings; it was '
                . 'preserved. Review config/auth.php manually.';

            return;
        }

        if ($content !== $original) {
            File::put($path, $content);

            $result['messages'][] =
                'Added missing JWT provider/guard to config/auth.php.';
        } else {
            $result['messages'][] =
                'JWT provider and API guard already configured.';
        }

        $result['auth_config'] = true;
    }

    private function readEntry(
        string $content,
        string $section,
        string $entry
    ): ?string {
        $sectionOffset = $this->findSectionStart($content, $section);

        if ($sectionOffset === null) {
            return null;
        }

        $sectionOpen = strpos($content, '[', $sectionOffset);

        if ($sectionOpen === false) {
            return null;
        }

        $sectionClose = $this->matchingBracket($content, $sectionOpen);

        if ($sectionClose === null) {
            return null;
        }

        $sectionContent = substr(
            $content,
            $sectionOpen + 1,
            $sectionClose - $sectionOpen - 1
        );

        $pattern = '/[\'"]' . preg_quote($entry, '/') . '[\'"]\s*=>\s*\[/';

        if (! preg_match(
            $pattern,
            $sectionContent,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return null;
        }

        $entryStart = $sectionOpen + 1 + $matches[0][1];
        $entryOpen = strpos($content, '[', $entryStart);

        if ($entryOpen === false) {
            return null;
        }

        $entryClose = $this->matchingBracket($content, $entryOpen);

        if ($entryClose === null || $entryClose > $sectionClose) {
            return null;
        }

        return substr(
            $content,
            $entryStart,
            $entryClose - $entryStart + 1
        );
    }

    private function insertEntry(
        string $content,
        string $section,
        string $entry
    ): ?string {
        $sectionOffset = $this->findSectionStart($content, $section);

        if ($sectionOffset === null) {
            return null;
        }

        $open = strpos($content, '[', $sectionOffset);

        if ($open === false) {
            return null;
        }

        $close = $this->matchingBracket($content, $open);

        if ($close === null) {
            return null;
        }

        return substr($content, 0, $close)
            . "\n        {$entry}\n    "
            . substr($content, $close);
    }

    private function findSectionStart(
        string $content,
        string $section
    ): ?int {
        if (! preg_match(
            '/[\'"]' . preg_quote($section, '/') . '[\'"]\s*=>\s*\[/',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return null;
        }

        return $matches[0][1];
    }

    private function matchingBracket(
        string $content,
        int $open
    ): ?int {
        $depth = 0;
        $length = strlen($content);
        $quote = null;
        $escaped = false;

        for ($i = $open; $i < $length; $i++) {
            $char = $content[$i];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                continue;
            }

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function entryHasValue(
        string $entry,
        string $key,
        string $value
    ): bool {
        return preg_match(
            '/[\'"]' . preg_quote($key, '/') . '[\'"]\s*=>\s*[\'"]'
                . preg_quote($value, '/') . '[\'"]/',
            $entry
        ) === 1;
    }

    private function entryHasModel(
        string $entry,
        string $model
    ): bool {
        return preg_match(
            '/[\'"]model[\'"]\s*=>\s*[\'"]'
                . preg_quote($model, '/') . '[\'"]/',
            $entry
        ) === 1;
    }
}
