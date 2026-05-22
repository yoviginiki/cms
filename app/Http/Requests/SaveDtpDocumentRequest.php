<?php

namespace App\Http\Requests;

use App\Domain\Magazine\Enums\FrameType;
use Illuminate\Foundation\Http\FormRequest;

class SaveDtpDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        $frameTypes = implode(',', array_column(FrameType::cases(), 'value'));

        return [
            // Spreads
            'spreads' => ['present', 'array', 'max:50'],
            'spreads.*.id' => ['nullable', 'string', 'max:36'],
            'spreads.*.spread_index' => ['required', 'integer', 'min:0'],
            'spreads.*.name' => ['nullable', 'string', 'max:255'],
            'spreads.*.metadata' => ['nullable', 'array'],

            // Pages
            'pages' => ['present', 'array', 'max:200'],
            'pages.*.id' => ['nullable', 'string', 'max:36'],
            'pages.*.spread_id' => ['nullable', 'string'],
            'pages.*.page_index' => ['required', 'integer', 'min:0'],
            'pages.*.side' => ['required', 'string', 'in:single,left,right'],
            'pages.*.width' => ['required', 'integer', 'min:50', 'max:10000'],
            'pages.*.height' => ['required', 'integer', 'min:50', 'max:10000'],
            'pages.*.bleed' => ['nullable', 'array'],
            'pages.*.margins' => ['nullable', 'array'],
            'pages.*.safe_area' => ['nullable', 'array'],
            'pages.*.background' => ['nullable', 'array'],
            'pages.*.master_page_id' => ['nullable', 'string'],
            'pages.*.metadata' => ['nullable', 'array'],

            // Layers
            'layers' => ['present', 'array', 'max:100'],
            'layers.*.id' => ['nullable', 'string', 'max:36'],
            'layers.*.page_id' => ['nullable', 'string'],
            'layers.*.name' => ['required', 'string', 'max:255'],
            'layers.*.layer_order' => ['required', 'integer', 'min:0'],
            'layers.*.visible' => ['boolean'],
            'layers.*.locked' => ['boolean'],
            'layers.*.metadata' => ['nullable', 'array'],

            // Frames
            'frames' => ['present', 'array', 'max:500'],
            'frames.*.id' => ['nullable', 'string', 'max:36'],
            'frames.*.page_id' => ['nullable', 'string'],
            'frames.*.spread_id' => ['nullable', 'string'],
            'frames.*.layer_id' => ['nullable', 'string'],
            'frames.*.frame_type' => ['required', 'string', "in:{$frameTypes}"],
            'frames.*.name' => ['nullable', 'string', 'max:255'],
            'frames.*.x' => ['required', 'numeric'],
            'frames.*.y' => ['required', 'numeric'],
            'frames.*.width' => ['required', 'numeric', 'min:1', 'max:10000'],
            'frames.*.height' => ['required', 'numeric', 'min:1', 'max:10000'],
            'frames.*.rotation' => ['numeric', 'min:0', 'max:360'],
            'frames.*.z_index' => ['integer', 'min:-100', 'max:9999'],
            'frames.*.visible' => ['boolean'],
            'frames.*.locked' => ['boolean'],
            'frames.*.content' => ['nullable', 'array'],
            'frames.*.style' => ['nullable', 'array'],
            'frames.*.metadata' => ['nullable', 'array'],

            // Issue metadata (layout mode, cover mode, etc.)
            'meta' => ['nullable', 'array'],
            'meta.issueSettings' => ['nullable', 'array'],
            'meta.issueSettings.layoutMode' => ['nullable', 'string', 'in:single,book,presentation'],
            'meta.issueSettings.coverMode' => ['nullable', 'string', 'in:standalone,spread'],
            'meta.issueSettings.readingDirection' => ['nullable', 'string', 'in:ltr,rtl'],
            'meta.viewerSettings' => ['nullable', 'array'],
            'meta.viewerSettings.display_mode' => ['nullable', 'string', 'in:spread,single,scroll,flipbook'],
            'meta.viewerSettings.bg_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'meta.viewerSettings.ui_theme' => ['nullable', 'string', 'in:dark,light'],
            'meta.viewerSettings.page_transition' => ['nullable', 'string', 'in:slide,fade,flip,turn,none'],
            'meta.viewerSettings.transition_speed' => ['nullable', 'integer', 'min:100', 'max:2000'],
            'meta.viewerSettings.show_thumbnails' => ['nullable', 'boolean'],
            'meta.viewerSettings.show_page_numbers' => ['nullable', 'boolean'],
            'meta.viewerSettings.auto_hide_ui' => ['nullable', 'boolean'],

            // Asset references
            'asset_references' => ['sometimes', 'array', 'max:500'],
            'asset_references.*.frame_id' => ['nullable', 'string'],
            'asset_references.*.source_url' => ['nullable', 'string', 'max:500'],
            'asset_references.*.alt' => ['nullable', 'string', 'max:500'],
            'asset_references.*.caption' => ['nullable', 'string', 'max:1000'],
            'asset_references.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Reject unsafe image URLs (allow http/https and relative /paths)
            foreach ($this->input('frames', []) as $i => $frame) {
                $src = $frame['content']['src'] ?? null;
                if (is_string($src) && $src !== '' && !preg_match('#^(https?://|/)#i', $src)) {
                    $v->errors()->add("frames.{$i}.content.src", 'Image URL must use http, https, or a relative path.');
                }
            }
            foreach ($this->input('asset_references', []) as $i => $ref) {
                $url = $ref['source_url'] ?? null;
                if (is_string($url) && $url !== '' && !preg_match('#^https?://#i', $url)) {
                    $v->errors()->add("asset_references.{$i}.source_url", 'URL must use http or https.');
                }
            }
        });
    }
}
