<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expense\DeleteExpenseParticipantAction;
use App\Actions\Expense\UpdateExpenseParticipantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyExpenseParticipantRequest;
use App\Http\Requests\Api\V1\UpdateExpenseParticipantRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use Illuminate\Http\JsonResponse;

class ExpenseParticipantController extends Controller
{
    public function update(
        UpdateExpenseParticipantRequest $request,
        Expense $expense,
        ExpenseParticipant $participant,
        UpdateExpenseParticipantAction $action,
    ): JsonResponse {
        $expense = $action->execute($participant, array_filter($request->validated(), fn ($v) => $v !== null));

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ], 'Participante atualizado.');
    }

    public function destroy(
        DestroyExpenseParticipantRequest $request,
        Expense $expense,
        ExpenseParticipant $participant,
        DeleteExpenseParticipantAction $action,
    ): JsonResponse {
        $expense = $action->execute($participant);

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ], 'Participante removido.');
    }
}
