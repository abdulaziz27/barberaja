<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToTenant;
    use SoftDeletes;

    public const TYPE_SERVICE = 'service';

    public const COMMISSION_NONE = 'none';
    public const COMMISSION_PERCENTAGE = 'percentage';
    public const COMMISSION_FIXED = 'fixed';

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'name',
        'type',
        'price',
        'duration_minutes',
        'stock_qty',
        'commission_type',
        'commission_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function scopeServices($query)
    {
        return $query->where('type', self::TYPE_SERVICE);
    }
}
