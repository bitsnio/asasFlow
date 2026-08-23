<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => [
                'required',
                'array',
            ],

            'company_id' => [
                'nullable',
                'integer',
            ],

            'site_id' => [
                'nullable',
                'integer',
            ],
        ];
    }
}