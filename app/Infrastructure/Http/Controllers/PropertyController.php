<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Properties\SearchPropertiesUseCase;
use App\Infrastructure\Http\Requests\SearchPropertiesRequest;
use App\Infrastructure\Http\Resources\PropertyResource;
use Illuminate\Http\JsonResponse;

class PropertyController
{
    public function index(SearchPropertiesRequest $request, SearchPropertiesUseCase $search): JsonResponse
    {
        $paginator = $search->handle($request->searchCriteria());

        return response()->json([
            'data' => PropertyResource::collection($paginator->items()),
            'next' => $paginator->nextPageUrl(),
            'prev' => $paginator->previousPageUrl(),
            'per_page' => $paginator->perPage(),
        ]);
    }
}
