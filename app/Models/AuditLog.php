<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const ACTION_WITHDRAWAL_REQUEST = 'withdrawal_request';
    public const ACTION_WITHDRAWAL_SUCCESS = 'withdrawal_success';
    public const ACTION_WITHDRAWAL_FAILED = 'withdrawal_failed';
    public const ACTION_SUBSCRIPTION_DEDUCTION = 'subscription_deduction';
    public const ACTION_SUBSCRIPTION_DEDUCTION_OVERDUE = 'subscription_deduction_overdue';
    public const ACTION_PLAN_CHANGE = 'plan_change';
    public const ACTION_ROLE_CHANGE = 'role_change';
    public const ACTION_STOCK_DEDUCTION = 'stock_deduction';
    public const ACTION_STOCK_INCREASE = 'stock_increase';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'action',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'meta',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Append-only log for sensitive actions.
     */
    public static function log(
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $meta = null
    ): self {
        return self::query()->create([
            'user_id' => Auth::id(),
            'tenant_id' => $meta['tenant_id'] ?? null,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'meta' => $meta,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
