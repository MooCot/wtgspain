<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Reservations\CreateReservationUseCase;
use App\Infrastructure\Http\Requests\StoreReservationRequest;
use App\Infrastructure\Http\Resources\ReservationResource;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ReservationController
{
    #[OA\Post(
        path: '/api/offers/{offer}/reservations',
        summary: 'Reserve a unit of an offer (atomic, race-safe)',
        tags: ['Reservations'],
        parameters: [
            new OA\PathParameter(name: 'offer', description: 'Offer ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['client_reference', 'customer_name', 'customer_email'],
                properties: [
                    new OA\Property(property: 'client_reference', type: 'string', example: 'web-order-9f782b1c'),
                    new OA\Property(property: 'customer_name', type: 'string', example: 'John Smith'),
                    new OA\Property(property: 'customer_email', type: 'string', format: 'email', example: 'john@example.com'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Reservation created'),
            new OA\Response(response: 404, description: 'Offer not found'),
            new OA\Response(response: 409, description: 'No available units left'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(Offer $offer, StoreReservationRequest $request, CreateReservationUseCase $useCase): JsonResponse
    {
        $reservation = $useCase->handle($offer, $request->validated());

        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }
}
