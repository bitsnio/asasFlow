<?php

namespace Bitsnio\Modules\Contracts;

interface RunableInterface
{
    /**
     * Run the specified command.
     */
    public function run(string $command);
}
