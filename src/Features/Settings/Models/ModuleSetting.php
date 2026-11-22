<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleSetting extends Model
{
    protected $table = 'module_settings';

    protected $fillable = [
        'module',
        'tenant_id',
        'company_id',
        'site_id',
        'values',
    ];

    protected $casts = [
        'values' => 'array',
    ];

    public function getCompanyIdAttribute(): ?int
    {
        return $this->attributes['tenant_id'] ?? $this->attributes['company_id'] ?? null;
    }

    public function setCompanyIdAttribute(?int $value): void
    {
        $this->attributes['tenant_id'] = $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Scope Helpers
    |--------------------------------------------------------------------------
    */

    public function isModuleLevel(): bool
    {
        return $this->tenant_id === null;
    }

    public function isCompanyLevel(): bool
    {
        return $this->tenant_id !== null && $this->site_id === null;
    }

    public function isSiteLevel(): bool
    {
        return $this->tenant_id !== null && $this->site_id !== null;
    }
}