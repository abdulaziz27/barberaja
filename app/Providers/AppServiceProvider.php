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

        // WhatsApp client: resolved based on WHATSAPP_DRIVER env variable.
        // Default: NullWhatsAppClient (logs only, no real send).
        $this->app->bind(
            \App\Contracts\WhatsAppClient::class,
            function ($app) {
                $driver = config('whatsapp.driver', 'null');
                return match ($driver) {
                    // Add real drivers here as they are implemented:
                    // 'fonnte' => new \App\Services\FonnteWhatsAppClient(config('whatsapp.fonnte.token')),
                    default => new \App\Services\NullWhatsAppClient(),
                };
            }
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
