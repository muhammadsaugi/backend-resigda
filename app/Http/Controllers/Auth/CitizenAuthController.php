<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
     */
    public function googleAuth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'name' => ['required', 'string'],
            'google_id' => ['nullable', 'string'],
            'avatar' => ['nullable', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));

        $citizen = Citizen::where('email', $email)
            ->orWhere(function ($query) use ($validated) {
                if (! empty($validated['google_id'])) {
                    $query->where('google_id', $validated['google_id']);
                }
            })
            ->first();

        if (! $citizen) {
            $citizen = Citizen::create([
                'name' => $validated['name'],
                'email' => $email,
                'google_id' => $validated['google_id'] ?? 'google_' . md5($email),
                'avatar' => $validated['avatar'] ?? null,
                'password' => Hash::make(\Illuminate\Support\Str::random(16)),
                'email_verified_at' => now(),
            ]);
        } else {
            $citizen->update([
                'google_id' => $validated['google_id'] ?? $citizen->google_id ?? 'google_' . md5($email),
                'avatar' => $validated['avatar'] ?? $citizen->avatar,
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
