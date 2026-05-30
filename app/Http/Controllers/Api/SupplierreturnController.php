<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierReturnController extends Controller
{
    public function __construct(private StockLedgerService $ledger) {}

    public function index(Request $request)
    {
        $query = SupplierReturn::with(['supplier', 'createdBy'])->latest();

        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('supplier_id')) $query->where('supplier_id', $request->supplier_id);

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'         => 'required|exists:suppliers,id',
            'goods_receipt_id'    => 'nullable|exists:goods_receipts,id',
            'return_date'         => 'required|date',
            'reason'              => 'required|string|max:200',
            'notes'               => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.qty_returned'=> 'required|numeric|min:0.0001',
            'items.*.unit_cost'   => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {
            $return = SupplierReturn::create([
                'supplier_id'      => $request->supplier_id,
                'goods_receipt_id' => $request->goods_receipt_id,
                'created_by'       => Auth::id(),
                'return_date'      => $request->return_date,
                'reason'           => $request->reason,
                'status'           => 'draft',
                'notes'            => $request->notes,
            ]);

            $total = 0;
            foreach ($request->items as $item) {
                $itemTotal = $item['qty_returned'] * $item['unit_cost'];
                $total += $itemTotal;
                SupplierReturnItem::create([
                    'supplier_return_id' => $return->id,
                    'product_id'         => $item['product_id'],
                    'qty_returned'       => $item['qty_returned'],
                    'unit_cost'          => $item['unit_cost'],
                    'total_amount'       => $itemTotal,
                ]);
            }

            $return->update(['total_amount' => $total]);

            return response()->json($return->load('items.product', 'supplier'), 201);
        });
    }

    public function show(SupplierReturn $supplierReturn)
    {
        return response()->json($supplierReturn->load('supplier', 'goodsReceipt', 'items.product', 'createdBy'));
    }

    public function confirm(SupplierReturn $supplierReturn)
    {
        if ($supplierReturn->status !== 'draft') {
            return response()->json(['message' => 'Hanya draft yang bisa dikonfirmasi.'], 422);
        }

        DB::transaction(function () use ($supplierReturn) {
            $supplierReturn->load('items');

            foreach ($supplierReturn->items as $item) {
                $this->ledger->recordSupplierReturn(
                    productId:    $item->product_id,
                    qty:          $item->qty_returned,
                    unitCost:     $item->unit_cost,
                    returnId:     $supplierReturn->id,
                    returnNumber: $supplierReturn->return_number,
                    notes:        $supplierReturn->reason,
                );
            }

            $supplierReturn->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Retur dikonfirmasi. Stok dikurangi.']);
    }

    public function destroy(SupplierReturn $supplierReturn)
    {
        if ($supplierReturn->status !== 'draft') {
            return response()->json(['message' => 'Hanya draft yang bisa dihapus.'], 422);
        }
        $supplierReturn->items()->delete();
        $supplierReturn->delete();
        return response()->json(['message' => 'Retur dihapus.']);
    }
}