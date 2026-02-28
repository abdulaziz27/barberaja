<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;
    use HasUuids;

    public const PLAN_TYPE_FREE = 'free';
    public const PLAN_TYPE_PRO = 'pro';
    public const PLAN_TYPE_ENTERPRISE = 'enterprise';

    public const PLAN_STATUS_ACTIVE = 'active';
    public const PLAN_STATUS_SUSPENDED = 'suspended';

    /** Default fee per completed ticket for free plan (Rp). */
    public const DEFAULT_FEE_PER_TRANSACTION_FREE = 2000;

    /** Default monthly subscription for pro plan (Rp). */
    public const DEFAULT_SUBSCRIPTION_PRICE_PRO = 99000;

    protected $fillable = [
        'name',
        'subscription_status',
        'plan_type',
        'plan_status',
        'plan_started_at',
        'plan_expires_at',
        'custom_fee_per_transaction',
        'custom_subscription_price',
        'subscription_overdue_at',
    ];

    protected function casts(): array
    {
        return [
            'plan_started_at' => 'date',
            'plan_expires_at' => 'date',
            'custom_fee_per_transaction' => 'decimal:2',
            'custom_subscription_price' => 'decimal:2',
            'subscription_overdue_at' => 'datetime',
        ];
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function isPlanActive(): bool
    {
        return $this->plan_status === self::PLAN_STATUS_ACTIVE;
    }

    public function isPro(): bool
    {
        return $this->plan_type === self::PLAN_TYPE_PRO;
    }

    public function isFree(): bool
    {
        return $this->plan_type === self::PLAN_TYPE_FREE;
    }

    public function isEnterprise(): bool
    {
        return $this->plan_type === self::PLAN_TYPE_ENTERPRISE;
    }

    public function isSubscriptionOverdue(): bool
    {
        return $this->subscription_overdue_at !== null;
    }
}

