<?php

namespace App\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = TenantContext::id();

        if ($tenantId !== null && $builder->getModel()->getTable() !== 'tenants') {
            $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
        }
    }
}

