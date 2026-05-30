<?php
// app/Models/StockOpnameDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class StockOpnameDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'status',
        'notes',
        'confirmed_at',
        'confirmed_by',
        'created_by',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────
    public function items()
    {
        return $this->hasMany(StockOpnameItem::class, 'document_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Static: generate document number ─────────────────────────────────────
    public static function generateNumber(): string
    {
        $date   = Carbon::now()->format('Ymd');
        $prefix = "OP-{$date}-";
        $last   = static::where('document_number', 'like', "{$prefix}%")
                        ->orderByDesc('document_number')
                        ->value('document_number');

        $seq = $last
            ? (int) substr($last, -3) + 1
            : 1;

        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'confirmed' => 'Dikonfirmasi',
            default     => $this->status,
        };
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getFilledItemsCountAttribute(): int
    {
        return $this->items()->whereNotNull('quantity_actual')->count();
    }
}