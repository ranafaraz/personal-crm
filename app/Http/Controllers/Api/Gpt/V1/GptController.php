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

    /**
     * Detect when the HTTP body was silently discarded because it exceeded PHP's
     * post_max_size. When that happens PHP empties $_POST/$_FILES/php://input but
     * still reports the original Content-Length, so an upload arrives looking
     * "empty" and downstream validation returns a misleading "field is required"
     * error. Return a clear 413 so the agent knows the file was too large.
     */
    protected function rejectIfBodyDropped(Request $request): ?JsonResponse
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        $postMax       = $this->iniBytes(ini_get('post_max_size'));

        $bodyEmpty = $request->getContent() === ''
            && count($request->all()) === 0
            && ! $request->hasFile('file');

        if ($contentLength > 0 && $postMax > 0 && $contentLength > $postMax && $bodyEmpty) {
            return response()->json([
                'error'      => sprintf(
                    'Upload rejected: request body (%d bytes) exceeds the server limit of %d MB. '
                    . 'Base64 encoding inflates a file by ~33%%, so the original file must be under ~%d MB. '
                    . 'Send a smaller file, or register it by public_url instead.',
                    $contentLength,
                    intdiv($postMax, 1024 * 1024),
                    intdiv((int) ($postMax / 1.34), 1024 * 1024)
                ),
                'field'      => 'file_base64',
                'max_bytes'  => $postMax,
            ], 413);
        }

        return null;
    }

    /** Convert a PHP ini shorthand byte value (e.g. "30M", "256K") to bytes. */
    protected function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit   = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => (int) $value,
        };
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

    /**
     * Format documents linked via uploadDocument()/addDocumentLink() for an
     * email_draft or follow_up. These are reference documents only — they are
     * NOT sent with the email/follow-up. Only ApiAttachment records (created
     * via uploadAttachment and passed as attachment_ids/suggested_attachment_ids)
     * are sendable.
     */
    protected function formatLinkedDocuments(\Illuminate\Support\Collection $apiDocumentLinks): array
    {
        return $apiDocumentLinks
            ->filter(fn ($link) => $link->document !== null)
            ->map(function ($link) {
                $doc = $link->document;
                $cv  = $doc->currentVersion;

                return [
                    'document_id'   => $doc->id,
                    'name'          => $doc->name,
                    'document_type' => $doc->document_type,
                    'filename'      => $cv?->original_filename,
                    'mime_type'     => $cv?->mime_type,
                    'size_bytes'    => $cv?->size_bytes,
                    'download_url'  => rtrim(config('app.url'), '/') . "/api/gpt/v1/documents/{$doc->id}/download",
                    'linked_at'     => $link->created_at?->toISOString(),
                ];
            })
            ->values()
            ->toArray();
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
