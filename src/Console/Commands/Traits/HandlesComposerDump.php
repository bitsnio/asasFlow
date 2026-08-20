<?php

namespace Bitsnio\AsasFlow\Console\Commands\Traits;

use Illuminate\Support\Facades\Process;

trait HandlesComposerDump
{
    /**
     * Run composer dump-autoload with Windows compatibility
     */
    protected function runComposerDumpAutoload(): void
    {
        $this->components->info('Running composer dump-autoload...');

        try {
            $command = $this->getComposerCommand();
            $process = Process::path(base_path())
                ->command($command)
                ->timeout(300);

            // Windows specific handling
            if ($this->isWindows()) {
                $process->tty(true);
            }

            $result = $process->run();

            if ($result->successful()) {
                $this->components->info('✓ Composer dump-autoload completed successfully.');
            } else {
                $errorOutput = $result->errorOutput();
                $this->components->error('Composer dump-autoload failed: ' . $errorOutput);
                $this->components->warn('Please run "composer dump-autoload" manually after module creation.');
            }
        } catch (\Exception $e) {
            $this->components->error('Failed to run composer dump-autoload: ' . $e->getMessage());
            $this->components->warn('Please run "composer dump-autoload" manually after module creation.');
        }
    }

    /**
     * Get the appropriate composer command for the current OS
     */
    protected function getComposerCommand(): string
    {
        if ($this->isWindows()) {
            // Try to find which composer executable works on Windows
            $composerCommands = [
                'composer',
                'composer.phar',
                'php composer.phar',
                'cmd /c composer'
            ];

            foreach ($composerCommands as $command) {
                try {
                    $test = Process::path(base_path())
                        ->command($command . ' --version')
                        ->timeout(5)
                        ->run();
                    
                    if ($test->successful()) {
                        return $command . ' dump-autoload';
                    }
                } catch (\Exception $e) {
                    // Continue to next command
                    continue;
                }
            }

            // Fallback to cmd
            return 'cmd /c composer dump-autoload';
        }

        // Unix-like systems (Mac, Linux)
        return 'composer dump-autoload';
    }

    /**
     * Check if the current OS is Windows
     */
    protected function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}