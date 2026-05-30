<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /api/users
     * Hanya admin yang bisa akses (dijaga middleware)
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->role) {
            $query->where('role', $request->role);
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->latest()->paginate($request->per_page ?? 15);

        return response()->json($users);
    }

    /**
     * POST /api/users
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8',
            'role'      => 'required|in:admin,manager,cashier',
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'avatar'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $validated['is_active'] = filter_var($request->is_active ?? true, FILTER_VALIDATE_BOOLEAN);

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    /**
     * PUT /api/users/{user}
     */
    public function update(Request $request, $id)
    {
        $user = \App\Models\User::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => ['required', 'email', \Illuminate\Validation\Rule::unique('users')->ignore($user->id)],
            'password'  => 'nullable|string|min:8',
            'role'      => 'required|in:admin,manager,cashier',
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'avatar'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $validated['is_active'] = filter_var($request->is_active ?? $user->is_active, FILTER_VALIDATE_BOOLEAN);

        $user->update($validated);

        return response()->json($user->fresh());
    }

    /**
     * DELETE /api/users/{user}
     */
    public function destroy(User $user)
    {
        // Tidak boleh hapus diri sendiri
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Tidak dapat menghapus akun sendiri.'], 422);
        }

        // Tidak boleh hapus admin terakhir
        if ($user->role === 'admin' && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return response()->json(['message' => 'Tidak dapat menghapus admin terakhir.'], 422);
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        return response()->json(['message' => 'User berhasil dihapus.']);
    }

    /**
     * GET /api/users/me
     * Profil user yang sedang login (semua role bisa akses)
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * PUT /api/users/me
     * Update profil sendiri (semua role bisa, tapi tidak bisa ganti role)
     */
    public function updateMe(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'avatar'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);

        return response()->json($user->fresh());
    }
}