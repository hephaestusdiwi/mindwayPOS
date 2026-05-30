<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockOpnameDocument;
use App\Models\StockOpnameItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    /**
     * GET /auth/inventory/opname
     * List semua dokumen opname
     */
    public function index(Request $request)
    {
        $outletId = $request->active_outlet_id;

        $docs = StockOpnameDocument::with(['createdBy:id,name', 'confirmedBy:id,name'])
            ->withCount('items')
            ->where('outlet_id', $outletId) // ✅ WAJIB
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json($docs);
    }

    /**
     * POST /auth/inventory/opname
     * Buat dokumen opname baru (otomatis load semua produk aktif)
     */
    public function store(Request $request)
    {
        $request->validate([
            'notes'       => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        DB::beginTransaction();
        try {
            // Buat dokumen
            $document = StockOpnameDocument::create([
                'outlet_id'      => $request->active_outlet_id, // ✅ TAMBAH
                'document_number'=> StockOpnameDocument::generateNumber(),
                'status'         => 'draft',
                'notes'          => $request->notes,
                'created_by'     => auth()->id(),
            ]);

            // Load produk aktif sebagai items
            $query = Product::where('is_active', true)
                ->where('outlet_id', $request->active_outlet_id) // ✅ WAJIB
                ->select('id', 'stock');

            if ($request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            $items = $products->map(fn($p) => [
                'document_id'     => $document->id,
                'product_id'      => $p->id,
                'quantity_system' => $p->stock,
                'quantity_actual' => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ])->toArray();

            StockOpnameItem::insert($items);

            DB::commit();

            return response()->json(
                $document->load(['items.product:id,name,sku,image', 'createdBy:id,name']),
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /auth/inventory/opname/{id}
     * Detail dokumen + semua items
     */
    public function show(Request $request, StockOpnameDocument $opname)
    {
        if ($opname->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $opname->load([
            'items.product:id,name,sku,image,stock',
            'createdBy:id,name',
            'confirmedBy:id,name',
        ]);

        $opname->items->each(function ($item) {
            if ($item->product && $item->product->image) {
                $item->product->image_url = \Illuminate\Support\Facades\Storage::disk('public')->url($item->product->image);
            } else {
                $item->product?->setAttribute('image_url', null);
            }
            $item->diff = $item->diff;
        });

        return response()->json($opname);
    }

    /**
     * PUT /auth/inventory/opname/{id}/items
     * Update quantity_actual untuk satu atau banyak item
     */
    public function updateItems(Request $request, StockOpnameDocument $opname)
    {
        if ($opname->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($opname->status === 'confirmed') {
            return response()->json(['message' => 'Dokumen sudah dikonfirmasi, tidak bisa diedit.'], 422);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:stock_opname_items,id',
            'items.*.quantity_actual' => 'nullable|integer|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        foreach ($request->items as $itemData) {
            StockOpnameItem::where('id', $itemData['id'])
                ->where('document_id', $opname->id)
                ->update([
                    'quantity_actual' => $itemData['quantity_actual'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
        }

        return response()->json(['message' => 'Item berhasil diperbarui.']);
    }

    /**
     * POST /auth/inventory/opname/{id}/confirm
     * Konfirmasi dokumen → update stok semua produk
     */
    public function confirm(Request $request, StockOpnameDocument $opname)
    {
        if ($opname->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($opname->status === 'confirmed') {
            return response()->json(['message' => 'Dokumen sudah dikonfirmasi.'], 422);
        }

        $items = $opname->items()->with('product')->whereNotNull('quantity_actual')->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Belum ada stok aktual yang diisi.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $product = $item->product;

                // ✅ proteksi product outlet
                if ($product->outlet_id !== $request->active_outlet_id) {
                    abort(403, 'Unauthorized');
                }

                $before  = $product->stock;
                $after   = $item->quantity_actual;
                $diff    = $after - $before;

                if ($diff === 0) continue;

                $product->update(['stock' => $after]);

                StockAdjustment::create([
                    'outlet_id'       => $request->active_outlet_id, // ✅ TAMBAH
                    'product_id'      => $product->id,
                    'user_id'         => auth()->id(),
                    'type'            => 'opname',
                    'quantity_before' => $before,
                    'quantity_after'  => $after,
                    'quantity_diff'   => $diff,
                    'notes'           => $item->notes,
                    'reference'       => $opname->document_number,
                ]);
            }

            $opname->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stok opname berhasil dikonfirmasi.',
                'data'    => $opname->fresh(['confirmedBy:id,name']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /auth/inventory/opname/{id}
     * Hapus dokumen draft
     */
    public function destroy(Request $request, StockOpnameDocument $opname)
    {
        if ($opname->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($opname->status === 'confirmed') {
            return response()->json(['message' => 'Dokumen yang sudah dikonfirmasi tidak bisa dihapus.'], 422);
        }

        $opname->delete();

        return response()->json(['message' => 'Dokumen berhasil dihapus.']);
    }
}