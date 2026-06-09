<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialActivityLog;
use App\Models\SocialMediaAsset;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Services\Social\SocialPublisherService;
use App\Services\Social\SocialProviderCatalog;
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
        $accounts = $this->publishableAccounts($user->id);

        return view('social-studio.posts.create', compact('assets', 'accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title_internal'   => 'required|string|max:500',
            'topic'            => 'nullable|string|max:255',
            'post_body'        => 'required|string|max:65000',
            'post_type'        => ['required', Rule::in(['text', 'image', 'article_link'])],
            'article_url'      => 'nullable|url|max:2048|required_if:post_type,article_link',
            'hashtags'         => 'nullable|string|max:500',
            'source_notes'     => 'nullable|string|max:5000',
            'scheduled_at'     => 'nullable|date',
            'timezone_display' => 'nullable|string|max:100',
            'featured_asset_id'=> 'nullable|integer|exists:social_media_assets,id',
            'visibility'       => ['nullable', Rule::in(['PUBLIC', 'CONNECTIONS'])],
            'target_accounts'  => 'nullable|array',
            'target_accounts.*'=> 'integer',
            'target_meta'      => 'nullable|array',
        ]);

        $user = $request->user();
        $body = $this->sanitizeContent($data['post_body']);
        $scheduledAt = isset($data['scheduled_at']) ? $this->toUtc($data['scheduled_at'], $data['timezone_display'] ?? 'Asia/Karachi') : null;

        $post = SocialPost::create([
            'tenant_id'        => $user->tenant_id,
            'user_id'          => $user->id,
            'title_internal'   => $data['title_internal'],
            'topic'            => $data['topic'] ?? null,
            'post_body'        => $body,
            'post_type'        => $data['post_type'],
            'article_url'      => $data['article_url'] ?? null,
            'hashtags_json'    => $this->parseHashtags($data['hashtags'] ?? ''),
            'source_notes'     => $data['source_notes'] ?? null,
            'scheduled_at'     => $scheduledAt,
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

        $this->syncTargets($post, $request, $scheduledAt);

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
        $post = SocialPost::where('user_id', $request->user()->id)
            ->with(['targets.account.provider', 'mediaAssets'])
            ->findOrFail($id);
        if (! $post->isEditable()) {
            return redirect()->route('social-studio.posts.show', $id)
                ->with('error', 'Published or cancelled posts cannot be edited.');
        }
        $assets = SocialMediaAsset::where('user_id', $request->user()->id)
            ->where('approval_status', 'approved')
            ->get();
        $accounts = $this->publishableAccounts($request->user()->id);

        return view('social-studio.posts.edit', compact('post', 'assets', 'accounts'));
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
            'post_body'        => 'required|string|max:65000',
            'post_type'        => ['required', Rule::in(['text', 'image', 'article_link'])],
            'article_url'      => 'nullable|url|max:2048',
            'hashtags'         => 'nullable|string|max:500',
            'source_notes'     => 'nullable|string|max:5000',
            'scheduled_at'     => 'nullable|date',
            'timezone_display' => 'nullable|string|max:100',
            'featured_asset_id'=> 'nullable|integer|exists:social_media_assets,id',
            'visibility'       => ['nullable', Rule::in(['PUBLIC', 'CONNECTIONS'])],
            'target_accounts'  => 'nullable|array',
            'target_accounts.*'=> 'integer',
            'target_meta'      => 'nullable|array',
        ]);

        $wasApproved = $post->approval_status === 'approved';
        $body = $this->sanitizeContent($data['post_body']);
        $scheduledAt = isset($data['scheduled_at']) ? $this->toUtc($data['scheduled_at'], $data['timezone_display'] ?? 'Asia/Karachi') : null;

        $post->update([
            'title_internal'   => $data['title_internal'],
            'topic'            => $data['topic'] ?? null,
            'post_body'        => $body,
            'post_type'        => $data['post_type'],
            'article_url'      => $data['article_url'] ?? null,
            'hashtags_json'    => $this->parseHashtags($data['hashtags'] ?? ''),
            'source_notes'     => $data['source_notes'] ?? null,
            'scheduled_at'     => $scheduledAt,
            'timezone_display' => $data['timezone_display'] ?? 'Asia/Karachi',
            // Editing after approval resets approval
            'approval_status'  => $wasApproved ? 'pending_review' : $post->approval_status,
            'approved_at'      => $wasApproved ? null : $post->approved_at,
            'approved_by'      => $wasApproved ? null : $post->approved_by,
            'status'           => $wasApproved ? 'ready_for_review' : $post->status,
        ]);

        if (! empty($data['featured_asset_id'])) {
            $asset = SocialMediaAsset::where('user_id', $request->user()->id)->find($data['featured_asset_id']);
            if ($asset) {
                $post->mediaAssets()->syncWithoutDetaching([
                    $asset->id => ['is_featured' => true, 'display_order' => 0],
                ]);
            }
        }

        $this->syncTargets($post->fresh(), $request, $scheduledAt);

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
        $post->targets()->whereNot('status', 'published')->update([
            'status' => $post->scheduled_at ? 'scheduled' : 'approved',
            'scheduled_at' => $post->scheduled_at,
        ]);

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

    public function publishNow(Request $request, int $id, SocialPublisherService $publisher): RedirectResponse
    {
        $request->validate(['confirm' => 'accepted']);

        $post = SocialPost::where('user_id', $request->user()->id)
            ->with(['targets.account'])
            ->findOrFail($id);

        if (! $post->isApproved()) {
            return back()->with('error', 'Post must be approved before publishing.');
        }

        $targets = $post->targets()->whereNot('status', 'published')->get();
        if ($targets->isEmpty()) {
            return back()->with('error', 'No publish targets selected. Add at least one connected account or WordPress site.');
        }

        try {
            foreach ($targets as $target) {
                $publisher->publish($target);
            }

            return redirect()->route('social-studio.published')->with('success', 'Post publishing completed for selected targets.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Publish failed: ' . $e->getMessage());
        }
    }

    private function syncTargets(SocialPost $post, Request $request, ?string $scheduledAt): void
    {
        $selectedIds = collect($request->input('target_accounts', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $accounts = SocialAccount::where('user_id', $request->user()->id)
            ->whereIn('id', $selectedIds)
            ->where('status', 'connected')
            ->with('provider')
            ->get()
            ->filter(fn (SocialAccount $account) => SocialProviderCatalog::providerSupportsPublishing($account->provider?->capabilities_json))
            ->keyBy('id');

        $post->targets()
            ->whereNot('status', 'published')
            ->whereNotIn('social_account_id', $accounts->keys())
            ->delete();

        foreach ($accounts as $account) {
            $providerKey = $account->provider->key;
            $targetMeta = $request->input("target_meta.{$account->id}", []);

            [$body, $metadata] = $this->buildTargetPayload($providerKey, $post, $targetMeta, $request);

            SocialPostTarget::updateOrCreate(
                [
                    'social_post_id' => $post->id,
                    'social_account_id' => $account->id,
                ],
                [
                    'provider_key' => $providerKey,
                    'platform_body' => $body,
                    'platform_metadata_json' => $metadata,
                    'status' => $post->approval_status === 'approved'
                        ? ($scheduledAt ? 'scheduled' : 'approved')
                        : 'draft',
                    'scheduled_at' => $scheduledAt,
                ],
            );
        }
    }

    private function buildTargetPayload(string $providerKey, SocialPost $post, array $targetMeta, Request $request): array
    {
        if ($providerKey === 'wordpress') {
            $content = trim((string) ($targetMeta['content'] ?? ''));

            return [
                $content !== '' ? $this->sanitizeContent($content) : $post->post_body,
                [
                    'title' => $targetMeta['title'] ?? $post->title_internal,
                    'excerpt' => $targetMeta['excerpt'] ?? null,
                    'slug' => $targetMeta['slug'] ?? null,
                    'wp_status' => in_array(($targetMeta['wp_status'] ?? 'draft'), ['draft', 'publish'], true)
                        ? $targetMeta['wp_status']
                        : 'draft',
                    'featured_asset_id' => $targetMeta['featured_asset_id'] ?? $request->input('featured_asset_id'),
                ],
            ];
        }

        return [
            trim((string) ($targetMeta['content'] ?? '')) ?: strip_tags($post->post_body),
            ['visibility' => $targetMeta['visibility'] ?? $request->input('visibility', 'PUBLIC')],
        ];
    }

    private function publishableAccounts(int $userId)
    {
        return SocialAccount::where('user_id', $userId)
            ->where('status', 'connected')
            ->with('provider')
            ->orderByDesc('is_default')
            ->orderBy('display_name')
            ->get()
            ->filter(fn (SocialAccount $account) => SocialProviderCatalog::providerSupportsPublishing($account->provider?->capabilities_json))
            ->values();
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

    private function sanitizeContent(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/iu', '', $html) ?? $html;
        $html = preg_replace('/\s(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/iu', '', $html) ?? $html;

        return trim($html);
    }
}
