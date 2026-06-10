<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\ApiAttachment;
use App\Models\ApiClient;
use App\Models\ApiDocument;
use App\Models\ApiDocumentLink;
use App\Models\ApiDocumentVersion;
use App\Models\Contact;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends GptController
{
    private const MAX_SIZE_KB = 20480; // 20 MB for Laravel's file validator
    private const MAX_SIZE_BYTES = 20 * 1024 * 1024;
    private const ALLOWED_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,gif,webp';

    // =========================================================================
    // CRUD
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $user  = $this->apiUser($request);
        $limit = min((int) $request->query('limit', 20), 100);

        $query = ApiDocument::where('user_id', $user->id)
            ->with(['currentVersion', 'links'])
            ->withCount('versions');

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->query('document_type'));
        }
        if ($request->filled('is_sensitive')) {
            $query->where('is_sensitive', filter_var($request->query('is_sensitive'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('q')) {
            $term = $request->query('q');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                                       ->orWhere('description', 'like', "%{$term}%"));
        }

        $docs = $query->orderByDesc('created_at')->limit($limit)->get();

        return response()->json([
            'data'  => $docs->map(fn ($d) => $this->format($d))->values(),
            'count' => $docs->count(),
        ]);
    }

    /** Handles both multipart/form-data (file upload) and application/json (URL registration). */
    public function store(Request $request): JsonResponse
    {
        if ($dropped = $this->rejectIfBodyDropped($request)) {
            return $dropped;
        }

        if ($request->hasFile('file')) {
            return $this->createFromUpload($request);
        }

        if ($request->filled('file_base64')) {
            return $this->createFromBase64($request);
        }

        return $this->createFromUrl($request);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $doc = ApiDocument::where('user_id', $this->apiUser($request)->id)
            ->with(['currentVersion', 'links'])
            ->withCount('versions')
            ->findOrFail($id);

        return response()->json(['data' => $this->format($doc)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'sometimes|string|max:500',
            'document_type' => ['sometimes', Rule::in(ApiDocument::DOCUMENT_TYPES)],
            'description'   => 'sometimes|nullable|string|max:2000',
        ]);

        $doc = ApiDocument::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        $patch = [];
        if (isset($data['name']))          $patch['name']          = $data['name'];
        if (isset($data['document_type'])) $patch['document_type'] = $data['document_type'];
        if (array_key_exists('description', $data)) $patch['description'] = $data['description'];

        if (!empty($patch)) {
            $doc->update($patch);
        }

        $this->audit($request, 'update_document', 'api_document', $doc->id, 'low',
            implode(',', array_keys($patch)), 'updated');

        $doc->load(['currentVersion', 'links']);
        $doc->loadCount('versions');

        return response()->json(['data' => $this->format($doc), 'message' => 'Document updated.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $doc = ApiDocument::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        $this->audit($request, 'delete_document', 'api_document', $doc->id, 'medium',
            "id={$doc->id}", 'soft-deleted');

        $doc->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    // =========================================================================
    // Download
    // =========================================================================

    public function download(Request $request, int $id): mixed
    {
        $doc = ApiDocument::where('user_id', $this->apiUser($request)->id)
            ->with('currentVersion')
            ->findOrFail($id);

        abort_unless($doc->currentVersion, 404, 'No version available for this document.');

        return $this->serveVersion($doc->currentVersion);
    }

    public function downloadVersion(Request $request, int $id, int $vid): mixed
    {
        $doc     = ApiDocument::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $version = ApiDocumentVersion::where('api_document_id', $doc->id)->where('id', $vid)->firstOrFail();

        return $this->serveVersion($version);
    }

    // =========================================================================
    // Versioning
    // =========================================================================

    public function listVersions(Request $request, int $id): JsonResponse
    {
        $doc      = ApiDocument::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $versions = ApiDocumentVersion::where('api_document_id', $doc->id)->orderBy('version_number')->get();

        return response()->json([
            'document_id'        => $doc->id,
            'current_version_id' => $doc->current_version_id,
            'data'               => $versions->map(fn ($v) => $this->formatVersion($v))->values(),
            'count'              => $versions->count(),
        ]);
    }

    public function addVersion(Request $request, int $id): JsonResponse
    {
        $user   = $this->apiUser($request);
        $client = $this->apiClient($request);
        $doc    = ApiDocument::where('user_id', $user->id)->findOrFail($id);

        $nextNum = (ApiDocumentVersion::where('api_document_id', $doc->id)->max('version_number') ?? 0) + 1;

        if ($request->hasFile('file')) {
            $request->validate([
                'file'          => 'required|file|max:' . self::MAX_SIZE_KB . '|mimes:' . self::ALLOWED_EXTENSIONS,
                'version_notes' => 'nullable|string|max:2000',
            ]);

            $file     = $request->file('file');
            $safe     = $this->sanitizeFilename($file->getClientOriginalName());
            $checksum = hash_file('sha256', $file->getRealPath());
            $path     = $file->storeAs("private/api-documents/{$doc->id}/v{$nextNum}", $safe, 'local');

            abort_unless($path, 500, 'Failed to store file.');

            $version = ApiDocumentVersion::create([
                'api_document_id'           => $doc->id,
                'version_number'            => $nextNum,
                'original_filename'         => $file->getClientOriginalName(),
                'mime_type'                 => $file->getMimeType() ?: $file->getClientMimeType(),
                'size_bytes'                => $file->getSize(),
                'checksum'                  => $checksum,
                'storage_path'              => $path,
                'upload_source'             => 'multipart',
                'version_notes'             => $request->input('version_notes'),
                'uploaded_by_api_client_id' => $client->id,
            ]);
        } else {
            $data = $request->validate([
                'public_url'    => 'required|url|max:2048',
                'mime_type'     => ['required', 'string', Rule::in(ApiDocumentVersion::ALLOWED_MIME_TYPES)],
                'size_bytes'    => 'required|integer|min:1|max:' . self::MAX_SIZE_BYTES,
                'checksum'      => 'nullable|string|max:64',
                'version_notes' => 'nullable|string|max:2000',
            ]);

            $urlError = ApiAttachment::validateUrl($data['public_url']);
            if ($urlError) {
                return response()->json(['error' => $urlError, 'field' => 'public_url'], 422);
            }

            $filename = basename(parse_url($data['public_url'], PHP_URL_PATH)) ?: $doc->name;

            $version = ApiDocumentVersion::create([
                'api_document_id'           => $doc->id,
                'version_number'            => $nextNum,
                'original_filename'         => $filename,
                'mime_type'                 => $data['mime_type'],
                'size_bytes'                => $data['size_bytes'],
                'checksum'                  => $data['checksum'] ?? null,
                'public_url'                => $data['public_url'],
                'upload_source'             => 'url',
                'version_notes'             => $data['version_notes'] ?? null,
                'uploaded_by_api_client_id' => $client->id,
            ]);
        }

        $doc->update(['current_version_id' => $version->id]);

        $this->audit($request, 'add_document_version', 'api_document', $doc->id, 'low',
            "version={$nextNum}", "version_id={$version->id}");

        return response()->json([
            'document_id' => $doc->id,
            'data'        => $this->formatVersion($version),
            'message'     => "Version {$nextNum} created. Previous versions remain accessible.",
        ], 201);
    }

    // =========================================================================
    // Links  (document ↔ entity associations)
    // =========================================================================

    public function listLinks(Request $request, int $id): JsonResponse
    {
        $doc   = ApiDocument::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $links = ApiDocumentLink::where('api_document_id', $doc->id)->orderBy('created_at')->get();

        return response()->json([
            'document_id' => $doc->id,
            'data'        => $links->map(fn ($l) => $this->formatLink($l))->values(),
            'count'       => $links->count(),
        ]);
    }

    public function addLink(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', Rule::in(ApiDocumentLink::ENTITY_TYPES)],
            'entity_id'   => 'required|integer|min:1',
        ]);

        $user   = $this->apiUser($request);
        $client = $this->apiClient($request);
        $doc    = ApiDocument::where('user_id', $user->id)->findOrFail($id);

        if (!$this->findEntity($data['entity_type'], (int) $data['entity_id'], $user)) {
            return response()->json([
                'error' => "The {$data['entity_type']} #{$data['entity_id']} was not found or does not belong to you.",
            ], 422);
        }

        $link = ApiDocumentLink::firstOrCreate(
            ['api_document_id' => $doc->id, 'entity_type' => $data['entity_type'], 'entity_id' => (int) $data['entity_id']],
            ['linked_by_api_client_id' => $client->id]
        );

        $this->audit($request, 'link_document', 'api_document', $doc->id, 'low',
            "entity={$data['entity_type']}#{$data['entity_id']}", "link_id={$link->id}");

        return response()->json([
            'data'    => $this->formatLink($link),
            'message' => "Document linked to {$data['entity_type']} #{$data['entity_id']}.",
        ], $link->wasRecentlyCreated ? 201 : 200);
    }

    public function removeLink(Request $request, int $id, int $linkId): JsonResponse
    {
        $doc  = ApiDocument::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $link = ApiDocumentLink::where('api_document_id', $doc->id)->where('id', $linkId)->firstOrFail();

        $this->audit($request, 'unlink_document', 'api_document', $doc->id, 'low',
            "link_id={$linkId}", 'removed');

        $link->delete();

        return response()->json(['message' => 'Document unlinked.']);
    }

    // =========================================================================
    // Entity-scoped convenience routes  (public thin wrappers)
    // =========================================================================

    public function indexForOpportunity(Request $r, int $id): JsonResponse  { return $this->entityIndex($r, 'opportunity', $id); }
    public function storeForOpportunity(Request $r, int $id): JsonResponse   { return $this->entityStore($r, 'opportunity', $id); }
    public function detachFromOpportunity(Request $r, int $id, int $d): JsonResponse { return $this->entityDetach($r, 'opportunity', $id, $d); }

    public function indexForContact(Request $r, int $id): JsonResponse       { return $this->entityIndex($r, 'contact', $id); }
    public function storeForContact(Request $r, int $id): JsonResponse        { return $this->entityStore($r, 'contact', $id); }
    public function detachFromContact(Request $r, int $id, int $d): JsonResponse     { return $this->entityDetach($r, 'contact', $id, $d); }

    public function indexForEmailDraft(Request $r, int $id): JsonResponse    { return $this->entityIndex($r, 'email_draft', $id); }
    public function storeForEmailDraft(Request $r, int $id): JsonResponse     { return $this->entityStore($r, 'email_draft', $id); }
    public function detachFromEmailDraft(Request $r, int $id, int $d): JsonResponse  { return $this->entityDetach($r, 'email_draft', $id, $d); }

    public function indexForFollowUp(Request $r, int $id): JsonResponse      { return $this->entityIndex($r, 'follow_up', $id); }
    public function storeForFollowUp(Request $r, int $id): JsonResponse       { return $this->entityStore($r, 'follow_up', $id); }
    public function detachFromFollowUp(Request $r, int $id, int $d): JsonResponse    { return $this->entityDetach($r, 'follow_up', $id, $d); }

    // =========================================================================
    // Private — entity-scoped helpers
    // =========================================================================

    private function entityIndex(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $user = $this->apiUser($request);

        if (!$this->findEntity($entityType, $entityId, $user)) {
            return response()->json(['error' => "The {$entityType} #{$entityId} was not found."], 404);
        }

        $docIds = ApiDocumentLink::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->pluck('api_document_id');

        $docs = ApiDocument::where('user_id', $user->id)
            ->whereIn('id', $docIds)
            ->with(['currentVersion', 'links'])
            ->withCount('versions')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'data'        => $docs->map(fn ($d) => $this->format($d))->values(),
            'count'       => $docs->count(),
        ]);
    }

    private function entityStore(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $user = $this->apiUser($request);

        if (!$this->findEntity($entityType, $entityId, $user)) {
            return response()->json(['error' => "The {$entityType} #{$entityId} was not found."], 404);
        }

        // Inject the entity link so store() picks it up via collectEntityLinks().
        $request->merge(["{$entityType}_id" => $entityId]);

        return $this->store($request);
    }

    private function entityDetach(Request $request, string $entityType, int $entityId, int $docId): JsonResponse
    {
        $user = $this->apiUser($request);

        if (!$this->findEntity($entityType, $entityId, $user)) {
            return response()->json(['error' => "The {$entityType} #{$entityId} was not found."], 404);
        }

        $doc = ApiDocument::where('user_id', $user->id)->find($docId);
        if (!$doc) {
            return response()->json(['error' => "Document #{$docId} not found."], 404);
        }

        $link = ApiDocumentLink::where('api_document_id', $docId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();

        if (!$link) {
            return response()->json(['error' => 'Link not found.'], 404);
        }

        $this->audit($request, 'unlink_document', 'api_document', $docId, 'low',
            "entity={$entityType}#{$entityId}", 'detached');

        $link->delete();

        return response()->json(['message' => "Document #{$docId} unlinked from {$entityType} #{$entityId}."]);
    }

    // =========================================================================
    // Private — document creation
    // =========================================================================

    private function createFromUpload(Request $request): JsonResponse
    {
        $request->validate([
            'file'           => 'required|file|max:' . self::MAX_SIZE_KB . '|mimes:' . self::ALLOWED_EXTENSIONS,
            'name'           => 'required|string|max:500',
            'document_type'  => ['nullable', Rule::in(ApiDocument::DOCUMENT_TYPES)],
            'description'    => 'nullable|string|max:2000',
            'version_notes'  => 'nullable|string|max:2000',
            'opportunity_id' => 'nullable|integer',
            'contact_id'     => 'nullable|integer',
            'email_draft_id' => 'nullable|integer',
            'follow_up_id'   => 'nullable|integer',
        ]);

        $user   = $this->apiUser($request);
        $client = $this->apiClient($request);
        $file   = $request->file('file');
        $dtype  = $request->input('document_type', 'other');

        $entityLinks = $this->collectEntityLinks($request, $user);
        if ($entityLinks instanceof JsonResponse) return $entityLinks;

        $warnings    = ApiDocumentVersion::detectSensitiveWarnings($file->getClientOriginalName(), $dtype);
        $isSensitive = count($warnings) > 0;

        // 1. Create document record (current_version_id set after file stored)
        $doc = ApiDocument::create([
            'tenant_id'          => $user->tenant_id,
            'user_id'            => $user->id,
            'name'               => $request->input('name'),
            'document_type'      => $dtype,
            'description'        => $request->input('description'),
            'is_sensitive'       => $isSensitive,
            'sensitive_warnings' => $isSensitive ? $warnings : null,
        ]);

        try {
            $safe     = $this->sanitizeFilename($file->getClientOriginalName());
            $checksum = hash_file('sha256', $file->getRealPath());
            $path     = $file->storeAs("private/api-documents/{$doc->id}/v1", $safe, 'local');

            if (!$path) {
                $doc->forceDelete();
                return response()->json(['error' => 'Failed to store file.'], 500);
            }

            $version = ApiDocumentVersion::create([
                'api_document_id'           => $doc->id,
                'version_number'            => 1,
                'original_filename'         => $file->getClientOriginalName(),
                'mime_type'                 => $file->getMimeType() ?: $file->getClientMimeType(),
                'size_bytes'                => $file->getSize(),
                'checksum'                  => $checksum,
                'storage_path'              => $path,
                'upload_source'             => 'multipart',
                'version_notes'             => $request->input('version_notes'),
                'uploaded_by_api_client_id' => $client->id,
            ]);

            $doc->update(['current_version_id' => $version->id]);
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory("private/api-documents/{$doc->id}");
            $doc->forceDelete();
            throw $e;
        }

        $this->saveEntityLinks($doc, $entityLinks, $client);

        $this->audit($request, 'create_document', 'api_document', $doc->id, 'low',
            "name={$doc->name},source=multipart", "id={$doc->id},v={$version->id}");

        $doc->load(['currentVersion', 'links']);
        $doc->loadCount('versions');

        return $this->documentCreatedResponse($doc, $warnings);
    }

    /**
     * Upload via a base64-encoded JSON payload. GPT Actions clients cannot
     * stream multipart/form-data file content, so this is the path they use
     * to send a real file from the conversation.
     */
    private function createFromBase64(Request $request): JsonResponse
    {
        $request->validate([
            'file_base64'    => 'required|string',
            'filename'       => 'required|string|max:255',
            'name'           => 'required|string|max:500',
            'document_type'  => ['nullable', Rule::in(ApiDocument::DOCUMENT_TYPES)],
            'description'    => 'nullable|string|max:2000',
            'version_notes'  => 'nullable|string|max:2000',
            'opportunity_id' => 'nullable|integer',
            'contact_id'     => 'nullable|integer',
            'email_draft_id' => 'nullable|integer',
            'follow_up_id'   => 'nullable|integer',
        ]);

        $extension = strtolower(pathinfo($request->input('filename'), PATHINFO_EXTENSION));
        if (!in_array($extension, explode(',', self::ALLOWED_EXTENSIONS), true)) {
            return response()->json(['error' => "Unsupported file extension: .{$extension}", 'field' => 'filename'], 422);
        }

        $contents = $this->decodeBase64File($request->input('file_base64'), self::MAX_SIZE_BYTES);
        if ($contents instanceof JsonResponse) return $contents;

        $user   = $this->apiUser($request);
        $client = $this->apiClient($request);
        $dtype  = $request->input('document_type', 'other');

        $entityLinks = $this->collectEntityLinks($request, $user);
        if ($entityLinks instanceof JsonResponse) return $entityLinks;

        $originalFilename = basename($request->input('filename'));
        $warnings    = ApiDocumentVersion::detectSensitiveWarnings($originalFilename, $dtype);
        $isSensitive = count($warnings) > 0;

        $doc = ApiDocument::create([
            'tenant_id'          => $user->tenant_id,
            'user_id'            => $user->id,
            'name'               => $request->input('name'),
            'document_type'      => $dtype,
            'description'        => $request->input('description'),
            'is_sensitive'       => $isSensitive,
            'sensitive_warnings' => $isSensitive ? $warnings : null,
        ]);

        try {
            $safe = $this->sanitizeFilename($originalFilename);
            $path = "private/api-documents/{$doc->id}/v1/{$safe}";

            if (!Storage::disk('local')->put($path, $contents)) {
                $doc->forceDelete();
                return response()->json(['error' => 'Failed to store file.'], 500);
            }

            $version = ApiDocumentVersion::create([
                'api_document_id'           => $doc->id,
                'version_number'            => 1,
                'original_filename'         => $originalFilename,
                'mime_type'                 => $this->detectMimeType($contents, $extension),
                'size_bytes'                => strlen($contents),
                'checksum'                  => hash('sha256', $contents),
                'storage_path'              => $path,
                'upload_source'             => 'agent',
                'version_notes'             => $request->input('version_notes'),
                'uploaded_by_api_client_id' => $client->id,
            ]);

            $doc->update(['current_version_id' => $version->id]);
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory("private/api-documents/{$doc->id}");
            $doc->forceDelete();
            throw $e;
        }

        $this->saveEntityLinks($doc, $entityLinks, $client);

        $this->audit($request, 'create_document', 'api_document', $doc->id, 'low',
            "name={$doc->name},source=agent_base64", "id={$doc->id},v={$version->id}");

        $doc->load(['currentVersion', 'links']);
        $doc->loadCount('versions');

        return $this->documentCreatedResponse($doc, $warnings);
    }

    private function createFromUrl(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:500',
            'public_url'     => 'required|url|max:2048',
            'mime_type'      => ['required', 'string', Rule::in(ApiDocumentVersion::ALLOWED_MIME_TYPES)],
            'size_bytes'     => 'required|integer|min:1|max:' . self::MAX_SIZE_BYTES,
            'checksum'       => 'nullable|string|max:64',
            'document_type'  => ['nullable', Rule::in(ApiDocument::DOCUMENT_TYPES)],
            'description'    => 'nullable|string|max:2000',
            'version_notes'  => 'nullable|string|max:2000',
            'opportunity_id' => 'nullable|integer',
            'contact_id'     => 'nullable|integer',
            'email_draft_id' => 'nullable|integer',
            'follow_up_id'   => 'nullable|integer',
        ]);

        $urlError = ApiAttachment::validateUrl($data['public_url']);
        if ($urlError) {
            return response()->json(['error' => $urlError, 'field' => 'public_url'], 422);
        }

        $user   = $this->apiUser($request);
        $client = $this->apiClient($request);
        $dtype  = $data['document_type'] ?? 'other';

        $entityLinks = $this->collectEntityLinks($request, $user);
        if ($entityLinks instanceof JsonResponse) return $entityLinks;

        // Derive filename from URL path; fall back to the document name.
        $urlFilename = basename(parse_url($data['public_url'], PHP_URL_PATH)) ?: $data['name'];
        $warnings    = ApiDocumentVersion::detectSensitiveWarnings($urlFilename, $dtype);
        $isSensitive = count($warnings) > 0;

        $doc = ApiDocument::create([
            'tenant_id'          => $user->tenant_id,
            'user_id'            => $user->id,
            'name'               => $data['name'],
            'document_type'      => $dtype,
            'description'        => $data['description'] ?? null,
            'is_sensitive'       => $isSensitive,
            'sensitive_warnings' => $isSensitive ? $warnings : null,
        ]);

        $version = ApiDocumentVersion::create([
            'api_document_id'           => $doc->id,
            'version_number'            => 1,
            'original_filename'         => $urlFilename,
            'mime_type'                 => $data['mime_type'],
            'size_bytes'                => $data['size_bytes'],
            'checksum'                  => $data['checksum'] ?? null,
            'public_url'                => $data['public_url'],
            'upload_source'             => 'url',
            'version_notes'             => $data['version_notes'] ?? null,
            'uploaded_by_api_client_id' => $client->id,
        ]);

        $doc->update(['current_version_id' => $version->id]);

        $this->saveEntityLinks($doc, $entityLinks, $client);

        $this->audit($request, 'create_document', 'api_document', $doc->id, 'low',
            "name={$doc->name},source=url", "id={$doc->id},v={$version->id}");

        $doc->load(['currentVersion', 'links']);
        $doc->loadCount('versions');

        return $this->documentCreatedResponse($doc, $warnings);
    }

    // =========================================================================
    // Private — helpers
    // =========================================================================

    /** Returns the entity model if found + owned by $user; null otherwise. */
    private function findEntity(string $entityType, int $entityId, User $user): ?Model
    {
        return match ($entityType) {
            'opportunity' => Opportunity::where('user_id', $user->id)->find($entityId),
            'contact'     => Contact::where('user_id', $user->id)->find($entityId),
            'email_draft' => EmailMessage::where('user_id', $user->id)->find($entityId),
            'follow_up'   => FollowUp::where('user_id', $user->id)->find($entityId),
            default       => null,
        };
    }

    /**
     * Validates and collects entity links from request body fields.
     * Returns an array of ['entity_type' => …, 'entity_id' => …] or a 422 JsonResponse.
     */
    private function collectEntityLinks(Request $request, User $user): array|JsonResponse
    {
        $pairs = [
            'opportunity_id' => 'opportunity',
            'contact_id'     => 'contact',
            'email_draft_id' => 'email_draft',
            'follow_up_id'   => 'follow_up',
        ];

        $links = [];
        foreach ($pairs as $field => $entityType) {
            if (!$request->filled($field)) continue;
            $entityId = (int) $request->input($field);

            if (!$this->findEntity($entityType, $entityId, $user)) {
                return response()->json([
                    'error' => "The {$entityType} #{$entityId} was not found or does not belong to you.",
                    'field' => $field,
                ], 422);
            }

            $links[] = ['entity_type' => $entityType, 'entity_id' => $entityId];
        }

        return $links;
    }

    private function saveEntityLinks(ApiDocument $doc, array $links, ApiClient $client): void
    {
        foreach ($links as $link) {
            ApiDocumentLink::firstOrCreate(
                ['api_document_id' => $doc->id, 'entity_type' => $link['entity_type'], 'entity_id' => $link['entity_id']],
                ['linked_by_api_client_id' => $client->id]
            );
        }
        $doc->unsetRelation('links');
    }

    /** Stream local file or return redirect info for URL-based versions. */
    private function serveVersion(ApiDocumentVersion $version): BinaryFileResponse|StreamedResponse|JsonResponse
    {
        if ($version->storage_path && Storage::disk('local')->exists($version->storage_path)) {
            return Storage::disk('local')->download(
                $version->storage_path,
                $version->original_filename,
                ['Content-Type' => $version->mime_type]
            );
        }

        if ($version->public_url) {
            return response()->json([
                'download_type' => 'external_url',
                'download_url'  => $version->public_url,
                'filename'      => $version->original_filename,
                'mime_type'     => $version->mime_type,
                'size_bytes'    => $version->size_bytes,
                'message'       => 'This document is hosted externally. Use download_url to access it.',
            ]);
        }

        return response()->json(['error' => 'File not available for download.'], 404);
    }

    private function sanitizeFilename(string $filename): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($filename));

        return substr($safe ?: 'document', 0, 200);
    }

    private function documentCreatedResponse(ApiDocument $doc, array $warnings): JsonResponse
    {
        $payload = ['data' => $this->format($doc), 'message' => 'Document created.'];

        if (!empty($warnings)) {
            $payload['sensitive_warnings'] = $warnings;
            $payload['warning']            = 'Sensitive document detected. Review before attaching to cold outreach.';
        }

        return response()->json($payload, 201);
    }

    // =========================================================================
    // Private — formatters
    // =========================================================================

    private function format(ApiDocument $doc): array
    {
        $cv = $doc->currentVersion;

        return [
            'document_id'        => $doc->id,
            'name'               => $doc->name,
            'document_type'      => $doc->document_type,
            'description'        => $doc->description,
            'is_sensitive'       => (bool) $doc->is_sensitive,
            'sensitive_warnings' => $doc->sensitive_warnings ?? [],
            'version_count'      => $doc->versions_count ?? $doc->versions()->count(),
            'current_version'    => $cv ? $this->formatVersion($cv) : null,
            'entity_links'       => $doc->relationLoaded('links')
                                     ? $doc->links->map(fn ($l) => $this->formatLink($l))->values()->toArray()
                                     : [],
            'created_at'         => $doc->created_at?->toISOString(),
            'updated_at'         => $doc->updated_at?->toISOString(),
        ];
    }

    private function formatVersion(ApiDocumentVersion $v): array
    {
        return [
            'version_id'        => $v->id,
            'version_number'    => $v->version_number,
            'original_filename' => $v->original_filename,
            'mime_type'         => $v->mime_type,
            'size_bytes'        => $v->size_bytes,
            'checksum'          => $v->checksum,
            'upload_source'     => $v->upload_source,
            'has_local_file'    => !empty($v->storage_path),
            'public_url'        => $v->public_url,
            'version_notes'     => $v->version_notes,
            'created_at'        => $v->created_at?->toISOString(),
        ];
    }

    private function formatLink(ApiDocumentLink $l): array
    {
        return [
            'link_id'     => $l->id,
            'entity_type' => $l->entity_type,
            'entity_id'   => $l->entity_id,
            'created_at'  => $l->created_at?->toISOString(),
        ];
    }
}
