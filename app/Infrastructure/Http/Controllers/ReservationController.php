<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Reservations\CreateReservationUseCase;
use App\Infrastructure\Http\Requests\StoreReservationRequest;
use App\Infrastructure\Http\Resources\ReservationResource;
use App\Infrastructure\Persistence\Eloquent\Models\Offer;
use Illuminate\Http\JsonResponse;

class ReservationController
{
    public function store(Offer $offer, StoreReservationRequest $request, CreateReservationUseCase $useCase): JsonResponse
    {
        $reservation = $useCase->handle($offer, $request->validated());

        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }
}
