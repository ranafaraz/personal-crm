<?php

namespace App\Http\Controllers\Api\App;

use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Support\OpportunityStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends AppController
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        // Group by stored status, then normalize to canonical stages
        $pipeline = array_fill_keys(OpportunityStage::STAGES, 0);

        Opportunity::where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->get()
            ->each(function ($row) use (&$pipeline) {
                $stage = OpportunityStage::normalize($row->status);
                $pipeline[$stage] += (int) $row->cnt;
            });

        $pendingDrafts = EmailMessage::where('user_id', $user->id)
            ->where('direction', 'outbound')
            ->where('status', 'draft')
            ->count();

        $followupsDueToday = FollowUp::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereDate('due_at', Carbon::today())
            ->count();

        return $this->data([
            'pipeline'            => $pipeline,
            'pending_drafts'      => $pendingDrafts,
            'followups_due_today' => $followupsDueToday,
        ]);
    }
}
