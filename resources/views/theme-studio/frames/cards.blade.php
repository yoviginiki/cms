<div style="padding:2rem;background:var(--semantic-color-background-canvas,#fff);">
    <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:1.5rem;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.heading,semantic.font.family.display" data-theme-element="heading.h2" data-theme-element-id="cards-h2" @endif>
        Card Grid
    </h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;">
        @foreach(['Default Card','Elevated Card','Bordered Card','Compact Card'] as $i => $title)
        <div style="background:var(--semantic-color-background-raised,#fff);border:1px solid var(--semantic-color-border-default,#e5e7eb);border-radius:var(--semantic-size-radius-lg,0.5rem);overflow:hidden;{{ $i === 1 ? 'box-shadow:var(--semantic-shadow-lg,0 10px 15px rgba(0,0,0,0.1));' : '' }}{{ $i === 2 ? 'border-width:2px;border-color:var(--semantic-color-border-strong,#333);' : '' }}"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.background.raised,semantic.color.border.default,semantic.size.radius.lg,semantic.shadow.lg" data-theme-element="card.{{ ['default','elevated','bordered','compact'][$i] }}" data-theme-element-id="card-{{ $i }}" @endif>
            <div style="height:160px;background:var(--semantic-color-background-surface,#f5f5f5);"></div>
            <div style="padding:{{ $i === 3 ? '0.75rem' : '1.25rem' }};">
                <h3 style="font-weight:600;margin-bottom:0.5rem;font-size:{{ $i === 3 ? '0.95rem' : '1.125rem' }};"
                    @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.heading" data-theme-element="card.title" data-theme-element-id="card-title-{{ $i }}" @endif>
                    {{ $title }}
                </h3>
                <p style="color:var(--semantic-color-text-muted,#666);font-size:0.875rem;margin-bottom:1rem;"
                    @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.muted" data-theme-element="card.description" data-theme-element-id="card-desc-{{ $i }}" @endif>
                    Sample card content showing how text, borders, and shadows work together.
                </p>
                <a href="#" style="color:var(--semantic-color-text-link,#3b82f6);font-size:0.875rem;font-weight:600;text-decoration:none;"
                    @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.link" data-theme-element="link" data-theme-element-id="card-link-{{ $i }}" @endif>
                    Read more →
                </a>
            </div>
        </div>
        @endforeach
    </div>
</div>
