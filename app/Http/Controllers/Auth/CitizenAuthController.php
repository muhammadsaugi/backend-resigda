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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:citizens,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $citizen = Citizen::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $citizen->createToken('regsida-citizen-token')->plainTextToken;

        return response()->json([
            'citizen' => [
                'id' => $citizen->id,
                'name' => $citizen->name,
                'email' => $citizen->email,
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

        $citizen = Citizen::where('email', $validated['email'])->first();

        if (! $citizen || ! Hash::check($validated['password'], $citizen->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $token = $citizen->createToken('regsida-citizen-token')->plainTextToken;

        return response()->json([
            'citizen' => [
                'id' => $citizen->id,
                'name' => $citizen->name,
                'email' => $citizen->email,
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
        $request->user('sanctum')->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
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
        ]);
    }
}
