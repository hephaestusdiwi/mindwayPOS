<?php
// app/Models/StockOpnameItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'document_id',
        'product_id',
        'quantity_system',
        'quantity_actual',
        'notes',
    ];

    protected $casts = [
        'quantity_system' => 'integer',
        'quantity_actual' => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function document()
    {
        return $this->belongsTo(StockOpnameDocument::class, 'document_id');
    }

    // ── Accessor ──────────────────────────────────────────────────────────────
    public function getDiffAttribute(): ?int
    {
        if ($this->quantity_actual === null) return null;
        return $this->quantity_actual - $this->quantity_system;
    }
}