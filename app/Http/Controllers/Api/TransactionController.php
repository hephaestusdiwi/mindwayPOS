<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Product;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->active_outlet_id; // ← tambahan

        $query = Transaction::with('items.product')
                            ->where('outlet_id', $outletId) // ← tambahan
                            ->latest();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        return $query->paginate($request->get('per_page', 15));
    }

    public function show(Request $request, $id)
    {
        $outletId = $request->active_outlet_id; // ← tambahan

        $transaction = Transaction::with('items.product')
            ->where('outlet_id', $outletId) // ← tambahan (security: cegah akses transaksi outlet lain)
            ->findOrFail($id);

        return response()->json($transaction);
    }

    public function store(Request $request)
    {
        \Log::info('OUTLET ID:', ['outlet_id' => $request->active_outlet_id]);
        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|integer|min:1',
            'payment_method' => ['required', 'string', function($attr, $value, $fail) {
                $allowed  = ['cash', 'qris'];
                $prefixes = ['transfer_', 'ewallet_'];

                if (in_array($value, $allowed)) return;

                foreach ($prefixes as $prefix) {
                    if (str_starts_with($value, $prefix)) return;
                }

                $fail('Metode pembayaran tidak valid.');
            }],
            'cash_paid'          => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $total    = 0;
            $outletId = $request->active_outlet_id; // ← tambahan

            $transaction = Transaction::create([
                'user_id'        => auth()->id(),
                'outlet_id'      => $outletId, // ← tambahan
                'total_price'    => 0,
                'payment_method' => $request->payment_method,
            ]);

            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->stock < $item['qty']) {
                    throw new \Exception("Stok {$product->name} tidak mencukupi. Sisa: {$product->stock}");
                }

                $price    = $product->discount_price ?? $product->price;
                $subtotal = $price * $item['qty'];
                $total   += $subtotal;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id'     => $product->id,
                    'qty'            => $item['qty'],
                    'price'          => $price,
                ]);

                $product->decrement('stock', $item['qty']);
            }

            $transaction->update(['total_price' => $total]);

            DB::commit();

            return response()->json([
                'message' => 'Transaksi berhasil',
                'data'    => $transaction->load('items.product'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}