<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $token = $this->issueToken($user);

        return ApiResponse::success([
            'user' => (new UserResource($user->refresh()))->resolve(),
            'token' => $token,
        ], 'Conta criada com sucesso.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        /**
         * Separa e-mail inexistente de senha incorreta para UX no MVP.
         * Trade-off: enumeração de e-mails cadastrados — mitigado por rate limit (`auth-login`).
         *
         * @see doc/SECURITY.md
         */
        $validated = $request->validated();
        $email = $validated['email'];
        $password = $validated['password'];

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return ApiResponse::error(
                'Não encontramos uma conta com este e-mail.',
                'ACCOUNT_NOT_FOUND',
                422,
            );
        }

        if (! Hash::check($password, $user->password)) {
            return ApiResponse::error(
                'E-mail ou senha inválidos.',
                'INVALID_CREDENTIALS',
                401,
            );
        }

        Auth::login($user);

        $token = $this->issueToken($user);

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve(),
            'token' => $token,
        ], 'Login realizado com sucesso.');
    }

    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $user->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Você saiu da conta.');
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success([
            'user' => (new UserResource(Auth::user()))->resolve(),
        ]);
    }

    private function issueToken(User $user): string
    {
        $expirationMinutes = (int) config('sanctum.expiration', 0);
        $expiresAt = $expirationMinutes > 0
            ? now()->addMinutes($expirationMinutes)
            : null;

        return $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;
    }
}
