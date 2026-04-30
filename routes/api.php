<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ChargeValidationController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\ExpenseParticipantController;
use App\Http\Controllers\Api\V1\PublicExpenseController;
use Illuminate\Support\Facades\Route;

/** Criação anônima em standby — middleware retorna 410; remover `public-expense-create-standby` para reativar. */
Route::post('/public/expenses', [PublicExpenseController::class, 'store'])
    ->middleware(['throttle:public-create-expense', 'public-expense-create-standby']);

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:auth-register');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:auth-login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::prefix('public')->group(function () {
        Route::get('/expenses/{hash}', [PublicExpenseController::class, 'show'])
            ->middleware('throttle:public-expense-show');
        Route::patch('/expenses/{hash}/close', [PublicExpenseController::class, 'closeExpense'])
            ->middleware('throttle:public-sensitive-mutation');
        Route::patch('/expenses/{hash}', [PublicExpenseController::class, 'updateExpense'])
            ->middleware('throttle:public-sensitive-mutation');
        Route::post('/expenses/{hash}/participants', [PublicExpenseController::class, 'addParticipants'])
            ->middleware('throttle:public-sensitive-mutation');
        Route::post('/expenses/{hash}/validate-participant', [PublicExpenseController::class, 'validateParticipantPublic'])
            ->middleware('throttle:public-validate-participant');
        Route::post('/expenses/{hash}/submit-proof', [PublicExpenseController::class, 'submitProofPublic'])
            ->middleware('throttle:public-submit-proof');
        Route::patch('/charges/{charge}/validate', [PublicExpenseController::class, 'validateCharge'])
            ->middleware('throttle:public-charge-action');
        Route::patch('/charges/{charge}/reject', [PublicExpenseController::class, 'rejectCharge'])
            ->middleware('throttle:public-charge-action');
        Route::get('/charges/{charge}/proof', [PublicExpenseController::class, 'downloadProof'])
            ->middleware('throttle:public-proof-download');
        Route::get('/charges/{charge}/proofs/latest/view', [PublicExpenseController::class, 'viewLatestProof'])
            ->middleware('throttle:public-proof-preview');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show']);
        Route::patch('/expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
        Route::post('/expenses/{expense}/participants', [ExpenseController::class, 'addParticipants']);
        Route::patch('/expenses/{expense}/participants/{participant}', [ExpenseParticipantController::class, 'update']);
        Route::delete('/expenses/{expense}/participants/{participant}', [ExpenseParticipantController::class, 'destroy']);

        Route::patch('/charges/{charge}/validate', [ChargeValidationController::class, 'validateCharge']);
        Route::patch('/charges/{charge}/reject', [ChargeValidationController::class, 'reject']);
        Route::get('/charges/{charge}/proof', [ChargeValidationController::class, 'downloadProof']);
        Route::get('/charges/{charge}/proofs/latest/view', [ChargeValidationController::class, 'viewLatestProof']);
    });

});
