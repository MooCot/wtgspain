<?php

namespace App\Providers;

use App\Application\Imports\Ports\ImportRepository;
use App\Application\Imports\Ports\SupplierRepository;
use App\Application\Offers\Ports\OfferRepository;
use App\Application\Properties\Ports\PropertyRepository;
use App\Application\Reservations\Ports\ReservationRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentImportRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentOfferRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentPropertyRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentReservationRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentSupplierRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OfferRepository::class, EloquentOfferRepository::class);
        $this->app->bind(PropertyRepository::class, EloquentPropertyRepository::class);
        $this->app->bind(ImportRepository::class, EloquentImportRepository::class);
        $this->app->bind(SupplierRepository::class, EloquentSupplierRepository::class);
        $this->app->bind(ReservationRepository::class, EloquentReservationRepository::class);
    }
}
