<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Charge\ChargeActionAudience;
use App\Actions\Charge\RejectChargeAction;
use App\Actions\Charge\ValidateChargeAction;
use App\Exceptions\HttpApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChargeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Charge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChargeValidationController extends Controller
{
    public function validateCharge(Charge $charge, ValidateChargeAction $validateChargeAction): JsonResponse
    {
        $this->authorizeAdmin($charge);

        $charge = $validateChargeAction->execute($charge, ChargeActionAudience::TEAM_ADMIN);

        return ApiResponse::success([
            'charge' => (new ChargeResource($charge->load('teamMember')))->resolve(),
        ], 'Pagamento validado.');
    }

    public function reject(Request $request, Charge $charge, RejectChargeAction $rejectChargeAction): JsonResponse
    {
        $this->authorizeAdmin($charge);

        $charge = $rejectChargeAction->execute(
            $charge,
            $request->input('reason'),
            ChargeActionAudience::TEAM_ADMIN,
        );

        return ApiResponse::success([
            'charge' => (new ChargeResource($charge->load('teamMember')))->resolve(),
        ], 'Comprovante rejeitado.');
    }

    public function downloadProof(Charge $charge): StreamedResponse|JsonResponse
    {
        $this->authorizeAdmin($charge);

        $proof = $charge->latestProof();
        if (! $proof) {
            return ApiResponse::error('No proof found.', 'NOT_FOUND', 404);
        }

        return Storage::disk('local')->download($proof->file_path, $proof->original_filename);
    }

    /**
     * @throws HttpApiException
     */
    private function authorizeAdmin(Charge $charge): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        $expense = $charge->expense;
        if (! $expense) {
            throw new HttpApiException('Not found.', 'NOT_FOUND', 404);
        }

        $membership = $expense->team->members()->where('user_id', $user->id)->first();
        if (! $membership || $membership->role !== 'admin') {
            throw new HttpApiException('Forbidden.', 'FORBIDDEN', 403);
        }
    }
}
