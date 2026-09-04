<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Imports\RegisterImportUseCase;
use App\Infrastructure\Http\Requests\StoreImportRequest;
use App\Infrastructure\Http\Resources\ImportCreatedResource;
use App\Infrastructure\Http\Resources\ImportResource;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Queue\Jobs\ProcessImportJob;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ImportController
{
    #[OA\Post(
        path: '/api/imports',
        summary: 'Import offers from a supplier (async, idempotent)',
        tags: ['Imports'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['supplier', 'external_import_id', 'sent_at', 'offers'],
                properties: [
                    new OA\Property(property: 'supplier', type: 'string', example: 'supplier-a'),
                    new OA\Property(property: 'external_import_id', type: 'string', example: 'import-2026-09-01-001'),
                    new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', example: '2026-09-01T10:00:00Z'),
                    new OA\Property(
                        property: 'offers',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'external_id', type: 'string', example: 'offer-a-10001'),
                                new OA\Property(
                                    property: 'property',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'code', type: 'string', example: 'BCN-0001'),
                                        new OA\Property(property: 'name', type: 'string', example: 'Apartment near Sagrada Familia'),
                                        new OA\Property(property: 'city', type: 'string', example: 'Barcelona'),
                                    ],
                                ),
                                new OA\Property(property: 'check_in', type: 'string', format: 'date', example: '2026-10-10'),
                                new OA\Property(property: 'check_out', type: 'string', format: 'date', example: '2026-10-15'),
                                new OA\Property(property: 'max_guests', type: 'integer', example: 4),
                                new OA\Property(property: 'price', type: 'integer', example: 72500),
                                new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                                new OA\Property(property: 'available_units', type: 'integer', example: 2),
                                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', example: '2026-09-10T23:59:59Z'),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Import accepted, processing queued',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 15),
                                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 422, description: 'Validation error (e.g. unknown supplier)'),
        ],
    )]
    public function store(StoreImportRequest $request, RegisterImportUseCase $register): JsonResponse
    {
        $validated = $request->validated();

        $import = $register->handle($validated['supplier'], $validated['external_import_id'], $validated['sent_at']);

        $response = (new ImportCreatedResource($import))->response()->setStatusCode(202);

        if ($import->wasRecentlyCreated) {
            /** @var array<int, array<string, mixed>> $offers */
            $offers = $validated['offers'];
            ProcessImportJob::dispatch($import, collect($offers));
        }

        return $response;
    }

    #[OA\Get(
        path: '/api/imports/{import}',
        summary: 'Get the current status of an import',
        tags: ['Imports'],
        parameters: [
            new OA\PathParameter(name: 'import', description: 'Import ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Import status'),
            new OA\Response(response: 404, description: 'Import not found'),
        ],
    )]
    public function show(Import $import): ImportResource
    {
        return new ImportResource($import);
    }
}
