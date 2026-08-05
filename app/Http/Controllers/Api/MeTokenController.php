<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Token;
use Throwable;

class MeTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $tokens = $user->tokens()
            ->where('revoked', false)
            ->latest()
            ->get();

        return response()->json([
            'ok' => true,
            'count' => $tokens->count(),
            'tokens' => $tokens->map(fn (Token $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->scopes,
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $user->createToken($validated['name'], ['mcp:use']);

            return response()->json([
                'ok' => true,
                'message' => 'Token créé — conservez-le, il ne sera plus affiché.',
                'token' => [
                    'name' => $validated['name'],
                    'access_token' => $result->accessToken,
                    'token_type' => 'Bearer',
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Échec création token : '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $tokenId): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $token = $user->tokens()->where('id', $tokenId)->first();

        if ($token === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Token introuvable.',
            ], 404);
        }

        $token->revoke();

        return response()->json([
            'ok' => true,
            'message' => 'Token révoqué.',
        ]);
    }
}
