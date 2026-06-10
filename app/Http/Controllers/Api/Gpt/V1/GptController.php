<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Http\Controllers\Controller;
use App\Models\AiActionAuditLog;
use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class GptController extends Controller
{
    protected function apiClient(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    protected function apiUser(Request $request): User
    {
        return $request->attributes->get('api_user');
    }

    /**
     * Decode a base64 file payload sent by GPT Actions clients (which cannot
     * stream multipart/form-data binary content). Returns the raw bytes, or
     * a 422 JsonResponse if the payload is invalid or out of range.
     */
    protected function decodeBase64File(string $base64, int $maxBytes): string|JsonResponse
    {
        $contents = base64_decode($base64, true);

        if ($contents === false) {
            return response()->json(['error' => 'file_base64 is not valid base64.', 'field' => 'file_base64'], 422);
        }

        $size = strlen($contents);

        if ($size === 0 || $size > $maxBytes) {
            return response()->json([
                'error' => sprintf('Decoded file must be between 1 byte and %d MB.', intdiv($maxBytes, 1024 * 1024)),
                'field' => 'file_base64',
            ], 422);
        }

        return $contents;
    }

    /** Best-effort MIME type detection for decoded base64 content. */
    protected function detectMimeType(string $contents, string $extension): string
    {
        $extensionMimeMap = [
            'csv'  => 'text/csv',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        if (isset($extensionMimeMap[$extension])) {
            return $extensionMimeMap[$extension];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_buffer($finfo, $contents) ?: 'application/octet-stream';
        finfo_close($finfo);

        return $mime;
    }

    protected function audit(
        Request $request,
        string $action,
        string $entityType = null,
        int $entityId = null,
        string $riskLevel = 'low',
        string $inputSummary = null,
        string $outputSummary = null,
        string $status = 'success',
    ): void {
        $client = $this->apiClient($request);
        AiActionAuditLog::record(
            userId:        $this->apiUser($request)->id,
            source:        $client->source_type,
            action:        $action,
            apiClientId:   $client->id,
            entityType:    $entityType,
            entityId:      $entityId,
            riskLevel:     $riskLevel,
            inputSummary:  $inputSummary,
            outputSummary: $outputSummary,
            status:        $status,
            ip:            $request->ip(),
        );
    }
}
