<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Http\Requests;

use Bitsnio\AsasFlow\Core\Services\ModuleSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ModuleSettingsService $service */
        $service = app(ModuleSettingsService::class);

        $module = (string) $this->route('module');

        $definitions = $service->definitions($module);

        $rules = [
            'enabled' => [
                'sometimes',
                'boolean',
            ],

            'values' => [
                'sometimes',
                'array',
            ],
        ];

        foreach ($definitions as $key => $definition) {
            if (! empty($definition['rules'])) {
                $rules["values.{$key}"] =
                    $definition['rules'];
            }
        }

        return $rules;
    }
}