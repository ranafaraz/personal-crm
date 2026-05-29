<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialActivityLog;
use App\Models\SocialMediaAsset;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $user   = $request->user();
        $status = $request->input('status', 'draft');

        $posts = SocialPost::where('user_id', $user->id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['targets.account.provider', 'mediaAssets'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        $statusCounts = SocialPost::where('user_id', $user->id)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('social-studio.posts.index', compact('posts', 'status', 'statusCounts'));
    }

    public function create(Request $request): View
    {
        $user   = $request->user();
        $assets = SocialMediaAsset::where('user_id', $user->id)
            ->where('approval_status', 'approved')
            ->orderByDesc('created_at')
            ->get();
        $linkedInAccount = SocialAccount::where('user_id', $user->id)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->where('status', 'connected')
            ->first();

        return view('social-studio.posts.create', compact('assets', 'linkedInAccount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title_internal'   => 'required|string|max:500',
            'topic'            => 'nullable|string|max:255',
            'post_body'        => 'required|string|max:3000',
            'post_type'        => ['required', Rule::in(['text', 'image', 'article_link'])],
            'article_url'      => 'nullable|url|max:2048|required_if:post_type,article_link',
            'hashtags'         => 'nullable|string|max:500',
            'source_notes'     => 'nullable|string|max:5000',
            'scheduled_at'     => 'nullable|date',
            'timezone_display' => 'nullable|string|max:100',
            'featured_asset_id'=> 'nullable|integer|exists:social_media_assets,id',
            'visibility'       => ['nullable', Rule::in(['PUBLIC', 'CONNECTIONS'])],
        ]);

        $user = $request->user();

        $post = SocialPost::create([
            'tenant_id'        => $user->tenant_id,
            'user_id'          => $user->id,
            'title_internal'   => $data['title_internal'],
            'topic'            => $data['topic'] ?? null,
            'post_body'        => $data['post_body'],
            'post_type'        => $data['post_type'],
            'article_url'      => $data['article_url'] ?? null,
            'hashtags_json'    => $this->parseHashtags($data['hashtags'] ?? ''),
            'source_notes'     => $data['source_notes'] ?? null,
            'scheduled_at'     => isset($data['scheduled_at']) ? $this->toUtc($data['scheduled_at'], $data['timezone_display'] ?? 'Asia/Karachi') : null,
            'timezone_display' => $data['timezone_display'] ?? 'Asia/Karachi',
            'status'           => 'draft',
            'approval_status'  => 'pending_review',
            'created_source'   => 'manual',
        ]);

        // Attach featured image if provided
        if (! empty($data['featured_asset_id'])) {
            $asset = SocialMediaAsset::where('user_id', $user->id)->find($data['featured_asset_id']);
            if ($asset) {
                $post->mediaAssets()->attach($asset->id, ['is_featured' => true, 'display_order' => 0]);
            }
        }

        // Create LinkedIn target if account is connected
        $account = SocialAccount::where('user_id', $user->id)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->where('status', 'connected')
            ->first();

        if ($account) {
            SocialPostTarget::create([
                'social_post_id'         => $post->id,
                'social_account_id'      => $account->id,
                'provider_key'           => 'linkedin',
                'platform_body'          => $post->post_body,
                'platform_metadata_json' => ['visibility' => $data['visibility'] ?? 'PUBLIC'],
                'status'                 => 'draft',
                'scheduled_at'           => $post->scheduled_at,
            ]);
        }

        SocialActivityLog::record(
            $user->id, $user->tenant_id,
            'post_created', SocialPost::class, $post->id,
            "Draft created manually: {$post->title_internal}"
        );

        return redirect()->route('social-studio.posts.show', $post->id)
            ->with('success', 'Draft created. Review and approve when ready.');
    }

    public function show(Request $request, int $id): View
    {
        $post = SocialPost::where('user_id', $request->user()->id)
            ->with(['targets.account.provider', 'targets.publishJobs', 'mediaAssets'])
            ->findOrFail($id);

        $assets = SocialMediaAsset::where('user_id', $request->user()->id)
            ->where('approval_status', 'approved')
            ->orderByDesc('created_at')
            ->get();

        return view('social-studio.posts.show', compact('post', 'assets'));
    }

    public function edit(Request $request, int $id): View
    {
        $post = SocialPost::where('user_id', $request->user()->id)->findOrFail($id);
        if (! $post->isEditable()) {
            return redirect()->route('social-studio.posts.show', $id)
                ->with('error', 'Published or cancelled posts cannot be edited.');
        }
        $assets = SocialMediaAsset::where('user_id', $request->user()->id)
            ->where('approval_status', 'approved')
            ->get();

        return view('social-studio.posts.edit', compact('post', 'assets'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $post = SocialPost::where('user_id', $request->user()->id)->findOrFail($id);
        if (! $post->isEditable()) {
            return back()->with('error', 'This post cannot be edited in its current state.');
        }

        $data = $request->validate([
            'title_internal'   => 'required|string|max:500',
            'topic'            => 'nullable|string|max:255',
            'post_body'        => 'required|string|max:3000',
            'post_type'        => ['required', Rule::in(['text', 'image', 'article_link'])],
            'article_url'      => 'nullable|url|max:2048',
            'hashtags'         => 'nullable|string|max:500',
            'source_notes'     => 'nullable|string|max:5000',
            'scheduled_at'     => 'nullable|date',
            'timezone_display' => 'nullable|string|max:100',
        ]);

        $wasApproved = $post->approval_status === 'approved';

        $post->update([
            'title_internal'   => $data['title_internal'],
            'topic'            => $data['topic'] ?? null,
            'post_body'        => $data['post_body'],
            'post_type'        => $data['post_type'],
            'article_url'      => $data['article_url'] ?? null,
            'hashtags_json'    => $this->parseHashtags($data['hashtags'] ?? ''),
            'source_notes'     => $data['source_notes'] ?? null,
            'scheduled_at'     => isset($data['scheduled_at']) ? $this->toUtc($data['scheduled_at'], $data['timezone_display'] ?? 'Asia/Karachi') : null,
            'timezone_display' => $data['timezone_display'] ?? 'Asia/Karachi',
            // Editing after approval resets approval
            'approval_status'  => $wasApproved ? 'pending_review' : $post->approval_status,
            'approved_at'      => $wasApproved ? null : $post->approved_at,
            'approved_by'      => $wasApproved ? null : $post->approved_by,
            'status'           => $wasApproved ? 'ready_for_review' : $post->status,
        ]);

        $user = $request->user();
        SocialActivityLog::record(
            $user->id, $user->tenant_id,
            $wasApproved ? 'post_edited_approval_reset' : 'post_edited',
            SocialPost::class, $post->id,
            $wasApproved ? 'Post edited after approval — reset to pending_review' : 'Post edited'
        );

        return redirect()->route('social-studio.posts.show', $post->id)
            ->with('success', $wasApproved ? 'Post updated. Approval has been reset — please review and approve again.' : 'Post updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $post = SocialPost::where('user_id', $request->user()->id)->findOrFail($id);
        $post->delete();

        $user = $request->user();
        SocialActivityLog::record($user->id, $user->tenant_id, 'post_deleted', SocialPost::class, $post->id, "Post deleted: {$post->title_internal}");

        return redirect()->route('social-studio.posts.index')
            ->with('success', 'Post deleted.');
    }

    public function submitForReview(Request $request, int $id): RedirectResponse
    {
        $post = SocialPost::where('user_id', $request->user()->id)->findOrFail($id);
        $post->update(['status' => 'ready_for_review']);
        return back()->with('success', 'Post submitted for review.');
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $post = SocialPost::where('user_id', $request->user()->id)->findOrFail($id);

        $post->update([
            'approval_status' => 'approved',
            'approved_at'     => now(),
            'approved_by'     => $request->user()->id,
            'status'          => $post->scheduled_at ? 'scheduled' : 'approved',
        ]);

        // Mirror approval to any targets
        $post->targets()->whereNot('status', 'published')->update(['status' => $post->scheduled_at ? 'scheduled' : 'approved']);

        $user = $request->user();
        SocialActivityLog::record($user->id, $user->tenant_id, 'post_approved', SocialPost::class, $post->id, 'Post approved for publication');

        return back()->with('success', 'Post approved' . ($post->scheduled_at ? ' and scheduled.' : '.'));
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $post = SocialPost::where('user_id', $request->user()->id)->findOrFail($id);
        $post->update(['approval_status' => 'rejected', 'status' => 'draft']);
        $post->targets()->update(['status' => 'draft']);

        $user = $request->user();
        SocialActivityLog::record($user->id, $user->tenant_id, 'post_rejected', SocialPost::class, $post->id, 'Post rejected');

        return back()->with('success', 'Post rejected and returned to draft.');
    }

    public function schedule(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_at'     => 'required|date|after:now',
            'timezone_display' => 'nullable|string|max:100',
        ]);

        $post = SocialPost::where('user_id', $request->user()->id)->findOrFail($id);
        $tz   = $data['timezone_display'] ?? 'Asia/Karachi';
        $utc  = $this->toUtc($data['scheduled_at'], $tz);

        $post->update([
            'scheduled_at'     => $utc,
            'timezone_display' => $tz,
            'status'           => $post->isApproved() ? 'scheduled' : $post->status,
        ]);

        $post->targets()->whereNot('status', 'published')->update(['scheduled_at' => $utc]);

        $user = $request->user();
        SocialActivityLog::record($user->id, $user->tenant_id, 'post_scheduled', SocialPost::class, $post->id, "Scheduled at {$utc} UTC ({$tz})");

        return back()->with('success', "Post scheduled for " . $post->scheduled_at->setTimezone($tz)->format('D, d M Y H:i') . " {$tz}.");
    }

    public function cancelSchedule(Request $request, int $id): RedirectResponse
    {
        $post = SocialPost::where('user_id', $request->user()->id)->findOrFail($id);
        $post->update(['scheduled_at' => null, 'status' => 'approved']);
        $post->targets()->whereNot('status', 'published')->update(['scheduled_at' => null, 'status' => 'approved']);

        $user = $request->user();
        SocialActivityLog::record($user->id, $user->tenant_id, 'post_unscheduled', SocialPost::class, $post->id, 'Schedule cancelled');

        return back()->with('success', 'Schedule cancelled. Post is approved and ready for manual publish-now.');
    }

    public function publishNow(Request $request, int $id): RedirectResponse
    {
        $request->validate(['confirm' => 'accepted']);

        $post = SocialPost::where('user_id', $request->user()->id)
            ->with(['targets.account'])
            ->findOrFail($id);

        if (! $post->isApproved()) {
            return back()->with('error', 'Post must be approved before publishing.');
        }

        $target = $post->targets()->where('status', '!=', 'published')->first();
        if (! $target) {
            return back()->with('error', 'No publishable target found.');
        }

        try {
            $service = app(\App\Services\Social\LinkedInPublishService::class);
            $service->publish($target);
            return redirect()->route('social-studio.published')->with('success', 'Post published to LinkedIn!');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Publish failed: ' . $e->getMessage());
        }
    }

    private function parseHashtags(string $input): array
    {
        return collect(preg_split('/[\s,]+/', $input))
            ->filter()
            ->map(fn ($h) => ltrim($h, '#'))
            ->filter()
            ->values()
            ->all();
    }

    private function toUtc(string $datetime, string $timezone): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $datetime, $timezone)
            ->setTimezone('UTC')
            ->toDateTimeString();
    }
}
