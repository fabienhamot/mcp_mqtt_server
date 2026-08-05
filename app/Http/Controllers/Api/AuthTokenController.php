<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        if (! Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ])) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $tokenResult = $user->createToken($validated['device_name'], ['mcp:use']);

        return response()->json([
            'ok' => true,
            'token_type' => 'Bearer',
            'access_token' => $tokenResult->accessToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $token = $user->token();
        if ($token !== null) {
            $token->revoke();
        }

        return response()->json([
            'ok' => true,
            'message' => 'Token révoqué.',
        ]);
    }
}
