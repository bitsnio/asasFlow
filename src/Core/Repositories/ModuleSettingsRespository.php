<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Repositories;

use Bitsnio\AsasFlow\Core\Models\ModuleSettings;

class ModuleSettingsRepository
{
    public function find(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): ?ModuleSettings {
        return ModuleSettings::query()
            ->where('module', $module)
            ->where(function ($query) use ($companyId) {
                if ($companyId === null) {
                    $query->whereNull('company_id');
                } else {
                    $query->where('company_id', $companyId);
                }
            })
            ->where(function ($query) use ($siteId) {
                if ($siteId === null) {
                    $query->whereNull('site_id');
                } else {
                    $query->where('site_id', $siteId);
                }
            })
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
    ): ModuleSettings {
        return ModuleSettings::query()->updateOrCreate(
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