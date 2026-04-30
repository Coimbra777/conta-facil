<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Charge\ChargeActionAudience;
use App\Actions\Charge\RejectChargeAction;
use App\Actions\Charge\ValidateChargeAction;
use App\Exceptions\HttpApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RejectChargeRequest;
use App\Http\Resources\ChargeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Charge;
use App\Support\ChargeProofHttpResponse;
use App\Support\ExpenseAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChargeValidationController extends Controller
{
    public function validateCharge(Charge $charge, ValidateChargeAction $validateChargeAction): JsonResponse
    {
        $this->authorizeExpenseOwner($charge);

        $charge = $validateChargeAction->execute($charge, ChargeActionAudience::EXPENSE_OWNER);

        return ApiResponse::success([
            'charge' => (new ChargeResource($charge->load(Charge::EAGER_WITH_PARTICIPANT)))->resolve(),
        ], 'Pagamento validado.');
    }

    public function reject(RejectChargeRequest $request, Charge $charge, RejectChargeAction $rejectChargeAction): JsonResponse
    {
        $this->authorizeExpenseOwner($charge);

        $charge = $rejectChargeAction->execute(
            $charge,
            $request->input('reason'),
            ChargeActionAudience::EXPENSE_OWNER,
        );

        return ApiResponse::success([
            'charge' => (new ChargeResource($charge->load(Charge::EAGER_WITH_PARTICIPANT)))->resolve(),
        ], 'Comprovante rejeitado.');
    }

    public function downloadProof(Charge $charge): BinaryFileResponse|JsonResponse
    {
        $this->authorizeExpenseOwner($charge);

        return ChargeProofHttpResponse::latest($charge, false);
    }

    public function viewLatestProof(Charge $charge): BinaryFileResponse|JsonResponse
    {
        $this->authorizeExpenseOwner($charge);

        return ChargeProofHttpResponse::latest($charge, true);
    }

    /**
     * @throws HttpApiException
     */
    private function authorizeExpenseOwner(Charge $charge): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        $expense = $charge->expense;
        if (! $expense) {
            throw new HttpApiException('Registro não encontrado.', 'NOT_FOUND', 404);
        }

        if (! ExpenseAuthorizer::canManage($user, $expense)) {
            throw new HttpApiException(
                'Você não tem permissão para realizar esta ação.',
                'FORBIDDEN',
                403,
            );
        }
    }
}
