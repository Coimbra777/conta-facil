<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expense\AddParticipantsToExpenseAction;
use App\Actions\Expense\CreateExpenseAction;
use App\Exceptions\HttpApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddTeamExpenseParticipantsRequest;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Requests\Api\V1\UpdateExpenseRequest;
use App\Http\Resources\ChargeResource;
use App\Http\Resources\ExpenseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expense;
use App\Models\Team;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function store(StoreExpenseRequest $request, Team $team, CreateExpenseAction $createExpenseAction): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $membership = $team->members()->where('user_id', $user->id)->first();
        if (! $membership || $membership->role !== 'admin') {
            throw new HttpApiException('Forbidden.', 'FORBIDDEN', 403);
        }

        $expense = $createExpenseAction->execute($team, $user, $request->validated());

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ], 'Despesa criada com sucesso.', 201);
    }

    public function index(Team $team): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $team->members()->where('user_id', $user->id)->exists()) {
            throw new HttpApiException('Forbidden.', 'FORBIDDEN', 403);
        }

        $expenses = $team->expenses()->latest()->get();

        return ApiResponse::success([
            'expenses' => ExpenseResource::collection($expenses)->resolve(),
        ]);
    }

    public function show(Team $team, Expense $expense): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $team->members()->where('user_id', $user->id)->exists()) {
            throw new HttpApiException('Forbidden.', 'FORBIDDEN', 403);
        }

        if ($expense->team_id !== $team->id) {
            throw new HttpApiException('Not found.', 'NOT_FOUND', 404);
        }

        $expense->load('charges.teamMember', 'charges.paymentProofs');

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ]);
    }

    public function update(
        UpdateExpenseRequest $request,
        Team $team,
        Expense $expense,
        ExpenseService $expenseService,
    ): JsonResponse {
        $expense = $expenseService->updateExpense($expense, $request->validated());

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ], 'Despesa atualizada.');
    }

    public function addParticipants(
        AddTeamExpenseParticipantsRequest $request,
        Team $team,
        Expense $expense,
        AddParticipantsToExpenseAction $action,
    ): JsonResponse {
        $expense = $action->execute($team, $expense, $request->input('participants', []));

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ], 'Participantes atualizados.');
    }

    public function showDirect(Expense $expense): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $expense->team->members()->where('user_id', $user->id)->exists()) {
            throw new HttpApiException('Forbidden.', 'FORBIDDEN', 403);
        }

        $expense->load('charges.teamMember', 'charges.paymentProofs');

        return ApiResponse::success([
            'expense' => (new ExpenseResource($expense))->resolve(),
        ]);
    }

    public function members(Expense $expense): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $membership = $expense->team->members()->where('user_id', $user->id)->first();
        if (! $membership || $membership->role !== 'admin') {
            throw new HttpApiException('Forbidden.', 'FORBIDDEN', 403);
        }

        $expense->load('charges.teamMember', 'charges.paymentProofs');

        return ApiResponse::success([
            'charges' => ChargeResource::collection($expense->charges)->resolve(),
        ]);
    }
}
