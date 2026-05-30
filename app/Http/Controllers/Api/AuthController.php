<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // REGISTER
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Register berhasil',
            'user'    => $user
        ], 201);
    }

    // LOGIN
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email atau password salah'
            ], 401);
        }

        $user = Auth::user();

        // hapus token lama
        $user->tokens()->delete();

        // buat token baru
        $token = $user->createToken('api-token')->plainTextToken;

        $activeOutletId = $user->activeOutletId();
        $activeOutlet   = $activeOutletId
            ? Outlet::find($activeOutletId)
            : null;

        $outlets = $user->isOwner()
            ? Outlet::active()->get(['id', 'name'])
            : $user->outlets()->wherePivot('is_active', true)->get(['outlets.id', 'outlets.name']);
        return response()->json([
            'message' => 'Login berhasil',
            'token'   => $token,
            'user'    => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'role'          => $user->role,
                'avatar'        => $user->avatar,
                'avatar_url'    => $user->avatar_url,
                'active_outlet' => $outlets,
            ],
        ]);
    }

    // LOGOUT
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }

    // ME (ANTI ERROR 500)
    public function me(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = $request->user();

        $activeOutletId = $user->activeOutletId();
        $activeOutlet   = $activeOutletId
            ? Outlet::active()->get(['id', 'name'])
            : $user->outlets()->wherePivot('is_active', true)->get(['outlets.id', 'outlets.name']);

        return response()->json([
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => $user->role,
            'avatar'        => $user->avatar,
            'avatar_url'    => $user->avatar_url,
            'active_outlet' => $activeOutlet,
            'outlets'       => $outlets
        ]);
    }
}