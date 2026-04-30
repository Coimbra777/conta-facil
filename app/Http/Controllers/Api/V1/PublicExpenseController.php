<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Charge\ChargeActionAudience;
use App\Actions\Charge\RejectChargeAction;
use App\Actions\Charge\SubmitPaymentProofAction;
use App\Actions\Charge\ValidateChargeAction;
use App\Actions\Expense\AddPublicExpenseParticipantsAction;
use App\Http\Controllers\Concerns\AuthorizesPublicExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddPublicExpenseParticipantsRequest;
use App\Http\Requests\Api\V1\RejectChargeRequest;
use App\Http\Requests\Api\V1\StorePublicExpenseRequest;
use App\Http\Requests\Api\V1\SubmitPublicProofRequest;
use App\Http\Requests\Api\V1\UpdatePublicExpenseRequest;
use App\Http\Requests\Api\V1\ValidateParticipantPublicRequest;
use App\Http\Resources\CreatedPublicExpenseResource;
use App\Http\Resources\PublicExpenseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Charge;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Services\PublicExpenseCreatorService;
use App\Support\PublicParticipantChargeResolver;
use App\Support\SafeDownloadFilename;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicExpenseController extends Controller
{
    use AuthorizesPublicExpense;

    public function store(StorePublicExpenseRequest $request, PublicExpenseCreatorService $creator): JsonResponse
    {
        $data = $request->validated();
        $expense = $creator->create([
            'owner_name' => $data['owner_name'],
            'owner_phone' => $data['owner_phone'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'pix_key' => $data['pix_key'],
            'pix_qr_code' => $data['pix_qr_code'] ?? null,
            'due_date' => $data['due_date'],
            'participants' => $data['participants'],
        ]);

        return ApiResponse::success(
            ['expense' => (new CreatedPublicExpenseResource($expense))->resolve()],
            'Cobrança criada com sucesso.',
            201,
        );
    }

    public function show(Request $request, string $hash): JsonResponse
    {
        $expense = Expense::where('public_hash', $hash)
            ->with(['charges.expenseParticipant', 'charges.teamMember', 'charges.paymentProofs'])
            ->firstOrFail();

        return ApiResponse::success([
            'expense' => (new PublicExpenseResource($expense))->resolve(),
        ]);
    }

    public function closeExpense(Request $request, string $hash): JsonResponse
    {
        $expense = Expense::where('public_hash', $hash)->firstOrFail();

        $this->authorizeManageToken($request, $expense);

        if ($expense->status === 'closed') {
            return ApiResponse::error(
                'Esta despesa ja foi finalizada.',
                'INVALID_EXPENSE_STATE',
                422,
            );
        }

        $charges = $expense->charges()->get();
        if ($charges->isEmpty()) {
            return ApiResponse::error(
                'Nao ha participantes para finalizar.',
                'INVALID_EXPENSE_STATE',
                422,
            );
        }

        if ($charges->contains(fn (Charge $c) => $c->status !== 'validated')) {
            return ApiResponse::error(
                'So e possivel finalizar quando todos os participantes estiverem com pagamento validado.',
                'INVALID_EXPENSE_STATE',
                422,
            );
        }

        $expense->update(['status' => 'closed']);
        $expense->load(['charges.expenseParticipant', 'charges.teamMember', 'charges.paymentProofs']);

        return ApiResponse::success([
            'expense' => (new PublicExpenseResource($expense))->resolve(),
        ], 'Despesa finalizada.');
    }

    public function updateExpense(UpdatePublicExpenseRequest $request, string $hash, ExpenseService $expenseService): JsonResponse
    {
        $expense = Expense::where('public_hash', $hash)->firstOrFail();

        $this->authorizeManageToken($request, $expense);
        $this->assertExpenseOpen($expense);

        $data = $request->validated();
        $oldTotal = (float) $expense->total_amount;
        $totalAmount = (float) $data['amount'];
        $totalChanged = abs($totalAmount - $oldTotal) > 0.001;

        if ($totalChanged && $expense->charges()->where('status', '!=', 'pending')->exists()) {
            return ApiResponse::error(
                'Nao e possivel alterar o valor total enquanto houver cobranca com status diferente de pendente.',
                'INVALID_EXPENSE_STATE',
                422,
            );
        }

        $chargeCount = $expense->charges()->count();
        $amountPerMember = $chargeCount > 0
            ? floor($totalAmount / $chargeCount * 100) / 100
            : $totalAmount;

        $payload = [
            'description' => $data['description'],
            'total_amount' => $totalAmount,
            'amount_per_member' => $amountPerMember,
            'due_date' => $data['due_date'],
            'pix_key' => $data['pix_key'],
        ];
        if (array_key_exists('pix_qr_code', $data)) {
            $payload['pix_qr_code'] = $data['pix_qr_code'];
        }

        DB::transaction(function () use ($expense, $payload, $totalChanged, $expenseService) {
            $expense->update($payload);
            $expense->refresh();

            if ($totalChanged) {
                $expenseService->redistributeChargeAmounts($expense);
            } else {
                $expense->charges()->update([
                    'description' => $expense->description,
                    'due_date' => $expense->due_date,
                ]);
            }
        });

        $expense->refresh()->load(['charges.expenseParticipant', 'charges.teamMember', 'charges.paymentProofs']);

        return ApiResponse::success([
            'expense' => (new PublicExpenseResource($expense))->resolve(),
        ], 'Despesa atualizada.');
    }

    public function addParticipants(
        AddPublicExpenseParticipantsRequest $request,
        string $hash,
        AddPublicExpenseParticipantsAction $action,
    ): JsonResponse {
        $expense = Expense::where('public_hash', $hash)->firstOrFail();

        $this->authorizeManageToken($request, $expense);
        $this->assertExpenseOpen($expense);

        $participants = $request->input('participants', []);

        $expense = $action->execute($expense, $participants);

        $expense->refresh()->load(['charges.expenseParticipant', 'charges.teamMember', 'charges.paymentProofs']);

        return ApiResponse::success([
            'expense' => (new PublicExpenseResource($expense))->resolve(),
        ], 'Participantes adicionados.', 201);
    }

    public function validateParticipantPublic(ValidateParticipantPublicRequest $request, string $hash): JsonResponse
    {
        $expense = Expense::byHash($hash)->first();

        if (! $expense) {
            return ApiResponse::error('Registro não encontrado.', 'NOT_FOUND', 404);
        }

        $this->assertExpenseOpen($expense);

        $validated = $request->validated();
        $charge = PublicParticipantChargeResolver::findChargeForExactPublicParticipant(
            $expense,
            $validated['name'],
            $validated['phone'],
        );

        if ($charge === null) {
            return ApiResponse::error(
                'Participante não encontrado nesta despesa.',
                'PARTICIPANT_NOT_FOUND',
                422,
            );
        }

        $charge->refresh();

        $status = $charge->status;

        return ApiResponse::success([
            'status' => $status,
            'rejection_reason' => $charge->rejection_reason,
            'can_submit_proof' => in_array($status, ['pending', 'rejected'], true),
        ], $this->messageForValidateParticipantStatus($status));
    }

    public function submitProofPublic(
        SubmitPublicProofRequest $request,
        string $hash,
        SubmitPaymentProofAction $action,
    ): JsonResponse {
        $expense = Expense::where('public_hash', $hash)->firstOrFail();

        $this->assertExpenseOpen($expense);

        $validated = $request->validated();

        $charge = $action->execute($expense, $validated['name'], $validated['phone'], $request->file('proof'));

        return ApiResponse::success([
            'status' => $charge->status,
            'rejection_reason' => $charge->rejection_reason,
        ], 'Comprovante enviado. Aguardando aprovação do responsável.', 201);
    }

    public function validateCharge(Request $request, Charge $charge, ValidateChargeAction $validateChargeAction): JsonResponse
    {
        $expense = $this->authorizeManage($request, $charge->expense);
        $this->assertExpenseOpen($expense);

        $charge = $validateChargeAction->execute($charge, ChargeActionAudience::PUBLIC_MANAGE);

        $charge->load('teamMember');

        return ApiResponse::success([
            'charge' => [
                'id' => $charge->id,
                'status' => $charge->status,
                'member' => [
                    'id' => $charge->teamMember?->id,
                    'name' => $charge->teamMember?->name,
                ],
            ],
        ], 'Pagamento validado.');
    }

    public function rejectCharge(RejectChargeRequest $request, Charge $charge, RejectChargeAction $rejectChargeAction): JsonResponse
    {
        $expense = $this->authorizeManage($request, $charge->expense);
        $this->assertExpenseOpen($expense);

        $charge = $rejectChargeAction->execute($charge, $request->input('reason'), ChargeActionAudience::PUBLIC_MANAGE);

        return ApiResponse::success([
            'charge' => [
                'id' => $charge->id,
                'status' => $charge->status,
            ],
        ], 'Comprovante rejeitado.');
    }

    public function downloadProof(Request $request, Charge $charge): StreamedResponse|JsonResponse
    {
        $this->authorizeManage($request, $charge->expense);

        $proof = $charge->latestProof();
        if (! $proof) {
            return ApiResponse::error('No proof found.', 'NOT_FOUND', 404);
        }

        $downloadName = SafeDownloadFilename::forProof((string) $proof->mime_type, $proof->original_filename);

        return Storage::disk('local')->download($proof->file_path, $downloadName);
    }

    private function messageForValidateParticipantStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Você ainda não enviou comprovante.',
            'proof_sent' => 'Comprovante já enviado. Aguarde aprovação.',
            'rejected' => 'Comprovante rejeitado. Envie novamente.',
            'validated' => 'Pagamento já confirmado.',
            default => 'Status desconhecido.',
        };
    }
}
