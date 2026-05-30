<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    // Hanya tampilkan outlet yang dimiliki user yang login
    public function index(Request $request)
    {
        $user = $request->user();

        // Admin utama bisa lihat semua outlet
        if (in_array($user->role, ['admin', 'owner'])) {
            $outlets = Outlet::with('users')->get();
        } else {
            // Pegawai hanya lihat outlet yang di-assign ke mereka
            $outlets = $user->outlets()->with('users')->get();
        }

        return response()->json($outlets);
    }

    // Buat outlet baru — hanya admin
    public function store(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'owner'])) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string',
        ]);

        $outlet = Outlet::create($validated);

        // Auto-assign admin yang membuat ke outlet ini
        $outlet->users()->attach($request->user()->id, [
            'role'      => 'admin',
            'is_active' => true,
        ]);

        return response()->json($outlet, 201);
    }

    public function show(Request $request, Outlet $outlet)
    {
        // Pastikan user punya akses ke outlet ini
        if (!$this->userHasAccess($request->user(), $outlet)) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        return response()->json($outlet->load('users'));
    }

    public function update(Request $request, Outlet $outlet)
    {
        if (!in_array($request->user()->role, ['admin', 'owner'])) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'address'   => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $outlet->update($validated);
        return response()->json($outlet);
    }

    public function destroy(Request $request, Outlet $outlet)
    {
        if (!in_array($request->user()->role, ['admin', 'owner'])) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $outlet->delete();
        return response()->json(['message' => 'Outlet berhasil dihapus']);
    }

    // Assign pegawai ke outlet — hanya admin
    public function assignUser(Request $request, Outlet $outlet)
    {
        if (!in_array($request->user()->role, ['admin', 'owner'])) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'nullable|in:admin,manager,cashier',
        ]);

        // Cek apakah user sudah di-assign ke outlet ini
        if ($outlet->users()->where('user_id', $request->user_id)->exists()) {
            return response()->json(['message' => 'User sudah terdaftar di outlet ini'], 422);
        }

        $outlet->users()->attach($request->user_id, [
            'role'      => $request->role ?? 'cashier',
            'is_active' => true,
        ]);

        return response()->json(['message' => 'User berhasil ditambahkan ke outlet']);
    }

    // Hapus pegawai dari outlet — hanya admin
    public function removeUser(Request $request, Outlet $outlet, User $user)
    {
        if (!in_array($request->user()->role, ['admin', 'owner'])) {
        return response()->json(['message' => 'Akses ditolak'], 403);
    }

        $outlet->users()->detach($user->id);
        return response()->json(['message' => 'User berhasil dihapus dari outlet']);
    }

    // Switch outlet aktif
    public function switchOutlet(Request $request)
    {
        $request->validate(['outlet_id' => 'required|exists:outlets,id']);

        $user   = $request->user();
        $outlet = Outlet::findOrFail($request->outlet_id);

        // Pastikan user punya akses ke outlet yang dituju
        if (!$this->userHasAccess($user, $outlet)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke outlet ini'], 403);
        }

        // Simpan outlet aktif di pivot
        $user->outlets()->updateExistingPivot($outlet->id, ['is_active' => true]);

        // Nonaktifkan outlet lain
        $user->outlets()
             ->where('outlet_id', '!=', $outlet->id)
             ->each(fn($o) => $user->outlets()->updateExistingPivot($o->id, ['is_active' => false]));

        return response()->json([
            'message' => 'Outlet aktif berhasil diganti',
            'outlet'  => $outlet,
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────
    private function userHasAccess(User $user, Outlet $outlet): bool
    {
        if (in_array($user->role, ['admin', 'owner'])) return true;
        return $user->outlets()->where('outlet_id', $outlet->id)->exists();
    }
}