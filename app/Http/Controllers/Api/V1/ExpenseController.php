<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expense\AddExpenseParticipantsAction;
use App\Actions\Expense\CreateExpenseAction;
use App\Actions\Expense\DeleteExpenseAction;
use App\Actions\Expense\UpdateExpenseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddExpenseParticipantsRequest;
use App\Http\Requests\Api\V1\DestroyExpenseRequest;
use App\Http\Requests\Api\V1\ShowExpenseRequest;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Requests\Api\V1\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $expenses = Expense::query()
            ->where('created_by', $user->id)
            ->with([
                'charges.expenseParticipant',
                'charges.teamMember',
                'charges.paymentProofs',
            ])
            ->latest()
            ->get();

        return ApiResponse::success([
            'expenses' => ExpenseResource::collection($expenses)->resolve(),
        ], 'Despesas carregadas com sucesso.');
    }

    public function store(StoreExpenseRequest $request, CreateExpenseAction $createExpenseAction): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $expense = $createExpenseAction->execute($user, $request->validated());

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ], 'Despesa criada com sucesso.', 201);
    }

    public function show(ShowExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense->load([
            'charges.expenseParticipant',
            'charges.teamMember',
            'charges.paymentProofs',
        ]);

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ]);
    }

    public function update(
        UpdateExpenseRequest $request,
        Expense $expense,
        UpdateExpenseAction $updateExpenseAction,
    ): JsonResponse {
        $expense = $updateExpenseAction->execute($expense, $request->validated());

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ], 'Despesa atualizada.');
    }

    public function destroy(
        DestroyExpenseRequest $request,
        Expense $expense,
        DeleteExpenseAction $deleteExpenseAction,
    ): JsonResponse {
        $deleteExpenseAction->execute($expense);

        return ApiResponse::success(null, 'Cobrança excluída.');
    }

    public function addParticipants(
        AddExpenseParticipantsRequest $request,
        Expense $expense,
        AddExpenseParticipantsAction $action,
    ): JsonResponse {
        $expense = $action->execute($expense, $request->input('participants', []));

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ], 'Participantes atualizados.');
    }
}
