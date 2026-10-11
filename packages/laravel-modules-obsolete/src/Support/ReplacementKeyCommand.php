<?php

namespace Bitsnio\Modules\Support;

use Bitsnio\Modules\Generators\ModuleGenerator;

abstract class ReplacementKeyCommand
{
    public function __construct(protected ModuleGenerator $generator) {}

    abstract public function handle(): string;
}
