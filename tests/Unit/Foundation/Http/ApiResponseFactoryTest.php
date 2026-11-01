<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Http\ApiResponseFactory;
use Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class SampleTestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'full_label' => strtoupper($this->resource['name']),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource_version' => 'v1',
            ],
        ];
    }
}

class SampleTestResourceCollection extends ResourceCollection
{
    public $collects = SampleTestResource::class;

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'columns' => ['id', 'full_label'],
            ],
            'filterable' => true,
        ];
    }
}

it('unwraps JsonResource with custom toArray and preserves custom metadata without nested data.data', function () {
    $factory = app(ApiResponseFactory::class);
    $resource = (new SampleTestResource(['id' => 7, 'name' => 'Alpha']))
        ->additional(['extra_flag' => true]);

    $response = $factory->success($resource, 'Loaded item', 200, ['X-Custom' => 'Yes']);
    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('X-Custom'))->toBe('Yes')
        ->and($payload)->toBe([
            'success' => true,
            'message' => 'Loaded item',
            'data' => [
                'id' => 7,
                'full_label' => 'ALPHA',
            ],
            'meta' => [
                'resource_version' => 'v1',
                'extra_flag' => true,
            ],
        ]);
});

it('formats paginated ResourceCollection with custom metadata and normalized pagination meta', function () {
    $factory = app(ApiResponseFactory::class);

    $paginator = new LengthAwarePaginator(
        items: [
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ],
        total: 10,
        perPage: 2,
        currentPage: 1,
    );

    $collection = new SampleTestResourceCollection($paginator);
    $response = $factory->paginated($collection, 'Items fetched');
    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(200)
        ->and($payload)->toBe([
            'success' => true,
            'message' => 'Items fetched',
            'data' => [
                ['id' => 1, 'full_label' => 'FIRST'],
                ['id' => 2, 'full_label' => 'SECOND'],
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => 2,
                'total' => 10,
                'last_page' => 5,
                'from' => 1,
                'to' => 2,
                'has_more_pages' => true,
                'columns' => ['id', 'full_label'],
                'filterable' => true,
            ],
        ]);
});

it('returns 201 for created responses and preserves custom headers', function () {
    $factory = app(ApiResponseFactory::class);
    $response = $factory->created(['id' => 99], 'Record created', ['X-Trace-Id' => 'trace-123']);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->headers->get('X-Trace-Id'))->toBe('trace-123')
        ->and($response->getData(true))->toBe([
            'success' => true,
            'message' => 'Record created',
            'data' => ['id' => 99],
        ]);
});

it('validates error status codes and rejects non-error HTTP statuses', function (int $invalidStatus) {
    $factory = app(ApiResponseFactory::class);

    expect(fn () => $factory->error('Invalid status', $invalidStatus))
        ->toThrow(InvalidArgumentException::class);
})->with([200, 201, 204, 302, 600]);

it('formats standard error helpers through JsonResponseTrait', function () {
    $controller = new class {
        use JsonResponseTrait;

        public function callNotFound()
        {
            return $this->notFoundResponse('User not found');
        }

        public function callValidation()
        {
            return $this->validationErrorResponse(['email' => ['Invalid email']]);
        }

        public function callNotImplemented()
        {
            return $this->notImplementedResponse('Export not implemented');
        }
    };

    $notFound = $controller->callNotFound();
    expect($notFound->getStatusCode())->toBe(404)
        ->and($notFound->getData(true))->toBe([
            'success' => false,
            'message' => 'User not found',
        ]);

    $validation = $controller->callValidation();
    expect($validation->getStatusCode())->toBe(422)
        ->and($validation->getData(true))->toBe([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => ['email' => ['Invalid email']],
        ]);

    $notImpl = $controller->callNotImplemented();
    expect($notImpl->getStatusCode())->toBe(501)
        ->and($notImpl->getData(true))->toBe([
            'success' => false,
            'message' => 'Export not implemented',
        ]);
});
