<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrderItem;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GoodsReceiptController extends Controller
{
    public function __construct(private StockLedgerService $ledger) {}

    public function index(Request $request)
    {
        $outletId = $request->active_outlet_id;

        $query = GoodsReceipt::with(['supplier', 'purchaseOrder', 'receivedBy'])
            ->where('outlet_id', $outletId) 
            ->latest();

        $query = GoodsReceipt::with(['supplier', 'purchaseOrder', 'receivedBy'])
            ->latest();

        if ($request->filled('supplier_id'))
            $query->where('supplier_id', $request->supplier_id);
        if ($request->filled('status'))
            $query->where('status', $request->status);
        if ($request->filled('from'))
            $query->whereDate('received_date', '>=', $request->from);
        if ($request->filled('to'))
            $query->whereDate('received_date', '<=', $request->to);
        if ($request->filled('search'))
            $query->where('grn_number', 'like', "%{$request->search}%");

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'             => 'required|exists:suppliers,id',
            'purchase_order_id'       => 'nullable|exists:purchase_orders,id',
            'received_date'           => 'required|date',
            'supplier_delivery_note'  => 'nullable|string|max:100',
            'notes'                   => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.uom_id'          => 'nullable|exists:units_of_measure,id',
            'items.*.qty_received'    => 'required|numeric|min:0.0001',
            'items.*.unit_cost'       => 'required|numeric|min:0',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
        ]);

        return DB::transaction(function () use ($request) {
            $grn = GoodsReceipt::create([
                'outlet_id'              => $request->active_outlet_id,
                'supplier_id'            => $request->supplier_id,
                'purchase_order_id'      => $request->purchase_order_id,
                'received_by'            => Auth::id(),
                'received_date'          => $request->received_date,
                'supplier_delivery_note' => $request->supplier_delivery_note,
                'status'                 => 'draft',
                'notes'                  => $request->notes,
            ]);

            $totalCost = 0;
            foreach ($request->items as $item) {
                $qtyBase = $item['qty_received']; // TODO: konversi UoM jika berbeda

                $grnItem = GoodsReceiptItem::create([
                    'goods_receipt_id'       => $grn->id,
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'product_id'             => $item['product_id'],
                    'uom_id'                 => $item['uom_id'] ?? null,
                    'qty_received'           => $item['qty_received'],
                    'qty_in_base_uom'        => $qtyBase,
                    'unit_cost'              => $item['unit_cost'],
                    'total_cost'             => $item['qty_received'] * $item['unit_cost'],
                    'notes'                  => $item['notes'] ?? null,
                ]);
                $totalCost += $grnItem->total_cost;
            }

            $grn->update(['total_cost' => $totalCost]);

            return response()->json($grn->load('items.product', 'supplier'), 201);
        });
    }

    public function show(Request $request, GoodsReceipt $goodsReceipt)
    {
        if ($goodsReceipt->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(
            $goodsReceipt->load('supplier', 'purchaseOrder.items', 'receivedBy', 'items.product', 'items.uom')
        );
    }

    /**
     * Konfirmasi GRN → stok masuk, update PO qty_received
     */
    public function confirm(GoodsReceipt $goodsReceipt)
    {
        if ($goodsReceipt->status !== 'draft') {
            return response()->json(['message' => 'Hanya GRN draft yang bisa dikonfirmasi.'], 422);
        }

        DB::transaction(function () use ($goodsReceipt) {
            $goodsReceipt->load('items');

            foreach ($goodsReceipt->items as $item) {
                // Update stok via StockLedgerService
                $this->ledger->recordReceipt(
                    productId:  $item->product_id,
                    qty:        $item->qty_in_base_uom,
                    unitCost:   $item->unit_cost,
                    refType:    'GoodsReceipt',
                    refId:      $goodsReceipt->id,
                    refNumber:  $goodsReceipt->grn_number,
                    notes:      'Penerimaan barang dari ' . $goodsReceipt->supplier->name,
                    outletId:   $goodsReceipt->outlet_id
                );

                // Update harga pokok produk (average cost sederhana)
                $item->product->update(['cost_price' => $item->unit_cost]);

                // Update qty_received di PO item
                if ($item->purchase_order_item_id) {
                    $poItem = PurchaseOrderItem::find($item->purchase_order_item_id);
                    if ($poItem) {
                        $poItem->increment('qty_received', $item->qty_received);
                    }
                }
            }

            // Update status PO jika semua item sudah diterima
            if ($goodsReceipt->purchaseOrder) {
                $po = $goodsReceipt->purchaseOrder->fresh('items');
                $newStatus = $po->is_fully_received ? 'received' : 'partial';
                $po->update(['status' => $newStatus]);
            }

            $goodsReceipt->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);
        });

        return response()->json([
            'message'      => 'GRN berhasil dikonfirmasi. Stok telah diperbarui.',
            'goods_receipt' => $goodsReceipt->fresh('items.product', 'supplier'),
        ]);
    }

    /**
     * Batalkan GRN confirmed → reversal stock ledger
     */
    public function cancel(Request $request, GoodsReceipt $goodsReceipt)
    {
        if ($goodsReceipt->outlet_id !== $request->active_outlet_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate(['reason' => 'required|string|max:200']);

        if ($goodsReceipt->status === 'cancelled') {
            return response()->json(['message' => 'GRN sudah dibatalkan.'], 422);
        }

        DB::transaction(function () use ($goodsReceipt, $request) {

            if ($goodsReceipt->status === 'confirmed') {

                $this->ledger->reverseEntries(
                    'GoodsReceipt',
                    $goodsReceipt->id,
                    'Pembatalan GRN: ' . $request->reason
                );

                foreach ($goodsReceipt->items as $item) {
                    if ($item->purchase_order_item_id) {
                        $poItem = PurchaseOrderItem::find($item->purchase_order_item_id);
                        if ($poItem) {
                            $poItem->decrement('qty_received', $item->qty_received);
                        }
                    }
                }

                if ($goodsReceipt->purchaseOrder) {
                    $goodsReceipt->purchaseOrder->update(['status' => 'approved']);
                }
            }

            $goodsReceipt->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'GRN berhasil dibatalkan.']);
    }
}