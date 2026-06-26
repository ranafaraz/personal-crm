<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends GptController
{
    public function __invoke(Request $request): JsonResponse
    {
        $dbOk    = true;
        $dbError = null;
        try {
            \DB::select('SELECT 1');
            \DB::table('personal_access_tokens')->count();
        } catch (\Throwable $e) {
            $dbOk    = false;
            $dbError = $e->getMessage();
        }

        return response()->json([
            'status'    => $dbOk ? 'ok' : 'degraded',
            'version'   => 'v1',
            'timestamp' => now()->toISOString(),
            'checks'    => [
                'database'               => $dbOk ? 'ok' : 'error',
                'personal_access_tokens' => $dbOk ? 'ok' : $dbError,
            ],
        ], $dbOk ? 200 : 503);
    }
}
