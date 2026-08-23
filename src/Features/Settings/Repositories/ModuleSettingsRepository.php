<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Repositories;

use Bitsnio\AsasFlow\Features\Settings\Models\ModuleSetting;

class ModuleSettingsRepository
{
    public function find(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): ?ModuleSetting {
        return ModuleSetting::query()
            ->where('module', $module)
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->first();
    }

    public function values(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): array {
        return $this->find(
            $module,
            $companyId,
            $siteId
        )?->values ?? [];
    }

    public function save(
        string $module,
        array $values,
        ?int $companyId = null,
        ?int $siteId = null
    ): ModuleSetting {
        return ModuleSetting::query()->updateOrCreate(
            [
                'module' => $module,
                'company_id' => $companyId,
                'site_id' => $siteId,
            ],
            [
                'values' => $values,
            ]
        );
    }
}
