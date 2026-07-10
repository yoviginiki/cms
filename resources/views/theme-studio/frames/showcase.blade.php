{{-- Showcase dispatcher: a theme's layout personality selects a structurally
     DIFFERENT sample page (not just a recolor). Falls back to 'standard'. --}}
@php
    $l = preg_replace('/[^a-z]/', '', (string)($layout ?? 'standard')) ?: 'standard';
    $view = "theme-studio.frames.layouts.{$l}";
@endphp
@includeFirst([$view, 'theme-studio.frames.layouts.standard'])
