<?php

namespace Bitsnio\AsasFlow\Console\Commands\Installation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DocumentationInstaller
{
    public function install(Command $command): bool
    {
        $version = (string) config('asasflow.docs.default_version', '1.0');
        $base = base_path(config('larecipe.docs.path', 'resources/docs'));
        $hostPath = $base . DIRECTORY_SEPARATOR . $version;
        $source = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $version;

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
