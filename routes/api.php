<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ChargeValidationController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\ExpenseParticipantController;
use App\Http\Controllers\Api\V1\PublicExpenseController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::post('/public/expenses', [PublicExpenseController::class, 'store'])
    ->middleware('throttle:public-create-expense');

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
        Route::get('/expenses/{hash}', [PublicExpenseController::class, 'show']);
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
            ->middleware('throttle:public-charge-action');
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

        /** @deprecated Legado: times / membros (fora do fluxo principal de cobrança). */
        Route::prefix('teams')->group(function () {
            Route::post('/', [TeamController::class, 'store']);
            Route::get('/', [TeamController::class, 'index']);
            Route::get('/{team}', [TeamController::class, 'show']);
            Route::get('/{team}/dashboard', [TeamController::class, 'dashboard']);

            Route::post('/{team}/members', [TeamMemberController::class, 'store']);
            Route::delete('/{team}/members/{member}', [TeamMemberController::class, 'destroy']);
        });
    });

});
