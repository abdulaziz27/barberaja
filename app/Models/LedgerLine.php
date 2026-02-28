<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerLine extends Model
{
    use HasUuids;

    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'entry_id',
        'account',
        'direction',
        'amount',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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

    public function entry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'entry_id');
    }
}

