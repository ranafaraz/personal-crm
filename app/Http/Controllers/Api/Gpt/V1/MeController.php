<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends GptController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user   = $this->apiUser($request);
        $client = $this->apiClient($request);
        $token  = $request->attributes->get('api_token');

        $tokenExpiresAt  = $token?->expires_at ?? null;
        $daysUntilExpiry = $tokenExpiresAt
            ? (int) now()->diffInDays($tokenExpiresAt, absolute: false)
            : null;

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'client' => [
                'id'          => $client->id,
                'name'        => $client->name,
                'source_type' => $client->source_type,
                'scopes'      => $client->scopes,
            ],
            'token' => [
                'expires_at'      => $tokenExpiresAt?->utc()->toIso8601String(),
                'days_until_expiry' => $daysUntilExpiry,
                'expiry_warning'  => $daysUntilExpiry !== null && $daysUntilExpiry <= 30
                    ? "Token expires in {$daysUntilExpiry} day(s). Rotate before expiry to avoid disruption."
                    : null,
            ],
        ]);
    }
}
