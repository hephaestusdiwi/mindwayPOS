<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::withCount('products');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $categories = $query->latest()->paginate($request->per_page ?? 20);

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:500',
            'color'       => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active'   => 'nullable|boolean',
        ]);

        $validated['is_active'] = filter_var($request->is_active ?? true, FILTER_VALIDATE_BOOLEAN);
        $validated['color']     = $validated['color'] ?? '#117c6f';

        $category = Category::create($validated);

        return response()->json($category->loadCount('products'), 201);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => "required|string|max:255|unique:categories,name,{$category->id}",
            'description' => 'nullable|string|max:500',
            'color'       => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active'   => 'nullable|boolean',
        ]);

        $validated['is_active'] = filter_var($request->is_active ?? $category->is_active, FILTER_VALIDATE_BOOLEAN);

        $category->update($validated);

        return response()->json($category->loadCount('products'));
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Kategori tidak dapat dihapus karena masih memiliki produk.',
            ], 422);
        }

        $category->delet();

        return response()->json(['message' => 'Kategory berhasil dihapus.']);
    }
}