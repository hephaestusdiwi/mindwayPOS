<?php

// ============================================================
// app/Http/Controllers/Api/PurchaseOrderController.php
// ============================================================
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
 
class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->active_outlet_id;

        $query = PurchaseOrder::with(['supplier', 'createdBy'])
            ->where('outlet_id', $outletId)
            ->latest();
            
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('supplier_id')) $query->where('supplier_id', $request->supplier_id);
        if ($request->filled('search'))      $query->where('po_number', 'like', "%{$request->search}%");
        if ($request->filled('from'))        $query->whereDate('po_date', '>=', $request->from);
        if ($request->filled('to'))          $query->whereDate('po_date', '<=', $request->to);
        return response()->json($query->paginate(20));
    }
 
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'          => 'required|exists:suppliers,id',
            'po_date'              => 'required|date',
            'expected_date'        => 'nullable|date|after_or_equal:po_date',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.uom_id'       => 'nullable|exists:units_of_measure,id',
            'items.*.qty_ordered'  => 'required|numeric|min:0.0001',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);
 
        return DB::transaction(function () use ($request) {
            $po = PurchaseOrder::create([

                'outlet_id'     => $request->active_outlet_id,
                'supplier_id'   => $request->supplier_id,
                'created_by'    => Auth::id(),
                'po_date'       => $request->po_date,
                'expected_date' => $request->expected_date,
                'status'        => 'draft',
                'notes'         => $request->notes,
            ]);
 
            $subtotal = 0;
            foreach ($request->items as $item) {
                $disc    = $item['discount_percent'] ?? 0;
                $total   = $item['qty_ordered'] * $item['unit_price'] * (1 - $disc / 100);
                $subtotal += $total;
 
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product_id'],
                    'uom_id'            => $item['uom_id'] ?? null,
                    'qty_ordered'       => $item['qty_ordered'],
                    'qty_received'      => 0,
                    'unit_price'        => $item['unit_price'],
                    'discount_percent'  => $disc,
                    'total_price'       => $total,
                    'notes'             => $item['notes'] ?? null,
                ]);
            }
 
            $po->update(['subtotal' => $subtotal, 'total_amount' => $subtotal]);
 
            return response()->json($po->load('items.product', 'supplier'), 201);
        });
    }
 
    public function show(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(
            $purchaseOrder->load('supplier', 'createdBy', 'approvedBy', 'items.product', 'items.uom', 'goodsReceipts')
        );
    }
 
    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($purchaseOrder->status !== 'draft') {
            return response()->json(['message' => 'Hanya PO draft yang bisa disetujui.'], 422);
        }

        $purchaseOrder->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json(['message' => 'PO disetujui.', 'purchase_order' => $purchaseOrder]);
    }
 
    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate(['reason' => 'required|string']);
        if (in_array($purchaseOrder->status, ['received', 'cancelled'])) {
            return response()->json(['message' => 'PO tidak dapat dibatalkan.'], 422);
        }
        
        $purchaseOrder->update(['status' => 'cancelled']);
        return response()->json(['message' => 'PO dibatalkan.']);
    }
 
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($purchaseOrder->status !== 'draft') {
            return response()->json(['message' => 'Hanya PO draft yang bisa dihapus.'], 422);
        }

        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();
        return response()->json(['message' => 'PO dihapus.']);
    }
}