<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $today    = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $outletId = $request->active_outlet_id;

        // ── Penjualan hari ini ────────────────────────────────────
        $todaySales     = (float) Transaction::where('outlet_id', $outletId)
                                             ->whereDate('created_at', $today)
                                             ->sum('total_price');

        $yesterdaySales = (float) Transaction::where('outlet_id', $outletId)
                                             ->whereDate('created_at', Carbon::yesterday())
                                             ->sum('total_price');

        $salesGrowth = $yesterdaySales > 0
            ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1)
            : 0;

        // ── Transaksi ─────────────────────────────────────────────
        $todayTransactions     = Transaction::where('outlet_id', $outletId)
                                            ->whereDate('created_at', $today)
                                            ->count();

        $yesterdayTransactions = Transaction::where('outlet_id', $outletId)
                                            ->whereDate('created_at', Carbon::yesterday())
                                            ->count();

        $transactionGrowth = $yesterdayTransactions > 0
            ? round((($todayTransactions - $yesterdayTransactions) / $yesterdayTransactions) * 100, 1)
            : 0;

        // ── Pelanggan ─────────────────────────────────────────────
        $totalCustomers    = User::count();
        $newCustomersToday = User::whereDate('created_at', $today)->count();

        // ── Grafik mingguan ───────────────────────────────────────
        $weeklySales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $weeklySales[] = [
                'date'  => $date->locale('id')->isoFormat('ddd'),
                'total' => (float) Transaction::where('outlet_id', $outletId)
                                              ->whereDate('created_at', $date)
                                              ->sum('total_price'),
                'count' => Transaction::where('outlet_id', $outletId)
                                      ->whereDate('created_at', $date)
                                      ->count(),
            ];
        }

        // ── Produk terlaris ───────────────────────────────────────
        // Pakai query manual agar tidak bergantung pada relasi transactionItems
         $topProducts = Product::selectRaw('
                products.id,
                products.name,
                products.price,
                products.stock,
                COALESCE(SUM(transaction_items.qty), 0) as sold_count
            ')
            ->leftJoin('transaction_items', 'products.id', '=', 'transaction_items.product_id')
            ->leftJoin('transactions', function ($join) use ($thisWeek, $outletId) {
                $join->on('transaction_items.transaction_id', '=', 'transactions.id')
                     ->where('transactions.created_at', '>=', $thisWeek)
                     ->where('transactions.outlet_id', '=', $outletId); // ← filter outlet
            })
            ->groupBy('products.id', 'products.name', 'products.price', 'products.stock')
            ->orderByDesc('sold_count')
            ->limit(5)
            ->get();

        // ── Stok menipis ──────────────────────────────────────────
        $lowStock = Product::where('stock', '<=', 10)
            ->orderBy('stock')
            ->limit(8)
            ->get(['id', 'name', 'stock', 'price']);

        return response()->json([
            'stats' => [
                'today_sales'         => $todaySales,
                'sales_growth'        => $salesGrowth,
                'today_transactions'  => $todayTransactions,
                'transaction_growth'  => $transactionGrowth,
                'total_customers'     => $totalCustomers,
                'new_customers_today' => $newCustomersToday,
                'total_products'      => Product::count(),
            ],
            'weekly_sales' => $weeklySales,
            'top_products' => $topProducts,
            'low_stock'    => $lowStock,
        ]);
    }
}