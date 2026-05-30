<?php
// ============================================================
// app/Http/Controllers/Api/SupplierController.php
// ============================================================
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
 
class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();
        if ($request->filled('search'))
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
        if ($request->filled('status'))
            $query->where('status', $request->status);
 
        return response()->json($query->latest()->paginate(20));
    }
 
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:100',
            'contact_person'    => 'nullable|string|max:100',
            'phone'             => 'nullable|string|max:20',
            'email'             => 'nullable|email',
            'address'           => 'nullable|string',
            'city'              => 'nullable|string|max:100',
            'npwp'              => 'nullable|string|max:30',
            'payment_terms'     => 'required|in:cash,net7,net14,net30,net60',
            'bank_name'         => 'nullable|string|max:100',
            'bank_account'      => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:100',
            'notes'             => 'nullable|string',
            'status'            => 'in:active,inactive',
        ]);
        return response()->json(Supplier::create($data), 201);
    }
 
    public function show(Supplier $supplier)
    {
        return response()->json($supplier->load(['purchaseOrders' => fn($q) => $q->latest()->limit(5)]));
    }
 
    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name'              => 'sometimes|required|string|max:100',
            'contact_person'    => 'nullable|string|max:100',
            'phone'             => 'nullable|string|max:20',
            'email'             => 'nullable|email',
            'address'           => 'nullable|string',
            'city'              => 'nullable|string|max:100',
            'npwp'              => 'nullable|string|max:30',
            'payment_terms'     => 'in:cash,net7,net14,net30,net60',
            'bank_name'         => 'nullable|string|max:100',
            'bank_account'      => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:100',
            'notes'             => 'nullable|string',
            'status'            => 'in:active,inactive',
        ]);
        $supplier->update($data);
        return response()->json($supplier);
    }
 
    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            return response()->json(['message' => 'Supplier memiliki data PO, tidak bisa dihapus.'], 422);
        }
        $supplier->delete();
        return response()->json(['message' => 'Supplier dihapus.']);
    }
}