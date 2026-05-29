<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\SocialProvider;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $draftsCount     = SocialPost::where('user_id', $user->id)->whereIn('status', ['draft', 'ready_for_review'])->count();
        $scheduledCount  = SocialPost::where('user_id', $user->id)->where('status', 'scheduled')->where('scheduled_at', '>=', now())->where('scheduled_at', '<=', now()->addDays(7))->count();
        $failedCount     = SocialPost::where('user_id', $user->id)->where('status', 'failed')->count();

        $lastPublished = SocialPostTarget::whereHas('post', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'published')
            ->latest('published_at')
            ->first();

        $linkedInAccount = SocialAccount::where('user_id', $user->id)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->first();

        $providers = SocialProvider::all();

        return view('social-studio.dashboard', compact(
            'draftsCount', 'scheduledCount', 'failedCount',
            'lastPublished', 'linkedInAccount', 'providers'
        ));
    }
}
