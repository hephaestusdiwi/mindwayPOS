<?php

namespace App\Services;

use App\Models\Product;

class StockAlertService
{
    public function getLowStockCount(): int
    {
        return Product::whereColumn('current_stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->where('is_active', true)
            ->count();
    }

    public function getReorderProducts()
    {
        return Product::with(['category'])
            ->whereColumn('current_stock', '<=', 'reorder_point')
            ->where('reorder_point', '>', 0)
            ->where('is_active', true)
            ->select(['id', 'name', 'sku', 'current_stock', 'min_stock', 'reorder_point', 'cost_price', 'category_id'])
            ->orderByRaw('(current_stock - reorder_point) ASC')
            ->get()
            ->map(function ($p) {
                $p->shortage     = max(0, $p->reorder_point - $p->current_stock);
                $p->status_level = match (true) {
                    $p->current_stock <= 0             => 'out_of_stock',
                    $p->current_stock <= $p->min_stock => 'critical',
                    default                            => 'low',
                };
                return $p;
            });
    }

    public function getSummary(): array
    {
        $products = Product::where('min_stock', '>', 0)->where('is_active', true)->get();

        return [
            'out_of_stock' => $products->where('current_stock', '<=', 0)->count(),
            'critical'     => $products->filter(fn($p) =>
                $p->current_stock > 0 && $p->current_stock <= $p->min_stock
            )->count(),
            'low'          => $products->filter(fn($p) =>
                $p->current_stock > $p->min_stock && $p->current_stock <= $p->reorder_point
            )->count(),
        ];
    }
}