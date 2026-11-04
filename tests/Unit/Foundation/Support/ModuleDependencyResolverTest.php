<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Support\ModuleDependencyResolver;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->resolver = new ModuleDependencyResolver();
});

it('resolves module compatibility using default version when config is unset', function () {
    Config::set('asasflow.version', null);

    $modules = [
        'Core' => [
            'enabled' => true,
            'requires' => [],
            'compatibility' => [
                'asasflow' => '^1.0',
            ],
        ],
    ];

    $resolved = $this->resolver->resolve($modules);

    expect($resolved)->toHaveKey('Core');
});

it('bypasses semver checks when asasflow version is a dev branch', function () {
    Config::set('asasflow.version', 'dev-main');

    $modules = [
        'Core' => [
            'enabled' => true,
            'requires' => [],
            'compatibility' => [
                'asasflow' => '^1.0',
            ],
        ],
    ];

    $resolved = $this->resolver->resolve($modules);

    expect($resolved)->toHaveKey('Core');
});

it('throws LogicException when installed asasflow version is incompatible with module constraint', function () {
    Config::set('asasflow.version', '1.0.0');

    $modules = [
        'Core' => [
            'enabled' => true,
            'requires' => [],
            'compatibility' => [
                'asasflow' => '^2.0',
            ],
        ],
    ];

    $this->resolver->resolve($modules);
})->throws(LogicException::class, 'Module [Core] requires AsasFlow [^2.0], but the installed version is [1.0.0].');
