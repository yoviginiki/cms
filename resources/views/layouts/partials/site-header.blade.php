@php $menuHtml = app(\App\Domain\Menus\Services\MenuRenderer::class)->renderByLocation($site ?? null, 'header'); @endphp
@if($menuHtml)
{!! $menuHtml !!}
@else
<header style="display:flex;align-items:center;justify-content:space-between;padding:1rem 2rem;border-bottom:1px solid var(--semantic-color-border-subtle,#f1f5f9);">
    <a href="/" style="font-weight:700;font-size:1.125rem;text-decoration:none;color:var(--semantic-color-text-heading,#111);font-family:var(--semantic-font-family-display,inherit);">{{ $site->name ?? 'Site' }}</a>
</header>
@endif
