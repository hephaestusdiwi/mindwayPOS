<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function __construct(private StockLedgerService $ledger) {}

    /**
     * GET /auth/stock-adjustments
     */
    public function index(Request $request)
    {
        $outletId = $request->active_outlet_id;

        $query = StockAdjustment::with(['createdBy'])
                ->withCount('items')
                ->where('outlet_id', $outletId) 
                ->latest();

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('from'))   $query->whereDate('adj_date', '>=', $request->from);
        if ($request->filled('to'))     $query->whereDate('adj_date', '<=', $request->to);

        return response()->json($query->paginate(20));
    }

    /**
     * POST /auth/stock-adjustments
     */
    public function store(Request $request)
    {
        $request->validate([
            'adj_date'            => 'required|date',
            'type'                => 'required|in:addition,reduction,recount',
            'reason'              => 'required|string|max:200',
            'notes'               => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.qty_system'  => 'required|numeric|min:0',
            'items.*.qty_actual'  => 'required|numeric|min:0',
            'items.*.unit_cost'   => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {
            $adj = StockAdjustment::create([
                'outlet_id'  => $request->active_outlet_id,
                'created_by' => Auth::id(),
                'adj_date'   => $request->adj_date,
                'type'       => $request->type,
                'reason'     => $request->reason,
                'status'     => 'draft',
                'notes'      => $request->notes,
            ]);

            foreach ($request->items as $item) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adj->id,
                    'product_id'          => $item['product_id'],
                    'qty_system'          => $item['qty_system'],
                    'qty_actual'          => $item['qty_actual'],
                    'qty_difference'      => $item['qty_actual'] - $item['qty_system'],
                    'unit_cost'           => $item['unit_cost'] ?? 0,
                ]);
            }

            return response()->json($adj->load('items.product'), 201);
        });
    }

    /**
     * GET /auth/stock-adjustments/{id}
     */
    public function show(Request $request, StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(
            $stockAdjustment->load('items.product', 'createdBy', 'approvedBy')
        );
    }

    /**
     * PATCH /auth/stock-adjustments/{id}/confirm
     */
    public function confirm(Request $request, StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($stockAdjustment->status !== 'draft') {
            return response()->json(['message' => 'Hanya draft yang bisa dikonfirmasi.'], 422);
        }

        DB::transaction(function () use ($stockAdjustment) {
            $stockAdjustment->load('items');

            foreach ($stockAdjustment->items as $item) {
                if ($item->qty_difference == 0) continue;

                $this->ledger->recordAdjustment(
                    productId:        $item->product_id,
                    qtyDifference:    $item->qty_difference,
                    unitCost:         $item->unit_cost,
                    adjustmentId:     $stockAdjustment->id,
                    adjustmentNumber: $stockAdjustment->adj_number,
                    notes:            $stockAdjustment->reason,
                    outletId:         $stockAdjustment->outlet_id // 🔥 WAJIB
                );
            }

            $stockAdjustment->update([
                'status'       => 'confirmed',
                'approved_by'  => Auth::id(),
                'confirmed_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Penyesuaian stok berhasil dikonfirmasi.']);
    }

    /**
     * PATCH /auth/stock-adjustments/{id}/cancel
     */
    public function cancel(Request $request, StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($stockAdjustment->status !== 'draft') {
            return response()->json(['message' => 'Hanya draft yang bisa dibatalkan.'], 422);
        }

        $stockAdjustment->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Penyesuaian dibatalkan.']);
    }

    /**
     * DELETE /auth/stock-adjustments/{id}
     */
    public function destroy(Request $request, StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($stockAdjustment->status !== 'draft') {
            return response()->json(['message' => 'Hanya draft yang bisa dihapus.'], 422);
        }

        $stockAdjustment->items()->delete();
        $stockAdjustment->delete();

        return response()->json(['message' => 'Dokumen dihapus.']);
    }
}