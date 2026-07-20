<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Forms\Services\FormSubmissionService;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\FormSubmission;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * S5 Forms v2 — the published site stays static: form blocks render plain
 * HTML <form>s POSTing here (the admin origin). The field schema is looked
 * up server-side from the form block itself; submissions land in the
 * RLS-scoped form_submissions table.
 */
class FormController extends Controller
{
    public function __construct(private FormSubmissionService $formService) {}

    /** List submissions (auth). Optional ?form_key= filter. */
    public function submissions(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $query = FormSubmission::where('site_id', $site->id)->orderByDesc('created_at');
        if ($formKey = $request->input('form_key')) {
            $query->where('form_key', $formKey);
        }
        $page = $query->paginate(min(100, max(5, $request->integer('per_page', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn ($s) => $this->serialize($s)),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'form_keys' => FormSubmission::where('site_id', $site->id)
                    ->select('form_key')->distinct()->pluck('form_key'),
            ],
        ]);
    }

    public function deleteSubmission(Site $site, FormSubmission $submission): JsonResponse
    {
        $this->authorize('update', $site);
        abort_if($submission->site_id !== $site->id, 404);
        $submission->delete();

        return response()->json(['success' => true]);
    }

    /** Stream all submissions (optionally one form) as CSV. */
    public function export(Request $request, Site $site): StreamedResponse
    {
        $this->authorize('view', $site);
        $formKey = (string) $request->input('form_key', '');

        return response()->streamDownload(function () use ($site, $formKey) {
            $out = fopen('php://output', 'w');
            $columns = null;
            FormSubmission::where('site_id', $site->id)
                ->when($formKey !== '', fn ($q) => $q->where('form_key', $formKey))
                ->orderBy('created_at')
                ->chunk(200, function ($rows) use ($out, &$columns) {
                    foreach ($rows as $row) {
                        if ($columns === null) {
                            $columns = array_keys($row->data ?? []);
                            fputcsv($out, array_merge(['submitted_at', 'form'], $columns));
                        }
                        fputcsv($out, array_merge(
                            [$row->created_at?->toDateTimeString(), $row->form_key],
                            array_map(fn ($c) => is_bool($v = $row->data[$c] ?? '') ? ($v ? 'yes' : 'no') : (string) $v, $columns),
                        ));
                    }
                });
            fclose($out);
        }, "form-submissions-{$site->slug}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * Public generic endpoint — POST public/{site}/forms/{formKey}/submit.
     * Rate-limited; tenant context via public.site middleware.
     */
    public function submitPublic(Request $request, Site $site, string $formKey)
    {
        $block = $this->findFormBlock($site, $formKey);
        if (!$block) {
            return response()->json(['message' => 'This form no longer exists.'], 404);
        }

        return $this->handle($request, $site, $formKey, $block);
    }

    /** Legacy contact-form endpoint (already-published pages POST here). */
    public function submit(Request $request, Site $site)
    {
        $block = $this->findFormBlock($site, 'contact');
        if (!$block) {
            return response()->json(['message' => 'No contact form configured'], 404);
        }

        return $this->handle($request, $site, 'contact', $block);
    }

    private function handle(Request $request, Site $site, string $formKey, Block $block)
    {
        $schema = FormSubmissionService::schemaFromBlock($this->blockFields($block));
        if ($schema === []) {
            return response()->json(['message' => 'This form has no fields.'], 422);
        }

        $notify = $block->data['notifyEmail'] ?? $block->data['recipient_email'] ?? null;

        $result = $this->formService->submit($site, $formKey, $schema, $request->all(), is_string($notify) ? $notify : null, [
            'ip' => $request->ip(),
            'referer' => mb_substr((string) $request->headers->get('referer', ''), 0, 500),
        ]);

        // Progressive enhancement: fetch/XHR gets JSON; a plain no-JS POST
        // gets a 303 back to the page with the #<key>-success :target anchor.
        if ($request->expectsJson() || $request->headers->has('X-Requested-With')) {
            return response()->json(['success' => true, 'message' => 'Thank you!']);
        }

        $back = (string) $request->headers->get('referer', '/');
        $back = strtok($back, '#') . '#form-' . $formKey . '-success';

        return redirect()->away($back, 303);
    }

    /** @return array<int, array> the block's field config */
    private function blockFields(Block $block): array
    {
        if ($block->type === 'contact-form') {
            return $block->data['fields'] ?? [
                ['label' => 'Name', 'type' => 'text', 'required' => true],
                ['label' => 'Email', 'type' => 'email', 'required' => true],
                ['label' => 'Message', 'type' => 'textarea', 'required' => true],
            ];
        }

        return is_array($block->data['fields'] ?? null) ? $block->data['fields'] : [];
    }

    /**
     * Resolve the form block server-side — the schema source of truth.
     * 'contact' → the site's contact-form block; anything else → the
     * customform block whose formKey matches.
     */
    private function findFormBlock(Site $site, string $formKey): ?Block
    {
        // Form blocks live on pages, posts, record templates, or sliders — a
        // form on a product record-single template must resolve too, not just
        // page/post-hosted forms.
        $query = Block::whereHasMorph('blockable', ['page', 'post', 'template', 'slider'], function ($q) use ($site) {
            $q->where('site_id', $site->id);
        });

        if ($formKey === 'contact') {
            return $query->where('type', 'contact-form')->first();
        }

        return $query->where('type', 'customform')
            ->where('data->formKey', $formKey)
            ->first();
    }

    private function serialize(FormSubmission $s): array
    {
        return [
            'id' => $s->id,
            'form_key' => $s->form_key,
            'data' => $s->data ?: (object) [],
            'meta' => $s->meta ?: (object) [],
            'created_at' => $s->created_at?->toISOString(),
        ];
    }
}
