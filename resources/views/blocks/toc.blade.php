@php
    $maxDepth = $data['maxDepth'] ?? 3;
    $style = $data['style'] ?? 'inline';
    $sticky = !empty($data['sticky']);
    $stickyStyle = $sticky ? ' style="position: sticky; top: 80px;"' : '';
@endphp
<nav class="toc toc--{{ e($style) }}"{!! $stickyStyle !!}>
    <p>Table of contents auto-generated from headings</p>
</nav>
