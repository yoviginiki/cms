@php $menuHtml = app(\App\Domain\Menus\Services\MenuRenderer::class)->renderByLocation($site ?? null, 'footer'); @endphp
@if($menuHtml)
{!! $menuHtml !!}
@else
<footer style="padding:2rem;text-align:center;border-top:1px solid var(--semantic-color-border-subtle,#f1f5f9);color:var(--semantic-color-text-muted,#9ca3af);font-size:0.8rem;">
    <p>© {{ date('Y') }} {{ $site->name ?? '' }}</p>
</footer>
@endif
