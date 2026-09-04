<?php

namespace App\Infrastructure\Http;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'wtgspain API',
    description: 'Асинхронний імпорт пропозицій житла, пошук найдешевшої актуальної пропозиції, бронювання.'
)]
#[OA\Server(url: 'http://localhost:8080', description: 'Local (docker compose)')]
class OpenApi
{
    //
}
