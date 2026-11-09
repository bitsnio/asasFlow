<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Http\Controllers;

use Bitsnio\AsasFlow\Foundation\Http\Traits\DropdownTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DropdownController extends Controller
{
    use DropdownTrait;

    public function __invoke(
        Request $request,
        string $table,
        string $label = 'name',
        ?string $value = null,
    ): JsonResponse {
        $reservedKeys = [
            'limit',
            'group_by',
            'search',
            'order_by',
            'order_dir',
            'module',
            'key',
            'include_all',
        ];

        $filters = $request->except($reservedKeys);
        $limit = $request->filled('limit') ? (int) $request->input('limit') : null;
        $groupBy = $request->filled('group_by') ? (string) $request->input('group_by') : null;
        $search = $request->filled('search') ? (string) $request->input('search') : null;
        $orderBy = $request->filled('order_by') ? (string) $request->input('order_by') : null;
        $orderDir = (string) $request->input('order_dir', 'asc');

        $options = [
            'module' => $request->query('module'),
            'key' => $request->query('key'),
            'include_all' => $request->query('include_all', true),
        ];

        return $this->getDropdownData(
            table: $table,
            label: $label,
            value: $value,
            filters: $filters,
            limit: $limit,
            groupBy: $groupBy,
            search: $search,
            orderBy: $orderBy,
            orderDir: $orderDir,
            options: $options,
        );
    }
}
