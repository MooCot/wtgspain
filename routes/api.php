<?php

use App\Infrastructure\Http\Controllers\ImportController;
use App\Infrastructure\Http\Controllers\PropertyController;
use App\Infrastructure\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::post('/imports', [ImportController::class, 'store']);
Route::get('/imports/{import}', [ImportController::class, 'show']);

Route::get('/properties', [PropertyController::class, 'index']);

Route::post('/offers/{offer}/reservations', [ReservationController::class, 'store']);
