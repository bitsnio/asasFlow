<?php

namespace AsasFlow\Features\ControllerGeneration\Contracts;

interface RouteGeneratorInterface
{
    public function generate($module, array $structure, array $options = []): array;
    public function preview($module, array $structure, array $options = []): array;
}
