<div class="accordion-block" style="margin-bottom: 1.5rem;">
    @foreach(($data['items'] ?? []) as $item)
        <details style="border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 0.5rem; overflow: hidden;">
            <summary style="padding: 1rem 1.25rem; cursor: pointer; font-weight: 500; background: #f9fafb; list-style: none; display: flex; align-items: center; justify-content: space-between;">
                {{ $item['title'] ?? '' }}
                <span style="transition: transform 0.2s;">&#9660;</span>
            </summary>
            <div style="padding: 1rem 1.25rem; border-top: 1px solid #e5e7eb;">
                {!! $item['content'] ?? '' !!}
            </div>
        </details>
    @endforeach
</div>
