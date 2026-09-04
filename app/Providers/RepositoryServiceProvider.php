<?php

namespace App\Providers;

use App\Application\Imports\Ports\ImportRepository;
use App\Application\Imports\Ports\OfferRepository;
use App\Application\Imports\Ports\PropertyRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentImportRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentOfferRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentPropertyRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OfferRepository::class, EloquentOfferRepository::class);
        $this->app->bind(PropertyRepository::class, EloquentPropertyRepository::class);
        $this->app->bind(ImportRepository::class, EloquentImportRepository::class);
    }
}
