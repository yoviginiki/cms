<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Blocks\Services\BlockService;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use App\Support\Slugify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * S5 — Form Wizard: one guided flow that composes a configured customform
 * block and appends it to a chosen page (existing blocks preserved via
 * getBlockTree + full re-sync; block ids survive re-sync by design).
 */
class FormWizardController extends Controller
{
    public function __construct(private BlockService $blockService) {}

    public function create(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'page_id' => ['required', 'uuid'],
            'fields' => ['required', 'array', 'min:1', 'max:20'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'in:text,email,textarea,select,checkbox,radio'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.*.options' => ['sometimes', 'array', 'max:50'],
            'fields.*.options.*' => ['string', 'max:120'],
            'notify_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'success_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'submit_text' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $page = Page::where('site_id', $site->id)->find($validated['page_id']);
        if (!$page) {
            throw ValidationException::withMessages(['page_id' => 'Page not found on this site.']);
        }

        $formKey = $this->uniqueFormKey($site, $validated['name']);

        $formBlock = [
            'id' => Str::uuid()->toString(),
            'type' => 'customform',
            'level' => 'module',
            'order' => 0,
            'data' => [
                'formKey' => $formKey,
                'fields' => array_map(fn ($f) => [
                    'label' => $f['label'],
                    'type' => $f['type'],
                    'required' => (bool) ($f['required'] ?? false),
                    'placeholder' => $f['placeholder'] ?? '',
                    'options' => array_values($f['options'] ?? []),
                ], $validated['fields']),
                'notifyEmail' => $validated['notify_email'] ?? '',
                'successMessage' => $validated['success_message'] ?? 'Thank you! We received your message.',
                'submitText' => $validated['submit_text'] ?? 'Send',
            ],
            'children' => [],
        ];

        $tree = $this->blockService->getBlockTree($page);
        $tree[] = [
            'id' => Str::uuid()->toString(),
            'type' => 'section',
            'level' => 'section',
            'order' => count($tree),
            'data' => ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '820px'],
            'children' => [[
                'id' => Str::uuid()->toString(),
                'type' => 'row',
                'level' => 'row',
                'order' => 0,
                'data' => ['layout' => '1', 'gap' => '24px'],
                'children' => [[
                    'id' => Str::uuid()->toString(),
                    'type' => 'column',
                    'level' => 'column',
                    'order' => 0,
                    'data' => [],
                    'children' => [$formBlock],
                ]],
            ]],
        ];

        $this->blockService->syncBlocks($page, $tree);
        Page::whereKey($page->id)->toBase()->update([
            'content_modified_at' => now(),
            'needs_republish' => true,
            'needs_republish_reason' => 'form_added',
        ]);

        return response()->json(['data' => [
            'form_key' => $formKey,
            'page_id' => $page->id,
            'page_slug' => $page->slug,
        ]], 201);
    }

    /** Slugged name, made unique across the site's existing customform keys. */
    private function uniqueFormKey(Site $site, string $name): string
    {
        $base = Slugify::slug($name) ?: 'form';
        $existing = Block::whereHasMorph('blockable', ['page', 'post'], fn ($q) => $q->where('site_id', $site->id))
            ->where('type', 'customform')
            ->pluck('data')
            ->map(fn ($d) => $d['formKey'] ?? null)
            ->filter()
            ->all();

        $key = $base;
        $n = 2;
        while (in_array($key, $existing, true)) {
            $key = "{$base}-{$n}";
            $n++;
        }

        return $key;
    }
}
