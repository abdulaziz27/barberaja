<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToTenant;
    use SoftDeletes;

    public const TYPE_SERVICE = 'service';
    public const TYPE_BUNDLE = 'bundle';
    public const TYPE_RETAIL = 'retail';

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

    /**
     * Items (child services) that belong to this bundle.
     */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_id');
    }

    /**
     * Bundle memberships: bundles that contain this product as a child.
     */
    public function bundleOf(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'product_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeServices($query)
    {
        return $query->where('type', self::TYPE_SERVICE);
    }

    public function scopeBundles($query)
    {
        return $query->where('type', self::TYPE_BUNDLE);
    }

    public function scopeRetail($query)
    {
        return $query->where('type', self::TYPE_RETAIL);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isBundle(): bool
    {
        return $this->type === self::TYPE_BUNDLE;
    }

    public function isService(): bool
    {
        return $this->type === self::TYPE_SERVICE;
    }

    public function isRetail(): bool
    {
        return $this->type === self::TYPE_RETAIL;
    }
}
