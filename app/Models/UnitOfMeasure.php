<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitOfMeasure extends Model
{
    protected $table = 'units_of_measure';
    protected $fillable = ['name', 'symbol', 'is_base', 'status'];
    protected $casts = ['is_base' => 'boolean'];

    public function products()
    {
        return $this->hasMany(Product::class, 'uom_id');
    }

    public function conversionsFrom()
    {
        return $this->hasMany(UomConversion::class, 'from_uom_id');
    }

    public function conversionsTo()
    {
        return $this->hasMany(UomConversion::class, 'to_uom_id');
    }
}