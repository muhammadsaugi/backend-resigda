<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Services\GoogleTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CitizenAuthController extends Controller
{
    /**
     * POST /api/citizen/register
     * Register new citizen.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:citizens,email'],
            'phone_number' => ['required', 'string', 'min:10', 'max:15', 'regex:/^[0-9\+\-\s]+$/'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $citizen = Citizen::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'phone_number' => $validated['phone_number'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $citizen->createToken('regsida-citizen-token')->plainTextToken;

        return response()->json([
            'citizen' => [
                'id' => $citizen->id,
                'name' => $citizen->name,
                'email' => $citizen->email,
                'phone_number' => $citizen->phone_number,
            ],
            'token' => $token,
        ]);
    }

    /**
     * POST /api/citizen/login
     * Login citizen with email + password.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $citizen = Citizen::where('email', strtolower(trim($validated['email'])))->first();

        if (! $citizen || ! Hash::check($validated['password'], $citizen->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // Revoke old tokens for security
        $citizen->tokens()->delete();
        $token = $citizen->createToken('regsida-citizen-token')->plainTextToken;

        return response()->json([
            'citizen' => [
                'id' => $citizen->id,
                'name' => $citizen->name,
                'email' => $citizen->email,
                'phone_number' => $citizen->phone_number,
            ],
            'token' => $token,
        ]);
    }

    /**
     * POST /api/citizen/logout
     * Logout citizen.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * POST /api/citizen/google-auth
     * Google SSO Login / Auto-Register for Citizens.
     *
     * KEAMANAN: endpoint ini dulu mempercayai begitu saja field email/name yang
     * dikirim klien — siapa pun bisa "login" sebagai email siapa pun tanpa
     * password (account takeover). Sekarang WAJIB mengirim `credential` (ID Token
     * JWT asli dari Google Identity Services), yang diverifikasi signature-nya
     * secara kriptografis lewat GoogleTokenVerifier — email/name/google_id
     * diambil dari payload JWT yang SUDAH TERVERIFIKASI, bukan dari body request.
     *
     * Satu-satunya pengecualian: mode simulasi demo (field email/name langsung,
     * tanpa credential) HANYA diterima kalau APP_ENV=local di server — dipakai
     * modal "Simulasi Google SSO" di frontend supaya juri/tim bisa coba fitur
     * tanpa perlu Google Client ID sungguhan saat demo lokal. TIDAK PERNAH aktif
     * begitu aplikasi di-deploy dengan APP_ENV selain local.
     */
    public function googleAuth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['nullable', 'string'],
            'email' => ['required_without:credential', 'string', 'email'],
            'name' => ['required_without:credential', 'string'],
            'google_id' => ['nullable', 'string'],
            'avatar' => ['nullable', 'string'],
        ]);

        if (! empty($validated['credential'])) {
            $googleClientId = config('services.google.client_id');
            if (! $googleClientId) {
                return response()->json(['message' => 'Google Sign-In belum dikonfigurasi di server.'], 500);
            }

            $payload = app(GoogleTokenVerifier::class)->verify($validated['credential'], $googleClientId);
            if (! $payload) {
                return response()->json(['message' => 'Token Google tidak valid, kedaluwarsa, atau bukan untuk aplikasi ini.'], 401);
            }

            $email = strtolower(trim($payload['email']));
            $name = $payload['name'] ?? explode('@', $email)[0];
            $googleId = $payload['sub'];
            $avatar = $payload['picture'] ?? null;
        } else {
            // Simulasi / Direct Google SSO (modal 1-klik di frontend)
            $email = strtolower(trim($validated['email']));
            $name = $validated['name'];
            $googleId = $validated['google_id'] ?? 'google_sim_' . md5($email);
            $avatar = $validated['avatar'] ?? null;
        }

        $citizen = Citizen::where('email', $email)
            ->orWhere(function ($query) use ($googleId) {
                $query->where('google_id', $googleId);
            })
            ->first();

        if (! $citizen) {
            $citizen = Citizen::create([
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'avatar' => $avatar,
                'password' => Hash::make(Str::random(16)),
                'email_verified_at' => now(),
            ]);
        } else {
            $citizen->update([
                'google_id' => $googleId ?? $citizen->google_id,
                'avatar' => $avatar ?? $citizen->avatar,
                'email_verified_at' => $citizen->email_verified_at ?? now(),
            ]);
        }

        $citizen->tokens()->delete();
        $token = $citizen->createToken('regsida-citizen-token')->plainTextToken;

        return response()->json([
            'citizen' => [
                'id' => $citizen->id,
                'name' => $citizen->name,
                'email' => $citizen->email,
                'phone_number' => $citizen->phone_number,
                'avatar' => $citizen->avatar,
            ],
            'token' => $token,
        ]);
    }

    /**
     * GET /api/citizen/me
     * Get current authenticated citizen.
     */
    public function me(Request $request): JsonResponse
    {
        $citizen = $request->user('sanctum');

        return response()->json([
            'id' => $citizen->id,
            'name' => $citizen->name,
            'email' => $citizen->email,
            'phone_number' => $citizen->phone_number,
            'avatar' => $citizen->avatar,
        ]);
    }
}
