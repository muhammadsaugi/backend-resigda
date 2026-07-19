<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Auth ASN pakai Sanctum personal access token (bukan SPA cookie session),
 * karena frontend React dan backend Laravel akan di-deploy di domain
 * berbeda (Vercel vs Railway/Render) — token-based lebih simpel untuk
 * skenario cross-domain dibanding cookie-based SPA auth.
 */
class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Login pakai NIP + password (bukan email, sesuai identitas ASN).
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nip' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('nip', $validated['nip'])->first();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, ['password' => $validated['password']])) {
            throw ValidationException::withMessages([
                'nip' => ['NIP atau password salah.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'nip' => ['Akun ASN kamu tidak aktif. Hubungi admin.'],
            ]);
        }

        // Hapus token lama supaya tidak menumpuk tiap kali login dari device baru
        $token = $user->createToken('regsida-asn-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'nip' => $user->nip,
                'role' => $user->role,
                'opd' => $user->opd,
            ],
            'token' => $token,
        ]);
    }

    /**
     * POST /api/auth/logout
     * Hanya cabut token yang sedang dipakai request ini, bukan semua token user
     * (supaya login dari device lain tidak ikut ter-logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * GET /api/auth/me
     * Dipakai frontend untuk cek status login & role saat aplikasi dibuka.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'nip' => $user->nip,
            'role' => $user->role,
            'opd' => $user->opd,
        ]);
    }
}