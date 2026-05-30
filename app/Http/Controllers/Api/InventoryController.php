<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    // GET API PRODUK
    // DAFTAR PRODUK UNTUK DI STOCK OPNAME
    public function products(Request $request) 
    {
        $outletId = $request->active_outlet_id;

        $query = Product::with('category')
            ->where('outlet_id', $outletId)
            ->select('id', 'name', 'sku', 'image', 'stock', 'min_stock', 'category_id', 'is_active');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->low_stock === 'true') {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        $products = $query->orderBy('name')->paginate($request->per_page ?? 20);

        $products->getCollection()->transform(function ($p) {
            $p->image_url = $p->image
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($p->image)
                : null;
            $p->is_low_stock = $p->stock <= $p->min_stock;
            return $p;
        });

        return response()->json($products);
    }

    // Simpan hasil stock opname
    public function opname(Request $request)
    {
        $outletId = $request->active_outlet_id;

        $request->validate([
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.actual_qty'  => 'required|integer|min:0',
            'items.*.notes'       => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $results = [];

            foreach ($request->items as $itemData) {
                $product = Product::where('outlet_id', $outletId)
                    ->findOrFail($itemData['product_id']);
                $before  = $product->stock;
                $after   = $itemData['actual_qty'];
                $diff    = $after - $before;

                if ($diff === 0) continue;

                $product->update(['stock' => $after]);

                $adjustment = StockAdjustment::create([
                    'outlet_id'         => $outletId,
                    'product_id'        => $product->id,
                    'user_id'           => auth()->id(),
                    'type'              => 'opname',
                    'quantity_before'   => $before,
                    'quantity_after'    => $after,
                    'quantity_diff'     => $diff,
                    'notes'             => $itemp['notes'] ?? null,
                ]);

                $results[] = $adjustment->load('product:id,name,sku');
            }

            DB::commit();

            return response()->json([
                'message'   => count($results), ' produk berhasil disesuaikan',
                'data'      => $results,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function mutations(Request $request)
    {
        $outletId = $request->active_outlet_id;

        $query = StockAdjustment::with([
            'product:id,name,sku',
            'user:id,name',
        ])
        ->where('outlet_id', $outletId)
        ->latest();

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $mutations = $query->paginate($request->per_page ?? 20);

        return response()->json($mutations);
    }

    public function updateMinStock(Request $request, Product $product)
    {
        if ($product->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'min_stock' => 'required|integer|min:0',
        ]);

        $product->update(['min_stock' => $request->min_stock]);

        return response()->json($product);
    }

    public function summary(Request $request) 
    {
        $outletId = $request->active_outlet_id;

        return response()->json([
            'total_products'    => Product::where('outlet_id', $outletId)->count(),
            'low_stock_count'   => Product::where('outlet_id', $outletId)
                                    ->whereColumn('stock', '<=', 'min_stock')->count(),
            'out_of_stock'      => Product::where('outlet_id', $outletId)
                                    ->where('stock', 0)->count(),
            'total_adjustments' => StockAdjustment::where('outlet_id', $outletId)
                                    ->whereDate('created_at', today())->count(), // 🔥 FIX TYPO
        ]);
    }
}
