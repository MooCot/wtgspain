<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Imports\RegisterImportUseCase;
use App\Infrastructure\Http\Requests\StoreImportRequest;
use App\Infrastructure\Http\Resources\ImportCreatedResource;
use App\Infrastructure\Http\Resources\ImportResource;
use App\Infrastructure\Persistence\Eloquent\Models\Import;
use App\Infrastructure\Queue\Jobs\ProcessImportJob;
use Illuminate\Http\JsonResponse;

class ImportController
{
    public function store(StoreImportRequest $request, RegisterImportUseCase $register): JsonResponse
    {
        $validated = $request->validated();

        $import = $register->handle($validated['supplier'], $validated['external_import_id'], $validated['sent_at']);

        // Резолвимо відповідь ДО dispatch: sync-черга виконує Job одразу й мутує
        // той самий $import in-place — інакше 202-відповідь показувала б вже
        // completed замість pending.
        $response = (new ImportCreatedResource($import))->response()->setStatusCode(202);

        if ($import->wasRecentlyCreated) {
            /** @var array<int, array<string, mixed>> $offers */
            $offers = $validated['offers'];
            ProcessImportJob::dispatch($import, collect($offers));
        }

        return $response;
    }

    public function show(Import $import): ImportResource
    {
        return new ImportResource($import);
    }
}
