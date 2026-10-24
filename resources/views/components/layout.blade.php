@props(['title' => null, 'refresh' => false])
<!doctype html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($refresh)
        <meta http-equiv="refresh" content="4">
    @endif
    <title>{{ $title ? $title.' — ' : '' }}AI Feed Enricher</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-4">
            <a href="{{ route('feeds.index') }}" class="flex items-center gap-2 font-semibold">
                <span class="grid size-8 place-items-center rounded-lg bg-emerald-700 text-sm text-white">AI</span>
                AI Feed Enricher
            </a>
            <span class="text-sm text-stone-500">feed dodávateľa → Claude → kontrola → WooCommerce</span>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
