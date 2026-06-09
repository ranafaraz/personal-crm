<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\EmailMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->tenantQuery(FollowUp::class)
            ->with(['opportunity', 'contact', 'emailAccount']);

        $status = $request->input('status');

        if ($status) {
            $query->where('status', $status);
        }
        if ($opportunityId = $request->input('opportunity_id')) {
            $query->where('opportunity_id', $opportunityId);
        }
        if ($from = $request->input('due_from')) {
            $query->where('due_at', '>=', $from);
        }
        if ($to = $request->input('due_to')) {
            $query->where('due_at', '<=', $to);
        }

        $followUps = $query->orderBy('due_at')->paginate(25)->withQueryString();

        $scheduledEmailQuery = $this->tenantQuery(EmailMessage::class)
            ->with(['opportunity', 'contact', 'emailAccount'])
            ->where('direction', 'outbound')
            ->where('is_follow_up', true);

        if ($opportunityId = $request->input('opportunity_id')) {
            $scheduledEmailQuery->where('opportunity_id', $opportunityId);
        }
        if ($from = $request->input('due_from')) {
            $scheduledEmailQuery->where('scheduled_at', '>=', $from);
        }
        if ($to = $request->input('due_to')) {
            $scheduledEmailQuery->where('scheduled_at', '<=', $to);
        }

        if ($status) {
            $emailStatuses = match ($status) {
                'pending' => ['scheduled', 'queued'],
                'sent' => ['sent'],
                'cancelled' => ['cancelled'],
                default => [],
            };

            $emailStatuses
                ? $scheduledEmailQuery->whereIn('status', $emailStatuses)
                : $scheduledEmailQuery->whereRaw('1=0');
        } else {
            $scheduledEmailQuery->whereIn('status', ['scheduled', 'queued', 'sent', 'failed', 'cancelled']);
        }

        $scheduledEmailFollowUps = $scheduledEmailQuery
            ->orderByRaw('scheduled_at is null')
            ->orderBy('scheduled_at')
            ->limit(100)
            ->get();

        return view('follow-ups.index', compact('followUps', 'scheduledEmailFollowUps'));
    }

    public function show(Request $request, int $id): View
    {
        $followUp = $this->tenantQuery(FollowUp::class)
            ->with(['opportunity', 'contact', 'emailAccount', 'emailTemplate', 'emailMessage', 'emailSignature', 'apiAttachments'])
            ->findOrFail($id);

        return view('follow-ups.show', compact('followUp'));
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $followUp = $this->tenantQuery(FollowUp::class)->findOrFail($id);

        abort_unless($followUp->status === 'pending', 422, 'Only pending follow-ups can be cancelled.');

        $request->validate([
            'cancel_reason' => 'nullable|string|max:500',
        ]);

        $followUp->update([
            'status'        => 'cancelled',
            'cancel_reason' => $request->input('cancel_reason'),
        ]);

        return redirect()->back()->with('success', 'Follow-up cancelled.');
    }

    public function reschedule(Request $request, int $id): RedirectResponse
    {
        $followUp = $this->tenantQuery(FollowUp::class)->findOrFail($id);

        abort_unless($followUp->status === 'pending', 422, 'Only pending follow-ups can be rescheduled.');

        $request->validate([
            'due_at' => 'required|date|after:now',
        ]);

        $followUp->update(['due_at' => $request->input('due_at')]);

        return redirect()->back()->with('success', 'Follow-up rescheduled.');
    }
}
