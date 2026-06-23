<?php

namespace App\Http\Controllers\Api\Gpt\V1\Webhook;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only access to the webhook delivery log. Deliveries are created by
 * WebhookController::test (and, in a future dispatcher, by event triggers);
 * they are not created or mutated directly through this controller.
 */
class WebhookDeliveryController extends GptController
{
    private const STATUSES = ['pending', 'success', 'failed'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'webhook_id' => 'nullable|integer',
            'status'     => 'nullable|string|in:' . implode(',', self::STATUSES),
            'event'      => 'nullable|string|max:100',
            'limit'      => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = WebhookDelivery::where('user_id', $user->id)->with('webhook:id,name,status');

        if ($webhookId = $request->input('webhook_id')) {
            $query->where('webhook_id', $webhookId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        $deliveries = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'data'  => $deliveries->map(fn ($d) => $this->format($d)),
            'count' => $deliveries->count(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $delivery = WebhookDelivery::where('user_id', $this->apiUser($request)->id)
            ->with('webhook:id,name,status')
            ->findOrFail($id);

        return response()->json(['data' => $this->format($delivery)]);
    }

    public function format(WebhookDelivery $d): array
    {
        return [
            'id'            => $d->id,
            'webhook_id'    => $d->webhook_id,
            'event'         => $d->event,
            'status'        => $d->status,
            'payload'       => $d->payload,
            'response_code' => $d->response_code,
            'response_body' => $d->response_body,
            'attempts'      => $d->attempts,
            'delivered_at'  => $d->delivered_at?->toISOString(),
            'webhook'       => $d->relationLoaded('webhook') ? $d->webhook?->only(['id', 'name', 'status']) : null,
            'created_at'    => $d->created_at?->toISOString(),
            'updated_at'    => $d->updated_at?->toISOString(),
        ];
    }
}
