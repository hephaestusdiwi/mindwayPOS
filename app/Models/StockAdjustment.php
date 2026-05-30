<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'adj_number',
        'created_by',
        'approved_by',
        'adj_date',
        'type',
        'reason',
        'status',
        'notes',
        'confirmed_at',
    ];

    protected $casts = [
        'adj_date'     => 'date',
        'confirmed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->adj_number)) {
                $year  = now()->year;
                $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;
                $model->adj_number = 'ADJ-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}