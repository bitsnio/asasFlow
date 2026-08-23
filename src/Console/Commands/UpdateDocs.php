<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateDocs extends Command
{
    protected $signature = 'asasflow:docs
                            {--force : Overwrite existing AsasFlow documentation}';

    protected $description = 'Publish or update AsasFlow documentation';

    public function handle(): int
    {
        $version = config(
            'asasflow.docs.default_version',
            '1.0'
        );

        $larecipeBase = base_path(
            config(
                'larecipe.docs.path',
                'resources/docs'
            )
        );

        $sourcePath = dirname(__DIR__, 3)
            . '/docs/'
            . $version;

        if (! File::isDirectory($sourcePath)) {
            $this->error(
                "AsasFlow documentation source not found: {$sourcePath}"
            );

            return self::FAILURE;
        }

        /*
         * Determine where AsasFlow documentation lives.
         *
         * This follows the same logic as the installer.
         */
        $hostVersionPath = $larecipeBase . '/' . $version;

        $packageDocsPath = $larecipeBase
            . '/asasflow/'
            . $version;

        /*
         * If AsasFlow docs already exist in the namespaced location,
         * update that location.
         */
        if (File::isDirectory($packageDocsPath)) {
            $destinationPath = $packageDocsPath;
        } else {
            /*
             * Otherwise assume the package docs were adopted as
             * the application's default documentation.
             */
            $destinationPath = $hostVersionPath;
        }

        $this->info('Updating AsasFlow documentation...');

        if (
            File::isDirectory($destinationPath)
            && ! $this->option('force')
        ) {
            $this->warn(
                'Documentation already exists.'
            );

            if (! $this->confirm(
                'Overwrite existing AsasFlow documentation?'
            )) {
                $this->info('Documentation update cancelled.');

                return self::SUCCESS;
            }
        }

        File::ensureDirectoryExists(
            $destinationPath
        );

        File::copyDirectory(
            $sourcePath,
            $destinationPath
        );

        $this->info(
            "AsasFlow documentation updated successfully."
        );

        $this->line(
            "Source: {$sourcePath}"
        );

        $this->line(
            "Destination: {$destinationPath}"
        );

        return self::SUCCESS;
    }
}