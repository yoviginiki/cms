<blockquote class="quote-block">
    {!! $data['content'] ?? '' !!}
    @if(!empty($data['citation']))
        <cite>{{ $data['citation'] }}</cite>
    @endif
</blockquote>
