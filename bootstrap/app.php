<?php

use App\Application\Reservations\Exceptions\OfferUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // /api/* — завжди JSON, незалежно від Accept-заголовка клієнта (інакше
        // Laravel за замовчуванням редиректить на / при ValidationException і
        // рендерить HTML-сторінку на 404 для не-JSON запитів).
        $exceptions->shouldRenderJsonWhen(function (Request $request): bool {
            return $request->is('api/*');
        });

        $exceptions->render(function (OfferUnavailableException $e, Request $request): JsonResponse {
            return response()->json(['message' => $e->getMessage()], 409);
        });

        // 404 (route model binding / route not found) — очікувана частина API-
        // контракту, не неочікуваний збій, тому завжди чисте тіло, незалежно
        // від APP_DEBUG (який інакше додає повний stack trace в JSON теж, не
        // лише в HTML-сторінку помилки).
        $exceptions->render(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Not found.'], 404);
        });
    })->create();
