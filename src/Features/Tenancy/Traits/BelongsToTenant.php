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
        if (config('asasflow.tenancy.database_strategy', 'single') !== 'single') {
            return;
        }

        // 1. Automatically scope all SELECT/UPDATE/DELETE queries by tenant_id (and site_id if present)
        if (self::shouldApplySingleDbTenancy()) {
            static::addGlobalScope('asasflow_tenant', function (Builder $builder): void {
                $model = $builder->getModel();
                $table = $model->getTable();
                $fillable = $model->getFillable();

                if (self::isTableTenantExempt($table)) {
                    return;
                }

                $tenantId = TenantContext::getTenantId();
                $tenantCol = (string) config('asasflow.tenancy.tenant_column', 'tenant_id');

                if ($tenantId !== null && ($fillable === [] || in_array($tenantCol, $fillable, true))) {
                    $builder->where("{$table}.{$tenantCol}", $tenantId);
                }

                $siteCol = (string) config('asasflow.tenancy.site_column', 'site_id');

                if (
                    config('asasflow.tenancy.site_enabled', true)
                    && !self::isTableSiteExempt($table)
                    && ($fillable === [] || in_array($siteCol, $fillable, true))
                    && TenantContext::getSiteId() !== null
                ) {
                    $builder->where("{$table}.{$siteCol}", TenantContext::getSiteId());
                }
            });
        }

        // 2. Automatically set tenant_id and site_id when creating a new record
        static::creating(function (Model $model): void {
            $table = $model->getTable();
            $fillable = $model->getFillable();

            if (self::isTableTenantExempt($table)) {
                return;
            }

            $tenantCol = (string) config('asasflow.tenancy.tenant_column', 'tenant_id');
            if (
                ($fillable === [] || in_array($tenantCol, $fillable, true))
                && $model->getAttribute($tenantCol) === null
            ) {
                $tenantId = TenantContext::getTenantId()
                    ?? (app()->bound('request') ? (request()->input($tenantCol) ?? request()->header('X-Tenant-ID')) : null)
                    ?? (function_exists('auth') && auth()->check() ? auth()->user()?->getAttribute($tenantCol) : null)
                    ?? 1;

                $model->setAttribute($tenantCol, $tenantId);
            }

            $siteCol = (string) config('asasflow.tenancy.site_column', 'site_id');
            if (
                config('asasflow.tenancy.site_enabled', true)
                && !self::isTableSiteExempt($table)
                && ($fillable === [] || in_array($siteCol, $fillable, true))
                && $model->getAttribute($siteCol) === null
            ) {
                $siteId = TenantContext::getSiteId()
                    ?? (app()->bound('request') ? (request()->input($siteCol) ?? request()->header('X-Site-ID')) : null)
                    ?? (function_exists('auth') && auth()->check() ? auth()->user()?->getAttribute($siteCol) : null);

                if ($siteId !== null && $siteId !== '') {
                    $model->setAttribute($siteCol, $siteId);
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
