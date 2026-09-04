<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Properties\SearchPropertiesUseCase;
use App\Infrastructure\Http\Requests\SearchPropertiesRequest;
use App\Infrastructure\Http\Resources\PropertyResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PropertyController
{
    #[OA\Get(
        path: '/api/properties',
        summary: 'Search the cheapest actual offer per property',
        tags: ['Properties'],
        parameters: [
            new OA\QueryParameter(name: 'city', schema: new OA\Schema(type: 'string'), example: 'Barcelona'),
            new OA\QueryParameter(name: 'check_in', required: true, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-10-10'),
            new OA\QueryParameter(name: 'check_out', required: true, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-10-15'),
            new OA\QueryParameter(name: 'guests', required: true, schema: new OA\Schema(type: 'integer'), example: 2),
            new OA\QueryParameter(name: 'page', schema: new OA\Schema(type: 'integer'), example: 1),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Matching properties with best offer, cheapest first',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'code', type: 'string', example: 'BCN-0001'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Apartment near Sagrada Familia'),
                                    new OA\Property(property: 'city', type: 'string', example: 'Barcelona'),
                                    new OA\Property(
                                        property: 'best_offer',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 125),
                                            new OA\Property(property: 'supplier', type: 'string', example: 'supplier-a'),
                                            new OA\Property(property: 'price', type: 'integer', example: 72500),
                                            new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                                            new OA\Property(property: 'available_units', type: 'integer', example: 2),
                                            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', example: '2026-09-10T23:59:59Z'),
                                        ],
                                    ),
                                ],
                            ),
                        ),
                        new OA\Property(property: 'next', type: 'string', nullable: true),
                        new OA\Property(property: 'prev', type: 'string', nullable: true),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                    ],
                ),
            ),
            new OA\Response(response: 422, description: 'Validation error (missing check_in/check_out/guests)'),
        ],
    )]
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
