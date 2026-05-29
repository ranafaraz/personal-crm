<?php

namespace App\Http\Controllers\Api\Gpt\V1\Social;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\SocialAccount;
use App\Services\Social\LinkedInOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkedInAccountController extends GptController
{
    public function __construct(private LinkedInOAuthService $oauth) {}

    /** List connected LinkedIn accounts for the authenticated user. */
    public function index(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $accounts = SocialAccount::with('provider')
            ->where('user_id', $user->id)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->orderByDesc('is_default')
            ->get()
            ->map(fn ($a) => $this->formatAccount($a));

        return response()->json(['accounts' => $accounts]);
    }

    /** Verify (introspect) a LinkedIn account and refresh its capability flags. */
    public function verify(Request $request, int $id): JsonResponse
    {
        $user    = $this->apiUser($request);
        $account = $this->findAccount($user->id, $id);

        if (! $account) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        $app = $account->oauthApp;
        if (! $app) {
            return response()->json(['error' => 'OAuth app not found for this account.'], 422);
        }

        $result = $this->oauth->verifyAccount($account, $app);

        $this->audit($request, 'linkedin_account_verify', SocialAccount::class, $id);

        return response()->json([
            'account_id'     => $id,
            'active'         => $result['active'],
            'granted_scopes' => $result['granted_scopes'],
            'missing_scopes' => $result['missing_scopes'],
            'capabilities'   => $result['capabilities'],
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findAccount(int $userId, int $id): ?SocialAccount
    {
        return SocialAccount::with(['provider', 'oauthApp'])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->first();
    }

    private function formatAccount(SocialAccount $a): array
    {
        return [
            'id'               => $a->id,
            'display_name'     => $a->display_name,
            'public_profile_url' => $a->public_profile_url,
            'status'           => $a->status,
            'is_default'       => (bool) $a->is_default,
            'token_expires_at' => $a->token_expires_at?->toIso8601String(),
            'capabilities'     => $a->capabilities ?? [],
            'missing_scopes'   => $a->missing_scopes ?? [],
        ];
    }
}
