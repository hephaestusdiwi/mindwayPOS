<?php
// app/Models/Supplier.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'contact_person', 'phone', 'email', 'address',
        'city', 'npwp', 'payment_terms', 'bank_name', 'bank_account',
        'bank_account_name', 'notes', 'status',
    ];

    // Auto-generate kode supplier: SUP-001
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->code)) {
                $last = static::withTrashed()->max('id') ?? 0;
                $model->code = 'SUP-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function purchaseOrders()    { return $this->hasMany(PurchaseOrder::class); }
    public function goodsReceipts()     { return $this->hasMany(GoodsReceipt::class); }
    public function purchaseInvoices()  { return $this->hasMany(PurchaseInvoice::class); }
    public function supplierReturns()   { return $this->hasMany(SupplierReturn::class); }

    public function getPaymentTermsLabelAttribute(): string
    {
        return match($this->payment_terms) {
            'cash'  => 'Tunai',
            'net7'  => 'Net 7 hari',
            'net14' => 'Net 14 hari',
            'net30' => 'Net 30 hari',
            'net60' => 'Net 60 hari',
            default => $this->payment_terms,
        };
    }
}