<?php

use Illuminate\Support\Facades\Route;
use BinaryTorch\LaRecipe\Http\Controllers\DocumentationController;

$prefix     = config('asasflow.routes.prefix', 'docs/asasflow');
$middleware = config('asasflow.routes.middleware', ['web']);
$version    = config('asasflow.docs.default_version', '1.0');
$page       = config('asasflow.docs.default_page', 'overview');

Route::prefix($prefix)->middleware($middleware)->group(function () use ($version, $page, $prefix) {

    Route::redirect('/', "/{$prefix}/{$version}/{$page}")
        ->name('asasflow.docs.index');

    Route::get('/{routeVersion}/{routePage?}', function ($routeVersion, $routePage = null) use ($page, $prefix) {
        $routePage = $routePage ?? $page;

        // DocumentationRepository hardcodes larecipe.* config values at
        // construction time for internal links (docsRoute, defaultVersionUrl).
        // We must override BEFORE the controller/repository is constructed,
        // and restore AFTER the response is built.
        //
        // The four keys LaRecipe reads at construction + render time:
        //   larecipe.docs.path    → where to find markdown files
        //   larecipe.docs.route   → base URL for internal links (the "docs route")
        //   larecipe.docs.landing → default landing page
        //   larecipe.versions.default → default version for canonical links

        $origPath    = config('larecipe.docs.path');
        $origRoute   = config('larecipe.docs.route');
        $origLanding = config('larecipe.docs.landing');
        $origVersion = config('larecipe.versions.default');

        config([
            'larecipe.docs.path'        => '/resources/docs/asasflow',
            'larecipe.docs.route'       => "/{$prefix}",
            'larecipe.docs.landing'     => config('asasflow.docs.default_page', 'overview'),
            'larecipe.versions.default' => config('asasflow.docs.default_version', '1.0'),
        ]);

        try {
            // Resolve fresh — controller + repository read config at construction,
            // so we must NOT use a cached instance from the container.
            $response = app()->make(DocumentationController::class)->show($routeVersion, $routePage);
        } finally {
            config([
                'larecipe.docs.path'        => $origPath,
                'larecipe.docs.route'       => $origRoute,
                'larecipe.docs.landing'     => $origLanding,
                'larecipe.versions.default' => $origVersion,
            ]);
        }

        return $response;
    })->where('routePage', '.*')->name('asasflow.docs.show');
});
