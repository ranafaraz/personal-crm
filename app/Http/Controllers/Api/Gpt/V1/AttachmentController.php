<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\ApiAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends GptController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filename'   => 'required|string|max:500',
            'public_url' => 'required|string|max:2048',
            'mime_type'  => ['required', 'string', Rule::in(ApiAttachment::ALLOWED_MIME_TYPES)],
            'size_bytes' => 'required|integer|min:1|max:' . ApiAttachment::MAX_SIZE_BYTES,
            'category'   => ['nullable', Rule::in(ApiAttachment::CATEGORIES)],
            'notes'      => 'nullable|string|max:2000',
        ]);

        // Validate the URL is publicly reachable (scheme + no private IPs)
        $urlError = ApiAttachment::validateUrl($data['public_url']);
        if ($urlError) {
            return response()->json(['error' => $urlError, 'field' => 'public_url'], 422);
        }

        $user     = $this->apiUser($request);
        $client   = $this->apiClient($request);
        $category = $data['category'] ?? 'other';

        // Detect sensitive document warnings
        $warnings = ApiAttachment::detectSensitiveWarnings($data['filename'], $category);
        $status   = count($warnings) > 0 ? 'warning' : 'valid';

        $attachment = ApiAttachment::create([
            'tenant_id'             => $user->tenant_id,
            'user_id'               => $user->id,
            'added_by_api_client_id'=> $client->id,
            'filename'              => $data['filename'],
            'public_url'            => $data['public_url'],
            'mime_type'             => $data['mime_type'],
            'size_bytes'            => $data['size_bytes'],
            'category'              => $category,
            'notes'                 => $data['notes'] ?? null,
            'validation_status'     => $status,
            'validation_warnings'   => count($warnings) > 0 ? $warnings : null,
        ]);

        $this->audit($request, 'create_attachment', 'api_attachment', $attachment->id, 'low',
            "filename={$attachment->filename}, size={$attachment->size_bytes}",
            "id={$attachment->id}, status={$status}");

        return response()->json([
            'data'    => $this->format($attachment),
            'message' => count($warnings) > 0
                ? 'Attachment registered with warnings. Review before attaching to cold outreach.'
                : 'Attachment registered.',
        ], 201);
    }

    /** Accept a binary file upload (or base64 JSON payload) and store it on CRM-controlled disk. */
    public function upload(Request $request): JsonResponse
    {
        if ($dropped = $this->rejectIfBodyDropped($request)) {
            return $dropped;
        }

        if ($request->hasFile('file')) {
            return $this->uploadFromMultipart($request);
        }

        return $this->uploadFromBase64($request);
    }

    private function uploadFromMultipart(Request $request): JsonResponse
    {
        $request->validate([
            'file'     => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,gif,webp|max:20480',
            'category' => ['nullable', Rule::in(ApiAttachment::CATEGORIES)],
            'notes'    => 'nullable|string|max:2000',
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType() ?: $file->getClientMimeType();

        if (! in_array($mime, ApiAttachment::ALLOWED_MIME_TYPES, true)) {
            return response()->json(['error' => "Unsupported MIME type: {$mime}"], 422);
        }

        return $this->persistAttachment(
            $request,
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName(),
            $mime,
        );
    }

    /**
     * Upload via a base64-encoded JSON payload. GPT Actions clients cannot
     * stream multipart/form-data file content, so this is the path they use
     * to send a real file from the conversation.
     */
    private function uploadFromBase64(Request $request): JsonResponse
    {
        $request->validate([
            'file_base64' => 'required|string',
            'filename'    => 'required|string|max:255',
            'category'    => ['nullable', Rule::in(ApiAttachment::CATEGORIES)],
            'notes'       => 'nullable|string|max:2000',
        ]);

        $contents = $this->decodeBase64File($request->input('file_base64'), ApiAttachment::MAX_SIZE_BYTES);
        if ($contents instanceof JsonResponse) return $contents;

        $filename  = basename($request->input('filename'));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime      = $this->detectMimeType($contents, $extension);

        if (! in_array($mime, ApiAttachment::ALLOWED_MIME_TYPES, true)) {
            return response()->json(['error' => "Unsupported MIME type: {$mime}"], 422);
        }

        return $this->persistAttachment($request, $contents, $filename, $mime);
    }

    /** Create the attachment record, store the file bytes, and build the response. */
    private function persistAttachment(Request $request, string $contents, string $filename, string $mime): JsonResponse
    {
        $user     = $this->apiUser($request);
        $client   = $this->apiClient($request);
        $size     = strlen($contents);
        $category = $request->input('category', 'other');

        $warnings = ApiAttachment::detectSensitiveWarnings($filename, $category);
        $status   = count($warnings) > 0 ? 'warning' : 'valid';

        // Create record first to use its ID in the storage path
        $attachment = ApiAttachment::create([
            'tenant_id'             => $user->tenant_id,
            'user_id'               => $user->id,
            'added_by_api_client_id'=> $client->id,
            'filename'              => $filename,
            'public_url'            => '', // set after we know the ID
            'mime_type'             => $mime,
            'size_bytes'            => $size,
            'category'              => $category,
            'notes'                 => $request->input('notes'),
            'validation_status'     => $status,
            'validation_warnings'   => count($warnings) > 0 ? $warnings : null,
        ]);

        $sanitized   = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $storagePath = "api-attachments/{$attachment->id}/{$sanitized}";

        Storage::disk('local')->put("private/{$storagePath}", $contents);

        $downloadUrl = rtrim(config('app.url'), '/') . "/api/gpt/v1/attachments/{$attachment->id}/download";

        $attachment->update([
            'file_path'    => $storagePath,
            'storage_disk' => 'local',
            'public_url'   => $downloadUrl,
        ]);

        $this->audit($request, 'upload_attachment', 'api_attachment', $attachment->id, 'low',
            "filename={$filename}, size={$size}",
            "id={$attachment->id}, status={$status}");

        return response()->json([
            'data'    => $this->format($attachment),
            'message' => count($warnings) > 0
                ? 'File uploaded to CRM storage with warnings. Review before attaching to cold outreach.'
                : 'File uploaded to CRM storage.',
        ], 201);
    }

    /** Stream a locally-stored attachment file. Requires attachments:read scope. */
    public function download(Request $request, int $id): JsonResponse|StreamedResponse
    {
        $user       = $this->apiUser($request);
        $attachment = ApiAttachment::where('user_id', $user->id)->findOrFail($id);

        if (! $attachment->hasLocalFile()) {
            return response()->json([
                'download_url' => $attachment->public_url,
                'filename'     => $attachment->filename,
            ]);
        }

        $disk = $attachment->storage_disk ?? 'local';
        $path = "private/{$attachment->file_path}";

        if (! Storage::disk($disk)->exists($path)) {
            return response()->json(['error' => 'File not found on server.'], 404);
        }

        return Storage::disk($disk)->download($path, $attachment->filename, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user       = $this->apiUser($request);
        $attachment = ApiAttachment::where('user_id', $user->id)->findOrFail($id);

        return response()->json(['data' => $this->format($attachment)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user       = $this->apiUser($request);
        $attachment = ApiAttachment::where('user_id', $user->id)->findOrFail($id);

        $this->audit($request, 'delete_attachment', 'api_attachment', $attachment->id, 'medium',
            "id={$attachment->id}", 'deleted');

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    public function format(ApiAttachment $a): array
    {
        return [
            'id'                   => $a->id,
            'filename'             => $a->filename,
            'public_url'           => $a->public_url,
            'mime_type'            => $a->mime_type,
            'size_bytes'           => $a->size_bytes,
            'category'             => $a->category,
            'notes'                => $a->notes,
            'validation_status'    => $a->validation_status,
            'validation_warnings'  => $a->validation_warnings ?? [],
            'created_at'           => $a->created_at?->toISOString(),
        ];
    }
}
