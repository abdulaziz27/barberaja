<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Observers\TenantObserver;
use App\Observers\UserRoleObserver;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\XenditDisbursementClient::class,
            \App\Services\XenditDisbursementClient::class
        );
    }

    public function boot(): void
    {
        Tenant::observe(TenantObserver::class);
        UserRole::observe(UserRoleObserver::class);

        Gate::define('platformAdmin', function (User $user): bool {
            return UserRole::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('user_id', $user->id)
                ->where('role', 'platform_admin')
                ->exists();
        });
    }
}
