<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerEntry extends Model
{
    use HasUuids;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reference_id',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \RuntimeException('Ledger is immutable (insert-only).');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Ledger is immutable (insert-only).');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LedgerLine::class, 'entry_id');
    }
}

