<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\CitizenAuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CivicIntelligenceController;
use App\Http\Controllers\ClaimVerificationController;
use App\Http\Controllers\InspektoratController;
use App\Http\Controllers\RegulationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==================== Portal Warga (publik, tanpa login) ====================

Route::post('/chat', [ChatController::class, 'store']);
Route::post('/verify-claim', [ClaimVerificationController::class, 'store']);

Route::get('/regulations', [RegulationController::class, 'index']);
Route::get('/regulations/{regulation}', [RegulationController::class, 'show']);
Route::get('/regulations/{regulation}/relations', [RegulationController::class, 'relations']);

// ==================== Auth Warga ====================

Route::post('/citizen/register', [CitizenAuthController::class, 'register']);
Route::post('/citizen/login', [CitizenAuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/citizen/logout', [CitizenAuthController::class, 'logout']);
    Route::get('/citizen/me', [CitizenAuthController::class, 'me']);
});

// ==================== Auth ASN ====================

Route::post('/auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});

// ==================== Portal ASN (butuh auth + role) ====================

Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {

    // Dashboard: bisa diakses ketiga role (Staf OPD, Bagian Hukum, Inspektorat)
    Route::middleware('role:staf_opd,bagian_hukum,inspektorat')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
    });

    // Suara Warga & Decay Tracker: Staf OPD + Bagian Hukum (lihat tabel role di master prompt)
    Route::middleware('role:staf_opd,bagian_hukum')->group(function () {
        Route::get('/civic-insights', [CivicIntelligenceController::class, 'civicInsights']);
        Route::get('/decay', [CivicIntelligenceController::class, 'decay']);
        Route::get('/relations', [AdminController::class, 'listRelations']);
        Route::get('/reports/export', [AdminController::class, 'exportReport']);
    });

    // Manajemen regulasi, validasi relasi graf, closed-loop revisi: Bagian Hukum saja
    Route::middleware('role:bagian_hukum')->group(function () {
        Route::post('/regulations', [AdminController::class, 'storeRegulation']);
        Route::put('/regulations/{regulation}', [AdminController::class, 'updateRegulation']);
        Route::post('/regulations/{regulation}/embed', [AdminController::class, 'embedRegulation']);
        Route::post('/regulations/{regulation}/upload-pdf', [AdminController::class, 'uploadPdf']);
        Route::post('/relations', [AdminController::class, 'storeRelation']);
        Route::patch('/relations/{relation}', [AdminController::class, 'validateRelation']);
        Route::patch('/revisions/{revision}', [AdminController::class, 'updateRevisionStatus']);
    });

    // Dasbor Inspektorat pungli: Inspektorat saja.
    Route::middleware('role:inspektorat')->group(function () {
        Route::get('/inspektorat/pungli-heatmap', [InspektoratController::class, 'pungliHeatmap']);
        Route::get('/inspektorat/claim-history', [InspektoratController::class, 'claimHistory']);
    });
});