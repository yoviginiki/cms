<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — Admin</title>
    <link rel="stylesheet" href="/admin-assets/assets/index.css">
</head>
<body>
    <div id="root"></div>
    <script>
        window.__APP__ = {!! json_encode([
            'user' => auth()->user(),
            'csrfToken' => csrf_token(),
        ]) !!};
    </script>
    <script type="module" src="/admin-assets/assets/index.js"></script>
</body>
</html>
