<?php

namespace App\Http\Controllers\Api\App;

use App\Models\FollowUp;
use App\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Manual follow-up reminders for the mobile app (§4.5).
 *
 * These are lightweight reminders tied to an opportunity — not the auto-send
 * follow-ups managed by FollowUpService. The "complete" action marks the
 * reminder done by setting status=sent + sent_at, matching the existing ENUM.
 * shape() maps status=sent → 'completed' so the app never sees the raw DB value.
 */
class FollowupController extends AppController
{
    public function index(Request $request): JsonResponse
    {
        $query = FollowUp::where('user_id', $request->user()->id)
            ->where('status', 'pending');

        match ($request->query('filter', 'upcoming')) {
            'due_today' => $query->whereDate('due_at', Carbon::today()),
            'overdue'   => $query->whereDate('due_at', '<', Carbon::today()),
            default     => $query->whereDate('due_at', '>=', Carbon::today()),
        };

        $perPage   = min((int) $request->query('per_page', 20), 100);
        $paginator = $query->with('opportunity')
            ->orderBy('due_at')
            ->paginate($perPage);

        return $this->paginated($paginator, fn (FollowUp $f) => $this->shape($f));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opportunity_id' => ['required', 'integer'],
            'due_at'         => ['required', 'date', 'after:now'],
            'note'           => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $user        = $request->user();
        $opportunity = Opportunity::forCurrentUser()->find($validated['opportunity_id']);

        if (! $opportunity) {
            return $this->notFound('Opportunity not found.');
        }

        $followUp = FollowUp::create([
            'tenant_id'      => $user->tenant_id,
            'user_id'        => $user->id,
            'opportunity_id' => $opportunity->id,
            'due_at'         => $validated['due_at'],
            'subject'        => $validated['note'] ?? null,
            'status'         => 'pending',
        ]);

        return $this->data($this->shape($followUp->load('opportunity')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $followUp = $this->findOwned($request, $id);
        if (! $followUp) {
            return $this->notFound('Follow-up not found.');
        }

        $validated = $request->validate([
            'due_at' => ['sometimes', 'date', 'after:now'],
            'note'   => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $updates = [];
        if (array_key_exists('due_at', $validated)) {
            $updates['due_at'] = $validated['due_at'];
        }
        if (array_key_exists('note', $validated)) {
            $updates['subject'] = $validated['note'];
        }

        if ($updates) {
            $followUp->update($updates);
        }

        return $this->data($this->shape($followUp->fresh(['opportunity'])));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $followUp = $this->findOwned($request, $id);
        if (! $followUp) {
            return $this->notFound('Follow-up not found.');
        }

        $followUp->delete();

        return response()->json(null, 204);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $followUp = $this->findOwned($request, $id);
        if (! $followUp) {
            return $this->notFound('Follow-up not found.');
        }

        if ($followUp->status !== 'pending') {
            return $this->error('Follow-up is already completed.', 'ALREADY_COMPLETED');
        }

        $followUp->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return $this->data($this->shape($followUp->fresh(['opportunity'])));
    }

    private function findOwned(Request $request, int $id): ?FollowUp
    {
        return FollowUp::where('user_id', $request->user()->id)->find($id);
    }

    private function shape(FollowUp $f): array
    {
        return [
            'id'             => $f->id,
            'opportunity_id' => $f->opportunity_id,
            'opportunity'    => $f->opportunity ? [
                'id'    => $f->opportunity->id,
                'title' => $f->opportunity->title,
                'org'   => $f->opportunity->organization,
            ] : null,
            'due_at'         => $f->due_at?->toISOString(),
            'note'           => $f->subject,
            'status'         => $f->status === 'sent' ? 'completed' : $f->status,
            'completed_at'   => $f->sent_at?->toISOString(),
            'created_at'     => $f->created_at?->toISOString(),
            'updated_at'     => $f->updated_at?->toISOString(),
        ];
    }
}
