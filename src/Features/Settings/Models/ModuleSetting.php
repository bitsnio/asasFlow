<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleSetting extends Model
{
    protected $table = 'module_settings';

    protected $fillable = [
        'module',
        'company_id',
        'site_id',
        'values',
    ];

    protected $casts = [
        'values' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scope Helpers
    |--------------------------------------------------------------------------
    */

    public function isModuleLevel(): bool
    {
        return $this->company_id === null;
    }

    public function isCompanyLevel(): bool
    {
        return $this->company_id !== null && $this->site_id === null;
    }

    public function isSiteLevel(): bool
    {
        return $this->company_id !== null && $this->site_id !== null;
    }
}