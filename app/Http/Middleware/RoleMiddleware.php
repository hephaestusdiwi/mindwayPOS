<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Akun anda telah dinonaktifkan'], 403);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke halaman ini',
            ], 403);
        }

        return $next($request);
    }
}