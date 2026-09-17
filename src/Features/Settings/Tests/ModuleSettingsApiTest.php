<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;

beforeEach(function () {
    app(ModuleSettingsDiscovery::class)->discover();
});

it("requires authentication for settings endpoints", function () {
    $registry = app(ModuleSettingsRegistry::class);

    foreach ($registry->all() as $module => $configKey) {
        $this->getJson(
            "/api/settings/{$module}"
        )->assertUnauthorized();

        return;
    }

    $this->markTestSkipped(
        "No settings-enabled modules were discovered."
    );
});

it("can retrieve module settings through the API", function () {
    /*
     * Authenticate using the host application application auth mechanism.
     *
     * Example:
     *
     * $this->actingAs($user, "api");
     *
     * or, if the host uses JWT:
     *
     * $token = auth("api")->login($user);
     *
     * $this->withHeader(
     *     "Authorization",
     *     "Bearer {$token}"
     * );
     */

    $this->markTestIncomplete(
        "Configure authentication for the host application."
    );
});

it("can update module settings through the API", function () {
    /*
     * Configure authentication above before enabling this test.
     *
     * The actual setting key/value should be selected dynamically
     * from a discovered module so this test does not depend on a
     * hard-coded application module.
     */

    $this->markTestIncomplete(
        "Configure authentication for the host application."
    );
});
