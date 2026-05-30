<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveOutlet
{
    /**
     * Inject outlet_id aktif ke setiap request yang sudah auth.
     *
     * Flow:
     * - Owner bisa kirim header X-Outlet-ID untuk switch outlet
     * - User biasa: otomatis pakai outlet dari pivot table
     * - Jika user tidak punya outlet sama sekali → 403
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Owner: bisa pakai outlet manapun via header atau session
        if ($user->isOwner() || $user->isAdmin()) {
            $requestedOutletId = $request->header('X-Outlet-ID')
                              ?? $user->outlets()->first()?->id;

            $request->merge(['active_outlet_id' => (int) $requestedOutletId]);

            if ($requestedOutletId) {
                session(['active_outlet_id' => (int) $requestedOutletId]);
                $request->merge(['active_outlet_id' => (int) $requestedOutletId]);
            } else {
                // Default: outlet pertama
                $firstOutlet = \App\Models\Outlet::active()->first();
                if ($firstOutlet) {
                    session(['active_outlet_id' => $firstOutlet->id]);
                    $request->merge(['active_outlet_id' => $firstOutlet->id]);
                }
            }

            return $next($request);
        }

        // User biasa: ambil outlet dari pivot
        $outletId = $user->activeOutletId();

        if (! $outletId) {
            return response()->json([
                'message' => 'Akun Anda belum di-assign ke outlet manapun. Hubungi administrator.',
            ], 403);
        }

        // Inject ke request — bisa dipakai di controller via $request->active_outlet_id
        $request->merge(['active_outlet_id' => $outletId]);

        return $next($request);
    }
}