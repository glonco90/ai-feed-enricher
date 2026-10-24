<x-layout :title="$feed->name" :refresh="$running">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('feeds.index') }}" class="text-sm text-stone-500 hover:underline">← Feedy</a>
            <h1 class="mt-1 text-2xl font-semibold">{{ $feed->name }}</h1>
            <p class="text-sm text-stone-500">{{ $feed->source_filename }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if (! $running && $stats['pending'] + $stats['failed'] > 0)
                <form method="post" action="{{ route('feeds.process', $feed) }}">
                    @csrf
                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Spracovať cez AI ({{ $stats['pending'] + $stats['failed'] }})</button>
                </form>
            @endif
            @if ($stats['review'] > 0)
                <form method="post" action="{{ route('feeds.approve-all', $feed) }}" onsubmit="return confirm('Schváliť všetkých {{ $stats['review'] }} produktov bez kontroly?')">
                    @csrf
                    <button class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm hover:bg-stone-50">Schváliť všetky ({{ $stats['review'] }})</button>
                </form>
            @endif
            @if ($stats['approved'] + $stats['exported'] > 0)
                <a href="{{ route('feeds.export.csv', $feed) }}" class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm hover:bg-stone-50">Stiahnuť CSV pre WooCommerce</a>
            @endif
            @if ($stats['approved'] > 0)
                <form method="post" action="{{ route('feeds.export.woo', $feed) }}">
                    @csrf
                    <button class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-medium text-white hover:bg-violet-800">Odoslať do WooCommerce ({{ $stats['approved'] }})</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            ['Produkty', $stats['total'], null],
            ['Na kontrolu', $stats['review'], 'review'],
            ['Schválené', $stats['approved'] + $stats['exported'], 'approved'],
            ['Chyby', $stats['failed'], 'failed'],
            ['Zachytené validátorom', $stats['caught'], null],
            [$stats['cost'] !== null ? 'Cena AI' : 'Tokeny', $stats['cost'] !== null ? '$'.number_format($stats['cost'], 4) : number_format($stats['input_tokens'] + $stats['output_tokens'], 0, ',', ' '), null],
        ] as [$label, $value, $filter])
            <a @if ($filter) href="{{ route('feeds.show', [$feed, 'status' => $filter]) }}" @endif class="rounded-xl border border-stone-200 bg-white p-4 {{ $filter ? 'hover:border-stone-400' : '' }}">
                <div class="text-2xl font-semibold tabular-nums">{{ $value }}</div>
                <div class="mt-1 text-xs text-stone-500">{{ $label }}</div>
            </a>
        @endforeach
    </div>

    @if ($running)
        <p class="mt-4 text-sm text-sky-800">Spracúva sa… zostáva {{ $stats['pending'] }} produktov. Stránka sa obnovuje každé 4 sekundy.</p>
    @endif

    <div class="mt-6 overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-stone-200 px-4 py-3 text-sm">
            <span class="text-stone-500">Filter:</span>
            <a href="{{ route('feeds.show', $feed) }}" class="{{ $status ? 'text-stone-600 hover:underline' : 'font-semibold' }}">všetky</a>
            @foreach (\App\Models\Product::LABELS as $key => $label)
                <a href="{{ route('feeds.show', [$feed, 'status' => $key]) }}" class="{{ $status === $key ? 'font-semibold' : 'text-stone-600 hover:underline' }}">{{ mb_strtolower($label) }}</a>
            @endforeach
        </div>
        <table class="w-full text-left text-sm">
            <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">SKU</th>
                    <th class="px-4 py-3">Od dodávateľa</th>
                    <th class="px-4 py-3">Návrh AI</th>
                    <th class="px-4 py-3">Kategória</th>
                    <th class="px-4 py-3">Stav</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($products as $product)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3 font-mono text-xs whitespace-nowrap text-stone-500">{{ $product->sku }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $product->raw['name'] }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('products.show', $product) }}" class="font-medium hover:underline">{{ $product->ai['name'] ?? 'otvoriť' }}</a>
                            @if ($product->rejected_by_validator > 0 && $product->ai)
                                <span class="ml-1 text-xs text-amber-700" title="Validátor odmietol prvý návrh AI, model ho opravil">● opravené</span>
                            @endif
                            @if (! empty($product->ai['warnings']))
                                <span class="ml-1 text-xs text-sky-700">● upozornenia: {{ count($product->ai['warnings']) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-stone-600">{{ $product->categoryLabel() ?? '—' }}</td>
                        <td class="px-4 py-3"><x-status-badge :product="$product" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Žiadne produkty v tomto filtri.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>

    <form method="post" action="{{ route('feeds.destroy', $feed) }}" onsubmit="return confirm('Zmazať feed aj všetky produkty?')" class="mt-8">
        @csrf
        @method('delete')
        <button class="text-sm text-red-700 hover:underline">Zmazať feed</button>
    </form>
</x-layout>
