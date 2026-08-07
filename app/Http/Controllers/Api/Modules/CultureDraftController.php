<?php

namespace App\Http\Controllers\Api\Modules;

use App\Domain\Modules\Culture\CultureDraftService;
use App\Http\Controllers\Controller;
use App\Models\ModuleIdempotencyKey;
use App\Models\ModuleTenant;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Culture Engine receiving endpoint: POST /api/modules/culture-engine/drafts.
 *
 * Auth + tenant context + module-enabled are established by the route
 * middleware (module.token → module). This controller validates the bulletin,
 * rejects unknown block types (422, nothing persisted), enforces idempotency,
 * and files a draft Post via CultureDraftService.
 */
class CultureDraftController extends Controller
{
    public function __construct(private CultureDraftService $service)
    {
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\ModuleToken $token */
        $token = $request->attributes->get('module_token');
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('module_tenant');
        $module = $token->module;

        if (!$tenant) {
            return response()->json(['error' => 'platform_token_not_allowed',
                'message' => 'This endpoint requires a tenant-scoped token.'], 422);
        }

        $payload = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:250'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:250'],
            'status' => ['sometimes', 'in:draft'], // draft-only; never auto-publish
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:120'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'blocks' => ['present', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);

        // Unknown block types → 422, persist nothing.
        $unknown = $this->service->unknownBlockTypes($payload['blocks']);
        if ($unknown) {
            return response()->json([
                'error' => 'unknown_block_types',
                'types' => $unknown,
            ], 422);
        }

        $site = $this->resolveSite($tenant, $module->id);
        if (!$site) {
            return response()->json(['error' => 'no_target_site',
                'message' => 'No site is configured for this tenant.'], 422);
        }

        $key = $request->header('Idempotency-Key');
        $hash = hash('sha256', $request->getContent());

        if ($key) {
            $existing = ModuleIdempotencyKey::query()
                ->where('module_id', $module->id)
                ->where('tenant_id', $tenant->id)
                ->where('idempotency_key', $key)
                ->first();

            if ($existing) {
                if ($existing->payload_hash === $hash) {
                    return response()->json($this->draftResponse($existing->external_id, $site), 200);
                }
                return response()->json([
                    'error' => 'idempotency_key_conflict',
                    'message' => 'This Idempotency-Key was already used with a different payload.',
                ], 409);
            }
        }

        $post = DB::transaction(function () use ($site, $payload, $key, $hash, $module, $tenant) {
            $post = $this->service->createDraft($site, $payload);

            if ($key) {
                ModuleIdempotencyKey::create([
                    'module_id' => $module->id,
                    'tenant_id' => $tenant->id,
                    'idempotency_key' => $key,
                    'payload_hash' => $hash,
                    'external_id' => $post->id,
                    'entity_type' => 'post',
                    'entity_id' => $post->id,
                ]);
            }

            return $post;
        });

        return response()->json($this->draftResponse($post->id, $site), 201);
    }

    private function resolveSite(Tenant $tenant, string $moduleId): ?Site
    {
        $pivot = ModuleTenant::query()
            ->where('module_id', $moduleId)
            ->where('tenant_id', $tenant->id)
            ->first();

        $targetId = $pivot?->settings['target_site_id'] ?? null;
        if ($targetId) {
            $site = Site::where('id', $targetId)->where('tenant_id', $tenant->id)->first();
            if ($site) {
                return $site;
            }
        }

        // Fall back to the tenant's earliest-created site (RLS already scopes this).
        return Site::where('tenant_id', $tenant->id)->orderBy('created_at')->first();
    }

    private function draftResponse(string $externalId, Site $site): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            'external_id' => $externalId,
            'status' => 'draft',
            'admin_url' => "{$base}/admin/sites/{$site->id}/posts/{$externalId}/edit",
        ];
    }
}
