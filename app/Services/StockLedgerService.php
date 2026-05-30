<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockLedgerService
{
    public function recordReceipt(int $productId, float $qty, float $unitCost, string $refType, int $refId, string $refNumber, ?string $notes = null): StockLedger
    {
        return $this->record($productId, $qty, 0, $unitCost, $refType, $refId, $refNumber, $notes);
    }

    public function recordSale(int $productId, float $qty, float $unitCost, int $transactionId, string $transactionNumber): StockLedger
    {
        return $this->record($productId, 0, $qty, $unitCost, 'Transaction', $transactionId, $transactionNumber);
    }

    public function recordAdjustment(int $productId, float $qtyDifference, float $unitCost, int $adjustmentId, string $adjustmentNumber, ?string $notes = null): StockLedger
    {
        return $this->record(
            $productId,
            $qtyDifference > 0 ? $qtyDifference : 0,
            $qtyDifference < 0 ? abs($qtyDifference) : 0,
            $unitCost, 'StockAdjustment', $adjustmentId, $adjustmentNumber, $notes
        );
    }

    public function recordSupplierReturn(int $productId, float $qty, float $unitCost, int $returnId, string $returnNumber, ?string $notes = null): StockLedger
    {
        return $this->record($productId, 0, $qty, $unitCost, 'SupplierReturn', $returnId, $returnNumber, $notes);
    }

    public function recordOpname(int $productId, float $qtyDifference, float $unitCost, int $opnameId, string $opnameNumber): StockLedger
    {
        return $this->record(
            $productId,
            $qtyDifference > 0 ? $qtyDifference : 0,
            $qtyDifference < 0 ? abs($qtyDifference) : 0,
            $unitCost, 'StockOpname', $opnameId, $opnameNumber
        );
    }

    private function record(int $productId, float $qtyIn, float $qtyOut, float $unitCost, string $refType, int $refId, string $refNumber, ?string $notes = null): StockLedger
    {
        return DB::transaction(function () use ($productId, $qtyIn, $qtyOut, $unitCost, $refType, $refId, $refNumber, $notes) {
            $product    = Product::lockForUpdate()->findOrFail($productId);
            $qtyBalance = $product->current_stock + $qtyIn - $qtyOut;

            $product->update(['current_stock' => $qtyBalance]);

            return StockLedger::create([
                'product_id'  => $productId,
                'ref_type'    => $refType,
                'ref_id'      => $refId,
                'ref_number'  => $refNumber,
                'qty_in'      => $qtyIn,
                'qty_out'     => $qtyOut,
                'qty_balance' => $qtyBalance,
                'unit_cost'   => $unitCost,
                'total_cost'  => ($qtyIn + $qtyOut) * $unitCost,
                'notes'       => $notes,
                'created_by'  => Auth::id(),
            ]);
        });
    }

    public function getCurrentStock(int $productId): float
    {
        $latest = StockLedger::where('product_id', $productId)->latest('id')->first();
        return $latest ? (float) $latest->qty_balance : 0;
    }

    public function getLedger(int $productId, ?string $from = null, ?string $to = null)
    {
        $query = StockLedger::with('product', 'createdBy')
            ->where('product_id', $productId)
            ->orderBy('id', 'asc');

        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to)   $query->whereDate('created_at', '<=', $to);

        return $query->get();
    }

    public function getLowStockProducts()
    {
        return Product::with('category')
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->where('is_active', true)
            ->orderByRaw('(current_stock - min_stock) ASC')
            ->get();
    }

    public function reverseEntries(string $refType, int $refId, string $reversalNote): void
    {
        DB::transaction(function () use ($refType, $refId, $reversalNote) {
            $entries = StockLedger::where('ref_type', $refType)->where('ref_id', $refId)->get();
            foreach ($entries as $entry) {
                $this->record(
                    $entry->product_id, $entry->qty_out, $entry->qty_in,
                    $entry->unit_cost, $refType . 'Reversal', $refId,
                    'VOID-' . $entry->ref_number, $reversalNote
                );
            }
        });
    }
}