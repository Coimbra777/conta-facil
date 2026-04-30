<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Conta Certa') }}</title>
    @php
        $spaDir = public_path('spa');
        $manifestPath = $spaDir . '/.vite/manifest.json';
        if (! is_file($manifestPath)) {
            $manifestPath = $spaDir . '/manifest.json';
        }
        $manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
        $entry = is_array($manifest) ? ($manifest['index.html'] ?? null) : null;
    @endphp
    @if ($entry && isset($entry['file']))
        @php
            $assetBase = '/spa';
            $entryJs = $assetBase . '/' . $entry['file'];
            $entryCss = isset($entry['css']) && is_array($entry['css']) ? $entry['css'] : [];
        @endphp
        @foreach ($entryCss as $css)
            <link rel="stylesheet" href="{{ $assetBase }}/{{ $css }}">
        @endforeach
        <script type="module" src="{{ $entryJs }}"></script>
    @endif
</head>
<body class="antialiased">
    @if (! $entry || ! isset($entry['file']))
        <div style="font-family: system-ui, sans-serif; max-width: 42rem; margin: 3rem auto; padding: 1.5rem; line-height: 1.6;">
            <h1 style="font-size: 1.25rem; margin-bottom: 0.75rem;">Build da SPA não encontrado</h1>
            <p>O Laravel está configurado para servir o React a partir de <code>public/spa</code>, mas o manifest do Vite ainda não existe.</p>
            <p>Gere o build do frontend:</p>
            <pre style="background: #f4f4f5; padding: 1rem; border-radius: 0.5rem; overflow: auto;">cd frontend
npm install
npm run build</pre>
            <p>Em desenvolvimento, use o Vite em <code>http://localhost:5173</code> e a API em <code>{{ url('/api/v1') }}</code>.</p>
        </div>
    @else
        <div id="root"></div>
    @endif
</body>
</html>
