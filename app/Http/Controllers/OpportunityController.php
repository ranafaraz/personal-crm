<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityRequest;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->tenantQuery(Opportunity::class)->with('tags');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('organization', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Default sort: priority (urgent → low) → soonest deadline → most recent.
        // CASE keeps this portable across MySQL/MariaDB and SQLite; undefined
        // priority values sort to the end of the bucket.
        $opportunities = $query
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC")
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('deadline', 'asc')
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('opportunities.index', compact('opportunities'));
    }

    public function create(): View
    {
        $tags = $this->tenantQuery(Tag::class)->orderBy('name')->get();
        $contacts = $this->tenantQuery(Contact::class)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'company']);

        return view('opportunities.create', compact('tags', 'contacts'));
    }

    public function store(StoreOpportunityRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $contactIds = $data['contacts'] ?? [];
        unset($data['tags'], $data['contacts']);

        $data['last_activity_at'] = now();

        $opportunity = Opportunity::create($this->tenantData($data));

        if ($tagIds) {
            $opportunity->tags()->sync($tagIds);
        }
        if ($contactIds) {
            $opportunity->contacts()->sync($contactIds);
        }

        return redirect()->route('opportunities.show', $opportunity->id)
            ->with('success', 'Opportunity created successfully.');
    }

    public function show(Request $request, int $id): View
    {
        $opportunity = $this->tenantQuery(Opportunity::class)
            ->with([
                'contacts',
                'tags',
                'emailMessages.emailAccount',
                'followUps',
                'documents',
                'apiDocumentLinks.document.currentVersion',
                'timelineEvents' => fn ($q) => $q->orderByDesc('happened_at'),
            ])
            ->findOrFail($id);

        $this->authorize('view', $opportunity);

        $timeline = $opportunity->timelineEvents;

        return view('opportunities.show', compact('opportunity', 'timeline'));
    }

    public function edit(Request $request, int $id): View
    {
        $opportunity = $this->tenantQuery(Opportunity::class)
            ->with(['tags', 'contacts'])
            ->findOrFail($id);

        $this->authorize('update', $opportunity);

        $tags = $this->tenantQuery(Tag::class)->orderBy('name')->get();
        $contacts = $this->tenantQuery(Contact::class)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'company']);

        return view('opportunities.edit', compact('opportunity', 'tags', 'contacts'));
    }

    public function update(UpdateOpportunityRequest $request, int $id): RedirectResponse
    {
        $opportunity = $this->tenantQuery(Opportunity::class)->findOrFail($id);

        $this->authorize('update', $opportunity);

        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $contactIds = $data['contacts'] ?? [];
        unset($data['tags'], $data['contacts']);

        $data['last_activity_at'] = now();

        $opportunity->update($data);
        $opportunity->tags()->sync($tagIds);
        $opportunity->contacts()->sync($contactIds);

        return redirect()->route('opportunities.show', $opportunity->id)
            ->with('success', 'Opportunity updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $opportunity = $this->tenantQuery(Opportunity::class)->findOrFail($id);

        $this->authorize('delete', $opportunity);

        $opportunity->delete();

        return redirect()->route('opportunities.index')
            ->with('success', 'Opportunity deleted.');
    }

    /**
     * Soft-delete many opportunities at once. Only opportunities visible to the
     * current user are touched (tenantQuery + authorize each one), so submitting
     * an arbitrary id list can never delete someone else's records.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $opportunities = $this->tenantQuery(Opportunity::class)
            ->whereIn('id', $data['ids'])
            ->get();

        $deleted = 0;
        foreach ($opportunities as $opportunity) {
            if ($request->user()->can('delete', $opportunity)) {
                $opportunity->delete();
                $deleted++;
            }
        }

        $requested = count($data['ids']);
        $skipped   = $requested - $deleted;
        $msg = "{$deleted} opportunit" . ($deleted === 1 ? 'y' : 'ies') . ' deleted.';
        if ($skipped > 0) {
            $msg .= " {$skipped} skipped (not found or no permission).";
        }

        return redirect()->route('opportunities.index')->with('success', $msg);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $opportunity = $this->tenantQuery(Opportunity::class)->findOrFail($id);

        $this->authorize('update', $opportunity);

        $request->validate([
            'status' => 'required|string|max:100',
        ]);

        $opportunity->update([
            'status'           => $request->input('status'),
            'last_activity_at' => now(),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'status' => $opportunity->status]);
        }

        return redirect()->back()->with('success', 'Status updated.');
    }
}
