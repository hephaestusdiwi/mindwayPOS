<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // relasi ke produk
    public function products ()
    {
        return $this->hasMany(Product::class);
    }

    // accessor : jumlah produk aktif
    public function getProductsCountAttribute(): int
    {
        return $this->products()->count();
    }
}