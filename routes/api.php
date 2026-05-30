<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\StockOpnameController;
// Phase 2
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UomController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\StockLedgerController;
use App\Http\Controllers\Api\SupplierReturnController;
use App\Http\Controllers\Api\PurchaseInvoiceController;
use App\Http\Controllers\Api\OutletController; 

// test
Route::get('/test', function () {
    return response()->json(['message' => 'OK']);
});

// AUTH
Route::prefix('auth')->group(function () {

    // public
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    // protected
    Route::middleware(['auth:sanctum', 'set.outlet'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::post('/logout',   [AuthController::class, 'logout']);
        Route::get('/me',        [AuthController::class, 'me']);

        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('products',   ProductController::class);

        Route::get('transactions',      [TransactionController::class, 'index']);
        Route::post('transactions',     [TransactionController::class, 'store']);
        Route::get('transactions/{id}', [TransactionController::class, 'show']);

        Route::get('api/categories',               [CategoryController::class, 'index']);
        Route::post('api/categories',              [CategoryController::class, 'store']);
        Route::put('api/categories/{category}',    [CategoryController::class, 'update']);
        Route::delete('api/categories/{category}', [CategoryController::class, 'destroy']);

        // ⚠️ cart/checkout HARUS di atas cart/{id}
        Route::post('cart/checkout',  [CartController::class, 'checkout']);
        Route::get('cart',            [CartController::class, 'index']);
        Route::post('cart',           [CartController::class, 'store']);
        Route::delete('cart/{id}',    [CartController::class, 'destroy']);

        Route::get('reports', [App\Http\Controllers\Api\ReportController::class, 'index']);

        Route::get('/me',  [UserController::class, 'me']);
        Route::post('/me', [UserController::class, 'updateMe']);

        Route::middleware('role:admin')->group(function () {
            Route::get('/users',                         [UserController::class, 'index']);
            Route::post('/users',                        [UserController::class, 'store']);
            Route::match(['POST', 'PUT'], '/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{user}',               [UserController::class, 'destroy']);
        });

        Route::prefix('outlets')->group(function () {
            Route::post('/switch', [OutletController::class, 'switchOutlet']); // ← pindah ke atas
            Route::get('/',                              [OutletController::class, 'index']);
            Route::post('/',                             [OutletController::class, 'store']);
            Route::get('/{outlet}',                      [OutletController::class, 'show']);
            Route::post('/{outlet}',                     [OutletController::class, 'update']);
            Route::delete('/{outlet}',                   [OutletController::class, 'destroy']);
            Route::post('/{outlet}/assign-user',         [OutletController::class, 'assignUser']);
            Route::delete('/{outlet}/remove-user/{user}',[OutletController::class, 'removeUser']);
        });

        // ── Inventory (existing) ───────────────────────────────────────────
        Route::prefix('inventory')->group(function () {
            Route::get('/products',                     [InventoryController::class, 'products']);
            Route::get('/mutations',                    [InventoryController::class, 'mutations']);
            Route::put('/products/{product}/min-stock', [InventoryController::class, 'updateMinStock']);
            Route::get('/summary',                      [InventoryController::class, 'summary']);
            Route::get('/opname',                       [StockOpnameController::class, 'index']);
            Route::post('/opname',                      [StockOpnameController::class, 'store']);
            Route::get('/opname/{opname}',              [StockOpnameController::class, 'show']);
            Route::put('/opname/{opname}/items',        [StockOpnameController::class, 'updateItems']);
            Route::post('/opname/{opname}/confirm',     [StockOpnameController::class, 'confirm']);
            Route::delete('/opname/{opname}',           [StockOpnameController::class, 'destroy']);
        });

        // ── Phase 2: Master Supplier ───────────────────────────────────────
        Route::apiResource('suppliers', SupplierController::class);

        // ── Phase 2: Unit of Measure ───────────────────────────────────────
        Route::apiResource('uom', UomController::class);

        // ── Phase 2: Purchase Orders ───────────────────────────────────────
        Route::apiResource('purchase-orders', PurchaseOrderController::class)->except(['update']);
        Route::patch('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
        Route::patch('purchase-orders/{purchaseOrder}/cancel',  [PurchaseOrderController::class, 'cancel']);

        // ── Phase 2: Goods Receipts (GRN) ─────────────────────────────────
        Route::apiResource('goods-receipts', GoodsReceiptController::class)->except(['update']);
        Route::patch('goods-receipts/{goodsReceipt}/confirm', [GoodsReceiptController::class, 'confirm']);
        Route::patch('goods-receipts/{goodsReceipt}/cancel',  [GoodsReceiptController::class, 'cancel']);

        // ── Phase 2: Purchase Invoices ─────────────────────────────────────
        Route::apiResource('purchase-invoices', PurchaseInvoiceController::class);
        Route::post('purchase-invoices/{purchaseInvoice}/payments', [PurchaseInvoiceController::class, 'addPayment']);

        // ── Phase 2: Stock Adjustments ─────────────────────────────────────
        Route::apiResource('stock-adjustments', StockAdjustmentController::class)->except(['update']);
        Route::patch('stock-adjustments/{stockAdjustment}/confirm', [StockAdjustmentController::class, 'confirm']);
        Route::patch('stock-adjustments/{stockAdjustment}/cancel',  [StockAdjustmentController::class, 'cancel']);

        // ── Phase 2: Supplier Returns ──────────────────────────────────────
        Route::apiResource('supplier-returns', SupplierReturnController::class)->except(['update']);
        Route::patch('supplier-returns/{supplierReturn}/confirm', [SupplierReturnController::class, 'confirm']);

        // ── Phase 2: Stock Ledger & Alert ──────────────────────────────────
        // ⚠️ stock-alerts/count HARUS di atas stock-alerts
        Route::get('stock-alerts/count', [StockLedgerController::class, 'alertCount']);
        Route::get('stock-alerts',       [StockLedgerController::class, 'lowStock']);
        Route::get('stock-ledger/{productId}', [StockLedgerController::class, 'show']);
    });
});