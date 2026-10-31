<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GatewayToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatewayTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->isActive() === true, 403, 'This account is not active.');

        $credentials = (new GatewayToken(
            (string) config('services.gateway.token_secret'),
            (int) config('services.gateway.token_ttl'),
        ))->issue($user);

        return response()->json([
            'data' => [
                ...$credentials,
                'url' => config('services.gateway.url'),
            ],
        ]);
    }
}
