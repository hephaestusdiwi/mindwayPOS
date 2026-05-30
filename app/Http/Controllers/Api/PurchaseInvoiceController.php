<?php
// ============================================================
// app/Http/Controllers/Api/PurchaseInvoiceController.php
// ============================================================
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseInvoice::with(['supplier', 'createdBy'])->latest();

        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('supplier_id')) $query->where('supplier_id', $request->supplier_id);
        if ($request->filled('from'))        $query->whereDate('invoice_date', '>=', $request->from);
        if ($request->filled('to'))          $query->whereDate('invoice_date', '<=', $request->to);

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_number'   => 'required|string|max:50|unique:purchase_invoices',
            'supplier_id'      => 'required|exists:suppliers,id',
            'goods_receipt_id' => 'nullable|exists:goods_receipts,id',
            'invoice_date'     => 'required|date',
            'due_date'         => 'required|date',
            'total_amount'     => 'required|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);

        $invoice = PurchaseInvoice::create([
            ...$request->only(['invoice_number', 'supplier_id', 'goods_receipt_id', 'invoice_date', 'due_date', 'total_amount', 'notes']),
            'created_by'       => Auth::id(),
            'subtotal'         => $request->total_amount,
            'remaining_amount' => $request->total_amount,
            'status'           => 'unpaid',
        ]);

        return response()->json($invoice, 201);
    }

    public function show(PurchaseInvoice $purchaseInvoice)
    {
        return response()->json($purchaseInvoice->load('supplier', 'goodsReceipt', 'payments', 'createdBy'));
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $request->validate([
            'due_date' => 'sometimes|date',
            'notes'    => 'nullable|string',
        ]);
        $purchaseInvoice->update($request->only(['due_date', 'notes']));
        return response()->json($purchaseInvoice);
    }

    public function destroy(PurchaseInvoice $purchaseInvoice)
    {
        if ($purchaseInvoice->status !== 'unpaid') {
            return response()->json(['message' => 'Invoice yang sudah dibayar tidak bisa dihapus.'], 422);
        }
        $purchaseInvoice->delete();
        return response()->json(['message' => 'Invoice dihapus.']);
    }

    public function addPayment(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $request->validate([
            'payment_date'   => 'required|date',
            'amount'         => 'required|numeric|min:0.01|max:' . $purchaseInvoice->remaining_amount,
            'payment_method' => 'required|in:cash,transfer,giro',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
        ]);

        PurchaseInvoicePayment::create([
            ...$request->only(['payment_date', 'amount', 'payment_method', 'reference', 'notes']),
            'purchase_invoice_id' => $purchaseInvoice->id,
            'created_by'          => Auth::id(),
        ]);

        $newPaid      = $purchaseInvoice->paid_amount + $request->amount;
        $newRemaining = $purchaseInvoice->total_amount - $newPaid;
        $newStatus    = $newRemaining <= 0 ? 'paid' : 'partial';

        $purchaseInvoice->update([
            'paid_amount'      => $newPaid,
            'remaining_amount' => max(0, $newRemaining),
            'status'           => $newStatus,
        ]);

        return response()->json(['message' => 'Pembayaran dicatat.', 'invoice' => $purchaseInvoice->fresh()]);
    }
}