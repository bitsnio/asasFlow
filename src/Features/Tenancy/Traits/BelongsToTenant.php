<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Tenancy\Traits;

use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        if (!self::shouldApplySingleDbTenancy()) {
            return;
        }

        // 1. Automatically scope all SELECT/UPDATE/DELETE queries by tenant_id (and site_id if present)
        static::addGlobalScope('asasflow_tenant', function (Builder $builder): void {
            $model = $builder->getModel();
            $table = $model->getTable();

            if (self::isTableTenantExempt($table)) {
                return;
            }

            $tenantId = TenantContext::getTenantId();
            $tenantCol = config('asasflow.tenancy.tenant_column', 'tenant_id');

            if ($tenantId !== null) {
                $builder->where("{$table}.{$tenantCol}", $tenantId);
            }

            if (
                config('asasflow.tenancy.site_enabled', true)
                && !self::isTableSiteExempt($table)
                && TenantContext::getSiteId() !== null
            ) {
                $siteCol = config('asasflow.tenancy.site_column', 'site_id');
                $builder->where("{$table}.{$siteCol}", TenantContext::getSiteId());
            }
        });

        // 2. Automatically set tenant_id and site_id when creating a new record
        static::creating(function (Model $model): void {
            $table = $model->getTable();

            if (self::isTableTenantExempt($table)) {
                return;
            }

            $tenantCol = config('asasflow.tenancy.tenant_column', 'tenant_id');
            if ($model->getAttribute($tenantCol) === null && TenantContext::getTenantId() !== null) {
                $model->setAttribute($tenantCol, TenantContext::getTenantId());
            }

            if (
                config('asasflow.tenancy.site_enabled', true)
                && !self::isTableSiteExempt($table)
            ) {
                $siteCol = config('asasflow.tenancy.site_column', 'site_id');
                if ($model->getAttribute($siteCol) === null && TenantContext::getSiteId() !== null) {
                    $model->setAttribute($siteCol, TenantContext::getSiteId());
                }
            }
        });
    }

    protected static function shouldApplySingleDbTenancy(): bool
    {
        return (bool) config('asasflow.tenancy.enabled', false)
            && config('asasflow.tenancy.database_strategy', 'single') === 'single';
    }

    protected static function isTableTenantExempt(string $table): bool
    {
        $exempt = config('asasflow.tenancy.exempt_tables', ['tenants', 'tenant_domains']);

        return in_array($table, $exempt, true);
    }

    protected static function isTableSiteExempt(string $table): bool
    {
        $exempt = config('asasflow.tenancy.site_exempt_tables', ['sites']);

        return in_array($table, $exempt, true);
    }
}
