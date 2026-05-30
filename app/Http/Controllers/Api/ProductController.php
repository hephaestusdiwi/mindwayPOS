<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('low_stock')) {
            $query->where('stock', '<=', 10);
        }

        $products = $query->latest()->paginate($request->get('per_page', 24));

        return response()->json($products);
    }

    public function store(Request $request)
    {
                $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'price'          => 'required|numeric|min:0',
            'stock'          => 'required|integer|min:0',
            'sku'            => 'nullable|string|unique:products,sku',
            'description'    => 'nullable|string',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'is_active'      => 'nullable|in:0,1',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data = $request->except('image');

        // Auto generate SKU jika tidak diberikan
        if (empty($data['sku'])) {
            $data['sku'] = strtoupper(Str::random(3)) . '-' . rand(1000, 9999);
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);
        $product->load('category');

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load('category'));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'name'           => 'sometimes|string|max:255',
            'category_id'    => 'sometimes|exists:categories,id',
            'price'          => 'sometimes|numeric|min:0',
            'stock'          => 'sometimes|integer|min:0',
            'sku'            => 'nullable|string|unique:products,sku,' . $product->id,
            'description'    => 'nullable|string',
            'discount_price' => 'nullable|numeric|min:0',
            'is_active'      => 'boolean',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
 
        $data = $request->except('image');
 
        // Upload gambar baru
        if ($request->hasFile('image')) {
            // Hapus gambar lama
            if ($product->image) {
                \Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }
 
        $product->update($data);
        $product->load('category');
 
        return response()->json($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->image) {
            \Storage::disk('public')->delete($product->image);
        }
 
        $product->delete();
 
        return response()->json(['message' => 'Produk berhasil dihapus']);
    }
}