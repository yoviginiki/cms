<?php

namespace App\Domain\Magazine\Services;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Enums\FrameType;
use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Domain\Magazine\Models\MagazineSpread;

class DtpPreflightService
{
    public function runForIssue(MagazineIssue $issue): array
    {
        $spreads = MagazineSpread::where('issue_id', $issue->id)->orderBy('spread_index')->get();
        $pages = MagazineDtpPage::where('issue_id', $issue->id)->orderBy('page_index')->get();
        $frames = MagazineFrame::where('issue_id', $issue->id)->get();

        $items = [];

        // ─── Document structure ───
        if ($pages->isEmpty()) {
            $items[] = $this->item('error', 'NO_PAGES', 'Issue has no DTP pages.', blocking: true);
        }

        foreach ($pages as $page) {
            if ($page->width < 1 || $page->height < 1) {
                $items[] = $this->item('error', 'INVALID_PAGE_SIZE', "Page {$page->page_index}: invalid dimensions ({$page->width}x{$page->height}).", pageId: $page->id, blocking: true);
            }
        }

        // Check duplicate page indexes
        $pageIndexes = $pages->pluck('page_index')->toArray();
        if (count($pageIndexes) !== count(array_unique($pageIndexes))) {
            $items[] = $this->item('warning', 'DUPLICATE_PAGE_INDEX', 'Duplicate page indexes detected.');
        }

        // ─── Frames ───
        $validTypes = array_column(FrameType::cases(), 'value');
        $pageIds = $pages->pluck('id')->toArray();

        foreach ($frames as $i => $frame) {
            $name = $frame->name ?: "Frame #{$i}";
            $type = $frame->frame_type->value ?? (string) $frame->frame_type;
            $content = is_array($frame->content) ? $frame->content : [];
            $isVisible = $frame->visible;

            // Type validation
            if (!in_array($type, $validTypes)) {
                $items[] = $this->item('error', 'INVALID_FRAME_TYPE', "{$name}: invalid type '{$type}'.", frameId: $frame->id, blocking: true);
            }

            // Geometry validation
            if ($frame->width < 1 || $frame->height < 1) {
                $items[] = $this->item('error', 'INVALID_FRAME_SIZE', "{$name}: invalid dimensions ({$frame->width}x{$frame->height}).", frameId: $frame->id, blocking: true);
            }
            if (!is_finite((float) $frame->x) || !is_finite((float) $frame->y)) {
                $items[] = $this->item('error', 'INVALID_FRAME_POSITION', "{$name}: non-finite position.", frameId: $frame->id, blocking: true);
            }

            // Page ownership
            if ($frame->page_id && !in_array($frame->page_id, $pageIds)) {
                $items[] = $this->item('error', 'ORPHAN_FRAME', "{$name}: references non-existent page.", frameId: $frame->id, blocking: true);
            }

            // Skip content checks for hidden frames
            if (!$isVisible) {
                $items[] = $this->item('info', 'HIDDEN_FRAME', "{$name}: frame is hidden.", frameId: $frame->id);
                continue;
            }

            // ─── Text frame checks ───
            if (in_array($type, ['text', 'quote'])) {
                $html = $content['html'] ?? $content['text'] ?? '';
                if (trim(strip_tags($html)) === '') {
                    $items[] = $this->item('warning', 'EMPTY_TEXT_FRAME', "{$name}: empty text content.", frameId: $frame->id);
                }
            }

            // ─── Image frame checks ───
            if ($type === 'image') {
                $src = $content['src'] ?? '';
                if (!$src) {
                    $items[] = $this->item('error', 'MISSING_IMAGE', "{$name}: no image selected.", frameId: $frame->id, blocking: true);
                } elseif (!in_array(strtolower((string) parse_url($src, PHP_URL_SCHEME)), ['http', 'https'])) {
                    $items[] = $this->item('error', 'UNSAFE_IMAGE_URL', "{$name}: image URL uses unsafe scheme.", frameId: $frame->id, blocking: true);
                }

                if ($src && empty($content['alt'])) {
                    $items[] = $this->item('warning', 'MISSING_ALT_TEXT', "{$name}: missing alt text.", frameId: $frame->id);
                }

                $fitMode = $content['fitMode'] ?? 'fill';
                if (!in_array($fitMode, ['fill', 'fit', 'stretch', 'original'])) {
                    $items[] = $this->item('warning', 'INVALID_FIT_MODE', "{$name}: invalid fit mode '{$fitMode}'.", frameId: $frame->id);
                }

                $opacity = $content['opacity'] ?? 100;
                if (!is_numeric($opacity) || $opacity < 0 || $opacity > 100) {
                    $items[] = $this->item('warning', 'INVALID_OPACITY', "{$name}: opacity out of range.", frameId: $frame->id);
                }
            }

            // ─── Page bounds check ───
            if ($frame->page_id) {
                $page = $pages->firstWhere('id', $frame->page_id);
                if ($page) {
                    $right = $frame->x + $frame->width;
                    $bottom = $frame->y + $frame->height;

                    if ($frame->x >= $page->width || $frame->y >= $page->height || $right <= 0 || $bottom <= 0) {
                        $items[] = $this->item('error', 'FRAME_OUTSIDE_PAGE', "{$name}: completely outside page bounds.", frameId: $frame->id, pageId: $page->id, blocking: true);
                    } elseif ($frame->x < 0 || $frame->y < 0 || $right > $page->width || $bottom > $page->height) {
                        $bleed = is_array($page->bleed) ? $page->bleed : [];
                        $hasBleed = !empty($bleed);
                        if (!$hasBleed) {
                            $items[] = $this->item('warning', 'FRAME_OUTSIDE_PAGE', "{$name}: partially outside page bounds.", frameId: $frame->id, pageId: $page->id);
                        }
                    }

                    // Safe area / margins check
                    $margins = is_array($page->margins) ? $page->margins : [];
                    if (!empty($margins)) {
                        $ml = $margins['left'] ?? 0;
                        $mt = $margins['top'] ?? 0;
                        $mr = $margins['right'] ?? 0;
                        $mb = $margins['bottom'] ?? 0;
                        if ($frame->x < $ml || $frame->y < $mt || $right > $page->width - $mr || $bottom > $page->height - $mb) {
                            $items[] = $this->item('info', 'FRAME_OUTSIDE_SAFE_AREA', "{$name}: extends beyond margins.", frameId: $frame->id, pageId: $page->id);
                        }
                    }
                }
            }
        }

        // ─── Calculate result ───
        $errors = array_filter($items, fn ($i) => $i['severity'] === 'error');
        $warnings = array_filter($items, fn ($i) => $i['severity'] === 'warning');
        $infos = array_filter($items, fn ($i) => $i['severity'] === 'info');
        $blocking = array_filter($items, fn ($i) => $i['blocking'] ?? false);

        $score = 100;
        $score -= count($errors) * 15;
        $score -= count($warnings) * 5;
        $score -= count($infos) * 1;
        $score = max(0, min(100, $score));

        $status = count($blocking) > 0 ? 'error' : (count($warnings) > 0 ? 'warning' : 'pass');

        return [
            'status' => $status,
            'score' => $score,
            'counts' => [
                'errors' => count($errors),
                'warnings' => count($warnings),
                'info' => count($infos),
                'blocking' => count($blocking),
            ],
            'items' => array_values($items),
        ];
    }

    private function item(
        string $severity,
        string $code,
        string $message,
        ?string $frameId = null,
        ?string $pageId = null,
        ?string $spreadId = null,
        bool $blocking = false,
    ): array {
        return [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'frame_id' => $frameId,
            'page_id' => $pageId,
            'spread_id' => $spreadId,
            'blocking' => $blocking,
        ];
    }
}
