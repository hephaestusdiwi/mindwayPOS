<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'image',
        'price',
        'discount_price',
        'stock',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function transactionItems()
    {
        return $this->hasMany(transactionItems::class);
    }

    public function getImageUrlAttribute()
    {
        if (!$this->image) return null;
        return asset('storage/' . $this->image);
    }

    public function getEffectivePriceAttribute()
    {
        return $this->discount_price ?? $this->price;
    }

    protected $appends = ['image_url', 'effective_price'];
}