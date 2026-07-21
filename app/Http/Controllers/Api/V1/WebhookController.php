<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Webhooks\WebhookDispatcher;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Outgoing webhooks (collections v3): signed POSTs on record/form events.
 * The secret is shown once at creation; deliveries carry
 * X-Cms-Signature = HMAC-SHA256(raw body, secret).
 */
class WebhookController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $hooks = Webhook::where('site_id', $site->id)->orderBy('created_at')->get()
            ->map(fn ($h) => $this->serialize($h));

        return response()->json(['data' => $hooks]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $validated = $this->validated($request);
        if (Webhook::where('site_id', $site->id)->count() >= 20) {
            return response()->json(['message' => 'At most 20 webhooks per site.'], 422);
        }

        $secret = Str::random(48);
        $hook = Webhook::create($validated + ['site_id' => $site->id, 'secret' => $secret]);

        // The only response that ever includes the secret.
        return response()->json(['data' => $this->serialize($hook) + ['secret' => $secret]], 201);
    }

    public function update(Request $request, Site $site, Webhook $webhook): JsonResponse
    {
        $this->authorize('update', $site);
        abort_if($webhook->site_id !== $site->id, 404);

        $webhook->update($this->validated($request));

        return response()->json(['data' => $this->serialize($webhook)]);
    }

    public function destroy(Site $site, Webhook $webhook): JsonResponse
    {
        $this->authorize('update', $site);
        abort_if($webhook->site_id !== $site->id, 404);

        $webhook->delete();

        return response()->json(['message' => 'Webhook deleted.']);
    }

    /** Fire a test event so the receiver can be verified end to end. */
    public function test(Site $site, Webhook $webhook, WebhookDispatcher $dispatcher): JsonResponse
    {
        $this->authorize('update', $site);
        abort_if($webhook->site_id !== $site->id, 404);

        $dispatcher->dispatch($site, 'record.updated', [
            'test' => true,
            'message' => 'Test delivery from ' . config('app.name'),
        ]);

        return response()->json(['message' => 'Test delivery queued.']);
    }

    /** Recent deliveries for debugging. */
    public function deliveries(Site $site, Webhook $webhook): JsonResponse
    {
        $this->authorize('update', $site);
        abort_if($webhook->site_id !== $site->id, 404);

        $rows = WebhookDelivery::where('webhook_id', $webhook->id)
            ->orderByDesc('created_at')->limit(25)
            ->get(['id', 'event', 'status', 'attempts', 'response_code', 'next_attempt_at', 'created_at']);

        return response()->json(['data' => $rows]);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2000', 'url:https'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:' . implode(',', Webhook::EVENTS)],
            'active' => ['sometimes', 'boolean'],
        ]);
        $validated['events'] = array_values(array_unique($validated['events']));

        return $validated;
    }

    private function serialize(Webhook $hook): array
    {
        return [
            'id' => $hook->id,
            'url' => $hook->url,
            'events' => $hook->events,
            'active' => $hook->active,
            'last_delivered_at' => $hook->last_delivered_at?->toISOString(),
            'last_status' => $hook->last_status,
            'created_at' => $hook->created_at?->toISOString(),
        ];
    }
}
