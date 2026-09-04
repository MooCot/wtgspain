<?php

namespace App\Providers;

use App\Application\Imports\Ports\OfferRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentOfferRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OfferRepository::class, EloquentOfferRepository::class);
    }
}
