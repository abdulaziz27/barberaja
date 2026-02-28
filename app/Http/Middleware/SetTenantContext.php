<?php

namespace App\Http\Middleware;

use App\Models\UserRole;
use App\Scopes\TenantScope;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Resolve current tenant for the request and set TenantContext.
     *
     * Strategy (phase 1, simple):
     * - If authenticated user with non-platform_admin role exists: use first user_role.tenant_id.
     * - Else, if X-Tenant-Id header present and valid: use that.
     *
     * Note: UserRole is queried without TenantScope because tenant is not set yet.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = null;

        if ($user = $request->user()) {
            $role = UserRole::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('user_id', $user->getKey())
                ->where('role', '!=', 'platform_admin')
                ->first();

            if ($role) {
                $tenantId = $role->tenant_id;
            }
        }

        if ($tenantId === null && $headerTenant = $request->header('X-Tenant-Id')) {
            $tenantId = $headerTenant;
        }

        TenantContext::set($tenantId);

        return $next($request);
    }
}

