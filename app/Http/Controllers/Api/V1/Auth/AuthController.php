<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->refresh()),
            'token' => $token,
        ], 201);
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
            return response()->json([
                'message' => 'Não encontramos uma conta com este e-mail.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 422);
        }

        if (! Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'E-mail ou senha inválidos.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        Auth::login($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Você saiu da conta.',
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'user' => new UserResource(Auth::user()),
        ]);
    }
}
