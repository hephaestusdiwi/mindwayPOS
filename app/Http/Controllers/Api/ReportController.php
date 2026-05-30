<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->get('period', 'weekly');
        $date = Carbon::parse($request->get('date', now()));

        $currentTrxCount  = 0;
        $previousTrxCount = 0;
        $currentRevenue   = 0;
        $previousRevenue  = 0;
        $revenueGrowth    = 0;
        $trxGrowth        = 0;
        $start            = Carbon::now()->startOfWeek();
        $end              = Carbon::now()->endOfWeek();
        $prevStart        = Carbon::now()->subWeek()->startOfWeek();
        $prevEnd          = Carbon::now()->subWeek()->endOfWeek();

        switch ($period) {
            case 'daily':
                $start = $date->copy()->startOfDay();
                $end = $date->copy()->endOfDay();
                $prevStart = $date->copy()->subDay()->startOfDay();
                $prevEnd   = $date->copy()->subDay()->endOfDay();
                break;
            case 'monthly':
                $start = $date->copy()->startOfMonth();
                $end   = $date->copy()->endOfMonth();
                $prevStart = $date->copy()->subMonth()->startOfMonth();
                $prevEnd   = $date->copy()->subMonth()->endOfMonth();
                break;
            default:
                $start = $date->copy()->startOfWeek();
                $end   = $date->copy()->endOfWeek();
                $prevStart = $date->copy()->subWeek()->startOfWeek();
                $prevEnd   = $date->copy()->subWeek()->endOfWeek();
        }

        $currentRevenue = (float) Transaction::whereBetween('created_at', [$start, $end])->sum('total_price');
        $previousRevenue = (float) Transaction::whereBetween('created_at', [$prevStart, $prevEnd])->sum('total_price');
        $revenueGrowth = $previousRevenue > 0
            ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
            : 0;

        $currentTrxCount  = Transaction::whereBetween('created_at', [$start, $end])->count();
        $previousTrxCount = Transaction::whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $trxGrowth        = $previousTrxCount > 0
            ? round((($currentTrxCount - $previousTrxCount) / $previousTrxCount) * 100, 1)
            : 0;

        $avgTransaction = $currentTrxCount > 0
            ? round($currentRevenue / $currentTrxCount)
            : 0;

        $salesChart = $this->buildSalesChart($period, $start, $end);

        $topProducts = Product::selectRaw('
            products.id,
            products.name,
            products.price,
            SUM(transaction_items.qty) as total_qty,
            SUM(transaction_items.qty * transaction_items.price) as total_revenue
        ')
        ->join('transaction_items', 'products.id', '=', 'transaction_items.product_id')
        ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
        ->whereBetween('transactions.created_at', [$start, $end])
        ->groupBy('products.id', 'products.name', 'products.price')
        ->orderByDesc('total_qty')
        ->limit(8)
        ->get();

        $paymentMethods = Transaction::whereBetween('created_at', [$start, $end])
        ->selectRaw('payment_method, COUNT(*) as count, SUM(total_price) as total')
        ->groupBy('payment_method')
        ->get();

        
        $peakHours = Transaction::whereBetween('created_at', [$start, $end])
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count, SUM(total_price) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        $peakHoursChart = [];
        for ($h = 7; $h <= 22; $h++) {
            $peakHoursChart[] = [
                'hour'  => $h . ':00',
                'count' => $peakHours->get($h)->count ?? 0,
                'total' => (float) ($peakHours->get($h)?->total ?? 0),
            ];
        }
        
        return response()->json([
            'summary' => [
                'current_revenue'  => $currentRevenue,
                'previous_revenue' => $previousRevenue,
                'revenue_growth'   => $revenueGrowth,
                'current_trx'      => $currentTrxCount,
                'previous_trx'     => $previousTrxCount,
                'trx_growth'       => $trxGrowth,
                'avg_transaction'  => $avgTransaction,
            ],
            'sales_chart'   => $salesChart,
            'top_products'  => $topProducts,
            'payment_methods' => $paymentMethods,
            'peak_hours'      => $peakHoursChart,
            'period'          => $period,
            'start'           => $start->toDateTimeString(),
            'end'             => $end->toDateTimeString(),
        ]);
    }

    private function buildSalesChart(string $period, Carbon $start, Carbon $end): array
    {
        $chart = [];

        if ($period === 'daily') {
            // per jam
            for ($h =0; $h < 24; $h++) {
                $hourStart = $start->copy()->setHour($h)->startOfHour();
                $hourEnd   = $start->copy()->setHour($h)->endOfHour();
                $chart []  =  [
                    'label' => $h . ':00',
                    'total' => (float) Transaction::whereBetween('created_at', [$hourStart, $hourEnd])->count(),
                ];
            }
        } elseif ($period === 'weekly') {
            $current = $start->copy();
            while ($current <= $end) {
                $chart[] = [
                    'label' => $current->locale('id')->isoFormat('ddd, D MMM'),
                    'total' => (float) Transaction::whereDate('created_at', $current)->sum('total_price'),
                    'count' => Transaction::whereDate('created_at', $current)->count(),
                ];
                $current->addDay();
            }
        } else {
            // per minggu dalam sebulan
            $current = $start->copy()->startOfWeek();
            $weekNum = 1;

            while ($current <= $end) {
                $weekEnd = $current->copy()->endOfWeek();

                $chart[] = [
                    'label' => 'Minggu ' . $weekNum,
                    'total' => (float) Transaction::whereBetween('created_at', [$current, $weekEnd])->sum('total_price'),
                    'count' => Transaction::whereBetween('created_at', [$current, $weekEnd])->count(),
                ];

                $current->addWeek();
                $weekNum++;
            }
        }

        return $chart;
    }
}
