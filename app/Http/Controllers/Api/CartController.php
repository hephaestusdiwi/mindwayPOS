<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;


class CartController extends Controller
{
    public function index()
    {
        return Cart::with('product')
            ->where('user_id', auth()->id())
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1'
        ]);

        $cart = Cart::where('user_id', auth()->id())
            ->where('product_id', $request->product_id)
            ->first();

        if ($cart) {
            $cart->increment('qty', $request->qty);
        } else {
            Cart::create([
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
                'qty' => $request->qty
            ]);
        }

        return response()->json(['message' => 'Added to cart']);
    }

    public function destroy($id)
    {
        Cart::where('user_id', auth()->id())
            ->where('id', $id)
            ->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'required|string'
        ]);

        $cart = Cart::where('user_id', auth()->id())->get();

        if ($cart->isEmpty()) {
            return response()->json(['message' => 'Cart kosong'], 400);
        }

        DB::beginTransaction();

        try {
            $total = 0;

            $transaction = Transaction::create([
                'user_id' => auth()->id(),
                'total_price' => 0,
                'status' => 'paid',
            ]);

            foreach ($cart as $item) {
                
                $product = Product::findOrFail($item->product_id);

                if ($product->stock < $item->qty) {
                    throw new \Exception('Stock tidak cukup');
                }

                $subtotal = $product->price * $item->qty;
                $total += $subtotal;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'qty' => $item->qty,
                    'price' => $product->price
                ]);

                $product->decrement('stock', $item->qty);
            }

            if ($request->amount_paid < $total) {
                throw new \Exception('Uang tidak cukup');
            }

            $change = $request->amount_paid - $total;

            $transaction->update([
                'total_price' => $total
            ]);

            Payment::create([
                'transaction_id' => $transaction->id,
                'method' => $request->payment_method,
                'amount_paid' => $request->amount_paid,
                'change' => $change,
                'status' => 'paid',
                'paid_at' => now()
            ]);

            Cart::where('user_id', auth()->id())->delete();

            DB::commit();

            return response()->json([
                'message' => 'Checkout berhasil',
                'total' => $total,
                'paid' => $request->amount_paid,
                'change' => $change,
                'data' => $transaction->load('items.product')
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
