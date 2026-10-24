<x-layout title="Feedy">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-stone-200 bg-white p-6 lg:col-span-1">
            <h1 class="text-lg font-semibold">Nový feed</h1>
            <p class="mt-1 text-sm text-stone-500">XML (formát Heureka) alebo CSV s hlavičkou <code>sku,name,description,brand,ean,price,category</code>. Ostatné stĺpce sa načítajú ako parametre.</p>

            <form method="post" action="{{ route('feeds.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <input type="file" name="feed" accept=".xml,.csv,.txt" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-stone-100 file:px-3 file:py-2 file:text-sm hover:file:bg-stone-200">
                <button class="w-full rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Nahrať feed</button>
            </form>

            <form method="post" action="{{ route('feeds.store') }}" class="mt-3">
                @csrf
                <input type="hidden" name="sample" value="1">
                <button class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm hover:bg-stone-50">Načítať ukážkový feed (14 produktov)</button>
            </form>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white lg:col-span-2">
            <h2 class="border-b border-stone-200 px-6 py-4 text-lg font-semibold">Feedy</h2>

            @forelse ($feeds as $feed)
                <a href="{{ route('feeds.show', $feed) }}" class="flex items-center justify-between border-b border-stone-100 px-6 py-4 last:border-0 hover:bg-stone-50">
                    <div>
                        <div class="font-medium">{{ $feed->name }}</div>
                        <div class="text-sm text-stone-500">{{ $feed->source_filename }} · {{ $feed->created_at->format('j. n. Y H:i') }}</div>
                    </div>
                    <span class="text-sm text-stone-600">{{ $feed->products_count }} produktov</span>
                </a>
            @empty
                <p class="px-6 py-10 text-center text-sm text-stone-500">Zatiaľ žiadny feed. Nahraj vlastný alebo skús ukážkový.</p>
            @endforelse
        </section>
    </div>
</x-layout>
