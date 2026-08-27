<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Contracts;

interface GeneratorInterface
{
    public function generate($module, array $structure, array $options = []): array;
    public function preview($module, array $structure, array $options = []): array;
}
