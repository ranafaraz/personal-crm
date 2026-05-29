<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Posts scheduled in the next 60 days (or overdue with a schedule)
        $scheduled = SocialPost::where('user_id', $user->id)
            ->whereIn('status', ['scheduled', 'approved', 'failed'])
            ->whereNotNull('scheduled_at')
            ->with(['targets.account.provider'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn ($post) => [
                'id'           => $post->id,
                'title'        => $post->title_internal,
                'status'       => $post->status,
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                'tz'           => $post->timezone_display,
                'post_type'    => $post->post_type,
                'url'          => route('social-studio.posts.show', $post->id),
            ]);

        return view('social-studio.calendar', compact('scheduled'));
    }
}
