<?php

namespace Bitsnio\Modules\Process;

use Bitsnio\Modules\Contracts\RepositoryInterface;
use Bitsnio\Modules\Contracts\RunableInterface;

class Runner implements RunableInterface
{
    /**
     * The module instance.
     */
    protected RepositoryInterface $module;

    public function __construct(RepositoryInterface $module)
    {
        $this->module = $module;
    }

    /**
     * Run the given command.
     */
    public function run(string $command)
    {
        passthru($command);
    }
}
