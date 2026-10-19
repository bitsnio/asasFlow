<?php

namespace Bitsnio\AsasFlow\Console\Commands\Installation;

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
