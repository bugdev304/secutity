<?php

declare(strict_types=1);

use Ae3\AuthSecurity\Http\Controllers\AssistedRecoveryController;
use Ae3\AuthSecurity\Http\Controllers\FactorController;
use Ae3\AuthSecurity\Http\Controllers\MfaContactController;
use Ae3\AuthSecurity\Http\Controllers\MfaVerificationController;
use Ae3\AuthSecurity\Http\Controllers\OrganizationPolicyController;
use Ae3\AuthSecurity\Http\Controllers\PasswordController;
use Ae3\AuthSecurity\Http\Controllers\RecoveryCodeController;
use Illuminate\Support\Facades\Route;

// Gestão de fatores MFA
Route::prefix('mfa')->group(function (): void {

    Route::get('contacts', [MfaContactController::class, 'index']);
    Route::get('factors', [FactorController::class, 'index']);
    Route::post('factors', [FactorController::class, 'store']);
    Route::post('factors/{factor}/confirm', [FactorController::class, 'confirm']);
    Route::delete('factors/{factor}', [FactorController::class, 'destroy']);
    Route::get('factors/alternatives', [FactorController::class, 'alternatives']);

    // Verificação (challenge + verify)
    Route::post('factors/{factor}/challenge', [MfaVerificationController::class, 'challenge']);
    Route::post('factors/{factor}/challenge/resend', [MfaVerificationController::class, 'resend']);
    Route::post('verify', [MfaVerificationController::class, 'verify']);
    Route::post('recovery-codes/verify', [MfaVerificationController::class, 'verifyRecovery']);

    // Códigos de recuperação
    Route::get('recovery-codes', [RecoveryCodeController::class, 'show']);
    Route::post('recovery-codes', [RecoveryCodeController::class, 'store']);

    // Recuperação assistida
    Route::post('assisted-recoveries', [AssistedRecoveryController::class, 'store']);
    Route::post('assisted-recoveries/{recovery}/release', [AssistedRecoveryController::class, 'release']);
    Route::post('assisted-recoveries/complete', [AssistedRecoveryController::class, 'complete']);
    Route::post('assisted-recoveries/{recovery}/refuse', [AssistedRecoveryController::class, 'refuse']);

});

// Políticas de organização
Route::get('organization-policies', [OrganizationPolicyController::class, 'index']);
Route::put('organization-policies', [OrganizationPolicyController::class, 'upsert']);

// Senha
Route::post('password', [PasswordController::class, 'change']);
