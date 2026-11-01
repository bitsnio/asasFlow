<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/api/test-macro-success', function () {
        return response()->success(['id' => 10], 'Fetched via macro');
    });

    Route::post('/api/test-macro-created', function () {
        return response()->created(['id' => 11], 'Created via macro');
    });

    Route::get('/api/test-macro-paginated', function () {
        $paginator = new LengthAwarePaginator([['id' => 1]], 1, 15, 1);

        return response()->paginated($paginator, 'Paginated via macro');
    });

    Route::get('/api/test-macro-error', function () {
        return response()->error('Bad request macro', 400, ['field' => ['Invalid']]);
    });

    Route::post('/api/test-validation-exception', function (Request $request) {
        $request->validate(['email' => ['required', 'email']]);
    });

    Route::get('/api/test-unauthenticated', function () {
        throw new AuthenticationException('Token expired.');
    });

    Route::get('/api/test-forbidden', function () {
        throw new AuthorizationException('Missing permission.');
    });

    Route::get('/api/test-not-found', function () {
        throw (new ModelNotFoundException())->setModel('User', [42]);
    });

    Route::get('/api/test-server-error', function () {
        throw new RuntimeException('Sensitive database connection string failure');
    });
});

it('serves responses using registered Laravel response() macros', function () {
    $this->getJson('/api/test-macro-success')
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Fetched via macro',
            'data' => ['id' => 10],
        ]);

    $this->postJson('/api/test-macro-created')
        ->assertCreated()
        ->assertExactJson([
            'success' => true,
            'message' => 'Created via macro',
            'data' => ['id' => 11],
        ]);

    $this->getJson('/api/test-macro-paginated')
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Paginated via macro',
            'data' => [['id' => 1]],
            'meta' => [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 1,
                'last_page' => 1,
                'from' => 1,
                'to' => 1,
                'has_more_pages' => false,
            ],
        ]);

    $this->getJson('/api/test-macro-error')
        ->assertStatus(400)
        ->assertExactJson([
            'success' => false,
            'message' => 'Bad request macro',
            'errors' => ['field' => ['Invalid']],
        ]);
});

it('normalizes ValidationException (422), AuthenticationException (401), AuthorizationException (403), ModelNotFoundException (404), and Server Error (500)', function () {
    $this->postJson('/api/test-validation-exception', [])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Validation failed',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'errors' => ['email'],
        ]);

    $this->getJson('/api/test-unauthenticated')
        ->assertStatus(401)
        ->assertExactJson([
            'success' => false,
            'message' => 'Token expired.',
        ]);

    $this->getJson('/api/test-forbidden')
        ->assertStatus(403)
        ->assertExactJson([
            'success' => false,
            'message' => 'Missing permission.',
        ]);

    $this->getJson('/api/test-not-found')
        ->assertStatus(404)
        ->assertExactJson([
            'success' => false,
            'message' => 'Resource not found.',
        ]);

    // Ensure production mode hides sensitive exception details
    config(['app.debug' => false]);
    $this->getJson('/api/test-server-error')
        ->assertStatus(500)
        ->assertExactJson([
            'success' => false,
            'message' => 'Server error.',
        ]);
});
