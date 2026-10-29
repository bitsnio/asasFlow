<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Http\Controllers;

use Bitsnio\AsasFlow\Facades\Menu;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class SchemaController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $link = (string) $request->query('link', '');
        $id = $request->query('id');

        if ($link === '') {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter [link] is required.',
            ], 422);
        }

        try {
            $data = Menu::getSchemaByLink($link, $id);

            return response()->json([
                'success' => true,
                'message' => 'Schema retrieved successfully.',
                'data' => $data,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => "Record [{$id}] not found for schema link [{$link}].",
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
