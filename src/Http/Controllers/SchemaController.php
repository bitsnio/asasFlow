<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Http\Controllers;

use Bitsnio\AsasFlow\Facades\Menu;
use Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class SchemaController extends Controller
{
    use JsonResponseTrait;

    public function show(Request $request): JsonResponse
    {
        $link = (string) $request->query('link', '');
        $id = $request->query('id');

        if ($link === '') {
            return $this->errorResponse('Query parameter [link] is required.', 422);
        }

        try {
            $data = Menu::getSchemaByLink($link, $id);

            return $this->successResponse($data, 'Schema retrieved successfully.');
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse("Record [{$id}] not found for schema link [{$link}].");
        } catch (Throwable $e) {
            return $this->notFoundResponse($e->getMessage());
        }
    }
}
