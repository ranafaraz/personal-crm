<?php

namespace App\Http\Controllers\Api\Gpt\V1\Webhook;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends GptController
{
    private const STATUSES = ['active', 'paused'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:' . implode(',', self::STATUSES),
            'search' => 'nullable|string|max:200',
            'limit'  => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = Webhook::where('user_id', $user->id)->withCount('deliveries');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->input('search')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $webhooks = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'data'  => $webhooks->map(fn ($w) => $this->format($w)),
            'count' => $webhooks->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'url'      => 'required|url|max:2000',
            'events'   => 'nullable|array',
            'events.*' => 'string|max:100',
            'secret'   => 'nullable|string|max:255',
            'status'   => 'nullable|in:' . implode(',', self::STATUSES),
            'meta'     => 'nullable|array',
        ]);

        $user = $this->apiUser($request);

        $webhook = Webhook::create([
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
            'name'      => $data['name'],
            'url'       => $data['url'],
            'events'    => $data['events'] ?? null,
            'secret'    => $data['secret'] ?? null,
            'status'    => $data['status'] ?? 'active',
            'meta'      => $data['meta'] ?? null,
        ]);

        $this->audit($request, 'create_webhook', 'webhook', $webhook->id, 'low',
            "name={$webhook->name}, url={$webhook->url}", "id={$webhook->id}");

        return response()->json(['data' => $this->format($webhook)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $webhook = Webhook::where('user_id', $this->apiUser($request)->id)
            ->withCount('deliveries')
            ->findOrFail($id);

        return response()->json(['data' => $this->format($webhook)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'url'      => 'sometimes|url|max:2000',
            'events'   => 'sometimes|nullable|array',
            'events.*' => 'string|max:100',
            'secret'   => 'sometimes|nullable|string|max:255',
            'status'   => 'sometimes|in:' . implode(',', self::STATUSES),
            'meta'     => 'sometimes|nullable|array',
        ]);

        $user    = $this->apiUser($request);
        $webhook = Webhook::where('user_id', $user->id)->findOrFail($id);

        foreach (['name', 'url', 'events', 'secret', 'status', 'meta'] as $field) {
            if (array_key_exists($field, $data)) {
                $webhook->{$field} = $data[$field];
            }
        }

        $webhook->save();

        $this->audit($request, 'update_webhook', 'webhook', $webhook->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$webhook->id}");

        $webhook->loadCount('deliveries');

        return response()->json(['data' => $this->format($webhook)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $webhook = Webhook::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $webhook->delete();

        $this->audit($request, 'delete_webhook', 'webhook', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Record a test delivery for a webhook. This logs a WebhookDelivery and
     * stamps last_triggered_at — it does NOT make an outbound HTTP request
     * (there is no dispatcher; deliveries are recorded as pending). Paused
     * webhooks cannot be tested.
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'event'   => 'nullable|string|max:100',
            'payload' => 'nullable|array',
        ]);

        $user    = $this->apiUser($request);
        $webhook = Webhook::where('user_id', $user->id)->findOrFail($id);

        if ($webhook->status !== 'active') {
            return response()->json([
                'error'  => "Webhook cannot be tested while status is '{$webhook->status}'. Only active webhooks can fire.",
                'status' => $webhook->status,
            ], 422);
        }

        $delivery = WebhookDelivery::create([
            'user_id'    => $user->id,
            'tenant_id'  => $user->tenant_id,
            'webhook_id' => $webhook->id,
            'event'      => $data['event'] ?? 'test.ping',
            'status'     => 'pending',
            'payload'    => $data['payload'] ?? ['test' => true],
            'attempts'   => 0,
        ]);

        $webhook->forceFill(['last_triggered_at' => now()])->save();

        $this->audit($request, 'test_webhook', 'webhook', $webhook->id, 'medium',
            "id={$webhook->id}, event={$delivery->event}", "delivery_id={$delivery->id}");

        $webhook->loadCount('deliveries');

        return response()->json([
            'data'     => $this->format($webhook),
            'delivery' => $this->formatDelivery($delivery),
        ], 201);
    }

    public function format(Webhook $w): array
    {
        return [
            'id'                => $w->id,
            'name'              => $w->name,
            'url'               => $w->url,
            'events'            => $w->events,
            'has_secret'        => ! empty($w->secret),
            'status'            => $w->status,
            'failure_count'     => $w->failure_count,
            'deliveries_count'  => $w->deliveries_count ?? null,
            'last_triggered_at' => $w->last_triggered_at?->toISOString(),
            'meta'              => $w->meta,
            'created_at'        => $w->created_at?->toISOString(),
            'updated_at'        => $w->updated_at?->toISOString(),
        ];
    }

    private function formatDelivery(WebhookDelivery $d): array
    {
        return [
            'id'         => $d->id,
            'webhook_id' => $d->webhook_id,
            'event'      => $d->event,
            'status'     => $d->status,
            'payload'    => $d->payload,
            'attempts'   => $d->attempts,
            'created_at' => $d->created_at?->toISOString(),
        ];
    }
}
