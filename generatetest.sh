#!/bin/bash

# Settings Feature Tests Generation Script
# Uses project root as the base, mirrors the Controller setup script style
# Run from project root: ./setup-settings-tests.sh

echo "🚀 Setting up Settings Feature Tests..."
echo ""

PROJECT_ROOT="$(pwd)"
SETTINGS_DIR="$PROJECT_ROOT/src/Features/Settings"
TEST_DIR="$SETTINGS_DIR/Tests"

echo "📁 Project Root: $PROJECT_ROOT"
echo "📁 Settings Dir: $SETTINGS_DIR"
echo "📁 Tests Dir:    $TEST_DIR"
echo ""

# Sanity check — make sure we're at the package root
if [ ! -d "$PROJECT_ROOT/src" ]; then
    echo "❌ Error: $PROJECT_ROOT/src not found."
    echo "   Please run this script from the package root directory."
    exit 1
fi

# Remove existing tests directory if it exists
if [ -d "$TEST_DIR" ]; then
    echo "⚠️  Existing Tests directory found. Removing..."
    rm -rf "$TEST_DIR"
    echo "✅ Removed existing directory"
fi

# Create directory structure
mkdir -p "$TEST_DIR"

echo "✅ Directory structure created"
echo ""

# ============================================
# Helper function to create files
# ============================================
create_file() {
    local file_path="$1"
    local content="$2"

    mkdir -p "$(dirname "$file_path")"
    printf '%s\n' "$content" > "$file_path"
    echo "✅ Created: $file_path"
}

# ============================================
# 1. ModuleSettingsDiscoveryTest.php
# ============================================
create_file "$TEST_DIR/ModuleSettingsDiscoveryTest.php" '<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;

beforeEach(function () {
    app(ModuleSettingsDiscovery::class)->discover();
});

it("discovers modules that contain a settings.php file", function () {
    $registry = app(ModuleSettingsRegistry::class);

    expect($registry->all())
        ->toBeArray();
});

it("registers modules with settings definitions", function () {
    $registry = app(ModuleSettingsRegistry::class);

    foreach ($registry->all() as $module => $configKey) {
        expect($module)->toBeString()
            ->and($configKey)->toBeString();

        expect(
            app(ModuleSettingsService::class)
                ->definitions($module)
        )->toBeArray();
    }
});

it("loads settings definitions from module settings.php", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        expect($definitions)->toBeArray();

        foreach ($definitions as $key => $definition) {
            expect($key)->toBeString();

            if (is_array($definition)) {
                expect($definition)->toBeArray();
            }
        }
    }
});'

# ============================================
# 2. ModuleSettingsServiceTest.php
# ============================================
create_file "$TEST_DIR/ModuleSettingsServiceTest.php" '<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;

beforeEach(function () {
    app(\Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery::class)
        ->discover();
});

it("returns module settings with defaults", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);
        $values = $service->all($module);

        expect($values)->toBeArray();

        foreach ($definitions as $key => $definition) {
            $normalized = is_array($definition)
                ? array_merge([
                    "default" => null,
                    "scope" => "module",
                ], $definition)
                : [
                    "default" => $definition,
                    "scope" => "module",
                ];

            expect($values)
                ->toHaveKey($key);

            expect($values[$key])
                ->toBe($normalized["default"]);
        }
    }
});

it("can retrieve a single setting", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $value = $service->get($module, $key);

            expect($value)->not->toBeNull();
        }
    }
});

it("returns the supplied fallback for an unknown setting key", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        expect(
            $service->get(
                $module,
                "__non_existing_setting__",
                "fallback-value"
            )
        )->toBe("fallback-value");
    }
});

it("rejects an unknown setting during update", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        expect(fn () => $service->update(
            $module,
            [
                "__non_existing_setting__" => "test",
            ]
        ))->toThrow(
            \InvalidArgumentException::class
        );
    }
});

it("returns a complete schema", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $schema = $service->schema($module);

        expect($schema)->toBeArray();

        foreach ($schema as $key => $definition) {
            expect($definition)
                ->toHaveKey("key")
                ->toHaveKey("value")
                ->toHaveKey("default")
                ->toHaveKey("scope");
        }
    }
});'

# ============================================
# 3. ModuleSettingsScopeTest.php
# ============================================
create_file "$TEST_DIR/ModuleSettingsScopeTest.php" '<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;

beforeEach(function () {
    app(ModuleSettingsDiscovery::class)->discover();
});

it("supports module level settings", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $definition = is_array($definition)
                ? array_merge([
                    "scope" => "module",
                ], $definition)
                : [
                    "scope" => "module",
                ];

            if ($definition["scope"] !== "module") {
                continue;
            }

            $result = $service->update(
                $module,
                [
                    $key => $definition["default"],
                ]
            );

            expect($result)
                ->toHaveKey($key);
        }
    }
});

it("rejects company scope for module level settings", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $definition = is_array($definition)
                ? array_merge([
                    "scope" => "module",
                ], $definition)
                : [
                    "scope" => "module",
                ];

            if ($definition["scope"] !== "module") {
                continue;
            }

            expect(fn () => $service->update(
                $module,
                [
                    $key => $definition["default"],
                ],
                1
            ))->toThrow(\InvalidArgumentException::class);
        }
    }
});

it("rejects site scope without a company", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $definition = is_array($definition)
                ? array_merge([
                    "scope" => "module",
                ], $definition)
                : [
                    "scope" => "module",
                ];

            if ($definition["scope"] !== "site") {
                continue;
            }

            expect(fn () => $service->update(
                $module,
                [
                    $key => $definition["default"],
                ],
                null,
                1
            ))->toThrow(\InvalidArgumentException::class);
        }
    }
});'

# ============================================
# 4. ModuleSettingsCacheTest.php
# ============================================
create_file "$TEST_DIR/ModuleSettingsCacheTest.php" '<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Repositories\ModuleSettingsRepository;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    app(ModuleSettingsDiscovery::class)->discover();

    Cache::flush();
});

it("caches resolved settings", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $first = $service->all($module);

        expect($first)->toBeArray();

        $second = $service->all($module);

        expect($second)->toBe($first);
    }
});

it("forgets the cache after an update", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $definition = is_array($definition)
                ? array_merge([
                    "scope" => "module",
                    "default" => null,
                ], $definition)
                : [
                    "scope" => "module",
                    "default" => $definition,
                ];

            if ($definition["scope"] !== "module") {
                continue;
            }

            $newValue = "__cache_test__";

            $service->update(
                $module,
                [
                    $key => $newValue,
                ]
            );

            expect(
                $service->get($module, $key)
            )->toBe($newValue);

            return;
        }
    }

    $this->markTestSkipped(
        "No module-level settings are available."
    );
});

it("isolates cache entries by company and site", function () {
    $service = app(ModuleSettingsService::class);
    $registry = app(ModuleSettingsRegistry::class);

    foreach ($registry->all() as $module => $configKey) {
        $companyValues = $service->all(
            $module,
            100
        );

        $siteValues = $service->all(
            $module,
            100,
            200
        );

        expect($companyValues)->toBeArray()
            ->and($siteValues)->toBeArray();

        expect($siteValues)->not->toBeSameAs($companyValues);
    }
});'

# ============================================
# 5. ModuleSettingsApiTest.php
# ============================================
create_file "$TEST_DIR/ModuleSettingsApiTest.php" '<?php

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
});'

# ============================================
# 6. COMPLETION MESSAGE
# ============================================
echo ""
echo "✅ Settings Feature Tests setup complete!"
echo ""
echo "📊 Files created:"
echo "  - Tests: " $(find "$TEST_DIR" -maxdepth 1 -type f -name "*.php" | wc -l) "files"
echo ""
echo "📁 Location:"
echo "  - Tests: $TEST_DIR"
echo ""
echo "📋 Next steps:"
echo "1. Run the test suite:"
echo "   ./vendor/bin/pest src/Features/Settings/Tests"
echo ""
echo "2. To publish them into the host application (if registered):"
echo "   php artisan vendor:publish --tag=asasflow-settings-tests"
echo ""