<?php

namespace App\Http\Controllers\Api\Social;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\SocialActivityLog;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiDraftController extends GptController
{
    /**
     * POST /api/social/v1/drafts
     *
     * ChatGPT/AI may create drafts and suggest a schedule, but NEVER auto-publishes.
     * All posts land with status=draft, approval_status=pending_review.
     * A human must approve before the scheduler will pick it up.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title_internal'         => 'required|string|max:500',
            'topic'                  => 'nullable|string|max:255',
            'post_body'              => 'required|string|max:3000',
            'post_type'              => ['required', Rule::in(['text', 'image', 'article_link'])],
            'article_url'            => 'nullable|url|max:2048',
            'hashtags'               => 'nullable|array',
            'hashtags.*'             => 'string|max:100',
            'source_notes'           => 'nullable|string|max:5000',
            'suggested_scheduled_at' => 'nullable|date|after:now',
            'timezone_display'       => 'nullable|string|max:100',
            'visibility'             => ['nullable', Rule::in(['PUBLIC', 'CONNECTIONS'])],
        ]);

        $user = $this->apiUser($request);

        $post = SocialPost::create([
            'tenant_id'        => $user->tenant_id,
            'user_id'          => $user->id,
            'title_internal'   => $data['title_internal'],
            'topic'            => $data['topic'] ?? null,
            'post_body'        => $data['post_body'],
            'post_type'        => $data['post_type'],
            'article_url'      => $data['article_url'] ?? null,
            'hashtags_json'    => $data['hashtags'] ?? [],
            'source_notes'     => $data['source_notes'] ?? null,
            // Suggested schedule stored but NOT activated — requires human approval first
            'scheduled_at'     => isset($data['suggested_scheduled_at'])
                ? $this->toUtc($data['suggested_scheduled_at'], $data['timezone_display'] ?? 'Asia/Karachi')
                : null,
            'timezone_display' => $data['timezone_display'] ?? 'Asia/Karachi',
            'status'           => 'draft',
            'approval_status'  => 'pending_review',
            'created_source'   => 'chatgpt',
        ]);

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
            "Draft created via AI/ChatGPT: {$post->title_internal}"
        );

        $this->audit($request, 'social_draft_created', 'social_post', $post->id, 'low',
            "type={$post->post_type}, topic=" . ($post->topic ?? 'none'));

        return response()->json([
            'message'            => 'Draft created. A human must approve it in the CRM before it will be published.',
            'post_id'            => $post->id,
            'status'             => $post->status,
            'approval_status'    => $post->approval_status,
            'suggested_schedule' => $post->scheduled_at?->toIso8601String(),
            'review_url'         => route('social-studio.posts.show', $post->id),
            'warning'            => 'This draft will NOT be published automatically. Approval is required.',
        ], 201);
    }

    /**
     * GET /api/social/v1/drafts
     *
     * List recent AI-created drafts so ChatGPT can check their status.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $drafts = SocialPost::where('user_id', $user->id)
            ->where('created_source', 'chatgpt')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'title_internal', 'status', 'approval_status', 'scheduled_at', 'created_at']);

        return response()->json(['drafts' => $drafts]);
    }

    private function toUtc(string $datetime, string $timezone): string
    {
        return \Carbon\Carbon::parse($datetime, $timezone)
            ->setTimezone('UTC')
            ->toDateTimeString();
    }
}
