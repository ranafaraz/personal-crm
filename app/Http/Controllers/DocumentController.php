<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\ApiDocument;
use App\Models\Document;
use App\Models\Opportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->tenantQuery(Document::class);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('document_type', 'like', "%{$search}%");
            });
        }

        $documents = $query->with(['opportunity', 'contact'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('documents.index', compact('documents'));
    }

    public function create(): View
    {
        $opportunities = $this->tenantQuery(Opportunity::class)
            ->orderByDesc('created_at')
            ->get(['id', 'title']);

        return view('documents.create', compact('opportunities'));
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $opportunityId = $data['opportunity_id'] ?? null;

        // De-dupe: replace any existing document with the same file name already linked to the same opportunity.
        if ($opportunityId) {
            $existing = $this->tenantQuery(Document::class)
                ->where('opportunity_id', $opportunityId)
                ->where('file_name', $originalName)
                ->get();
            foreach ($existing as $dupe) {
                if ($dupe->file_path && Storage::disk('local')->exists($dupe->file_path)) {
                    Storage::disk('local')->delete($dupe->file_path);
                }
                $dupe->delete();
            }
        }

        $path = $file->store('documents', 'local');

        $document = Document::create($this->tenantData([
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'opportunity_id' => $opportunityId,
            'contact_id'    => $data['contact_id'] ?? null,
            'file_path'     => $path,
            'file_name'     => $originalName,
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
        ]));

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Document uploaded successfully.');
    }

    public function show(Request $request, int $id): View
    {
        $document = $this->tenantQuery(Document::class)->findOrFail($id);

        $this->authorize('view', $document);

        return view('documents.show', compact('document'));
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        $document = $this->tenantQuery(Document::class)->findOrFail($id);

        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function view(Request $request, int $id): StreamedResponse
    {
        $document = $this->tenantQuery(Document::class)->findOrFail($id);

        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, $document->file_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    public function viewApiDoc(Request $request, int $id): StreamedResponse
    {
        $doc = $this->tenantQuery(ApiDocument::class)
            ->with('currentVersion')
            ->findOrFail($id);

        $ver = $doc->currentVersion;

        abort_unless($ver && $ver->storage_path && Storage::disk('local')->exists($ver->storage_path), 404);

        return Storage::disk('local')->response($ver->storage_path, $ver->original_filename, [
            'Content-Type' => $ver->mime_type,
        ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $document = $this->tenantQuery(Document::class)->findOrFail($id);

        $this->authorize('delete', $document);

        // Remove the physical file
        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Document deleted.');
    }
}
