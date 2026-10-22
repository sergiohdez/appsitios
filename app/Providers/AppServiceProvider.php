<?php

namespace App\Providers;

use App\Models\AccessRequest;
use App\Policies\AccessRequestPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(AccessRequest::class, AccessRequestPolicy::class);
    }
}
