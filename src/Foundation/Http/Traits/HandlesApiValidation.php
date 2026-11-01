<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Http\Traits;

use Bitsnio\AsasFlow\Foundation\Http\ApiResponseFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait HandlesApiValidation
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            app(ApiResponseFactory::class)->validationError(
                $validator->errors(),
                'Validation failed',
            )
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            app(ApiResponseFactory::class)->forbidden(
                'Forbidden.'
            )
        );
    }
}
