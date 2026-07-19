<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian di routes: ->middleware('role:bagian_hukum,inspektorat')
 * Route harus sudah dilewati middleware 'auth:sanctum' duluan supaya
 * $request->user() terisi.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Akun ASN kamu tidak aktif. Hubungi admin.'], 403);
        }

        if (! $user->hasRole(...$roles)) {
            return response()->json([
                'message' => 'Kamu tidak punya akses ke resource ini.',
            ], 403);
        }

        return $next($request);
    }
}