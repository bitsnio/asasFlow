<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Http\Exceptions;

use Bitsnio\AsasFlow\Foundation\Http\ApiResponseFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionRenderer
{
    public function __construct(
        protected ApiResponseFactory $responseFactory,
    ) {}

    public function register(ExceptionHandler $handler): void
    {
        if (!method_exists($handler, 'renderable')) {
            return;
        }

        $handler->renderable(function (Throwable $e, Request $request): ?JsonResponse {
            if (!$this->shouldRenderJson($request)) {
                return null;
            }

            return $this->render($e);
        });
    }

    public function shouldRenderJson(Request $request): bool
    {
        if (!config('asasflow.responses.render_exceptions', true)) {
            return false;
        }

        return $request->expectsJson() || $request->is('api/*');
    }

    public function render(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->responseFactory->validationError(
                $e->errors(),
                'Validation failed',
            );
        }

        if ($e instanceof AuthenticationException) {
            return $this->responseFactory->unauthorized(
                $e->getMessage() !== '' ? $e->getMessage() : 'Unauthenticated.',
            );
        }

        if (
            $e instanceof AuthorizationException
            || $e instanceof AccessDeniedHttpException
            || is_a($e, 'Spatie\Permission\Exceptions\UnauthorizedException')
        ) {
            return $this->responseFactory->forbidden(
                $e->getMessage() !== '' ? $e->getMessage() : 'Forbidden.',
            );
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return $this->responseFactory->notFound('Resource not found.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $status = ($status >= 400 && $status <= 599) ? $status : 500;
            $message = $e->getMessage() !== '' ? $e->getMessage() : 'HTTP error.';

            return $this->responseFactory->error(
                $message,
                $status,
                null,
                $e->getHeaders(),
            );
        }

        $message = (bool) config('app.debug', false) && $e->getMessage() !== ''
            ? $e->getMessage()
            : 'Server error.';

        return $this->responseFactory->error($message, 500);
    }
}
