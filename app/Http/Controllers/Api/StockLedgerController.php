<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\StockLedgerService;
use App\Services\StockAlertService;
use Illuminate\Http\Request;

class StockLedgerController extends Controller
{
    public function __construct(
        private StockLedgerService $ledger,
        private StockAlertService $alert
    ) {}

    /**
     * Kartu stok per produk
     * GET /auth/stock-ledger/{productId}
     */
    public function show(Request $request, int $productId)
    {
        $outletId = $request->active_outlet_id;

        $product = Product::where('outlet_id', $outletId)
            ->findOrFail($productId);

        $ledger  = $this->ledger->getLedger($productId, $request->from, $request->to);

        return response()->json([
            'product' => $product,
            'ledger'  => $ledger,
        ]);
    }

    /**
     * Daftar produk stok rendah
     * GET /auth/stock-alerts
     */
    public function lowStock(Request $request)
    {
        $outletId = $request->active_outlet_id;

        return response()->json([
            'summary'  => $this->alert->getSummary($outletId), 
            'products' => $this->alert->getReorderProducts($outletId), 
        ]);
    }

    /**
     * Jumlah produk stok rendah untuk badge sidebar
     * GET /auth/stock-alerts/count
     */
    public function alertCount(Request $request)
    {
        $outletId = $request->active_outlet_id;

        return response()->json([
            'count' => $this->alert->getLowStockCount($outletId), 
        ]);
    }
}