@php
    $ai = $product->ai;
    $raw = $product->raw;
    $field = 'mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';
@endphp
<x-layout :title="$product->sku" :refresh="in_array($product->status, ['pending', 'processing']) && $product->feed->isProcessing()">
    <a href="{{ route('feeds.show', $product->feed) }}" class="text-sm text-stone-500 hover:underline">← {{ $product->feed->name }}</a>
    <div class="mt-1 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ $ai['name'] ?? $raw['name'] }}</h1>
        <x-status-badge :product="$product" />
    </div>
    <p class="mt-1 font-mono text-xs text-stone-500">{{ $product->sku }} · pokusy AI: {{ $product->attempts }} · tokeny: {{ $product->input_tokens }} / {{ $product->output_tokens }}</p>

    @if ($product->errors)
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            <p class="font-medium">AI výstup neprešiel kontrolou:</p>
            <ul class="mt-1 list-disc pl-4">
                @foreach ($product->errors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @elseif ($product->rejected_by_validator > 0)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Validátor odmietol prvý návrh AI ({{ $product->rejected_by_validator }}×), model ho po spätnej väzbe opravil.
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <section class="rounded-xl border border-stone-200 bg-white p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Od dodávateľa</h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div><dt class="text-stone-500">Názov</dt><dd>{{ $raw['name'] }}</dd></div>
                <div><dt class="text-stone-500">Popis</dt><dd>{{ $raw['description'] ?: '—' }}</dd></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><dt class="text-stone-500">Značka</dt><dd>{{ $raw['brand'] ?: '—' }}</dd></div>
                    <div><dt class="text-stone-500">Cena s DPH</dt><dd>{{ $raw['price'] ?: '—' }} €</dd></div>
                    <div><dt class="text-stone-500">EAN</dt><dd class="font-mono text-xs">{{ $raw['ean'] ?: '—' }}</dd></div>
                    <div><dt class="text-stone-500">Kategória</dt><dd>{{ $raw['category_hint'] ?: '—' }}</dd></div>
                </div>
                @if (! empty($raw['params']))
                    <div>
                        <dt class="text-stone-500">Parametre</dt>
                        <dd>
                            <table class="mt-1 w-full">
                                @foreach ($raw['params'] as $param)
                                    <tr class="border-t border-stone-100"><td class="py-1 pr-3 text-stone-500">{{ $param['name'] }}</td><td class="py-1">{{ $param['value'] }}</td></tr>
                                @endforeach
                            </table>
                        </dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5 lg:col-span-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Návrh AI — dá sa upraviť</h2>

            @if (! $ai)
                <p class="mt-4 text-sm text-stone-500">Produkt zatiaľ nemá návrh od AI.</p>
                <form method="post" action="{{ route('products.retry', $product) }}" class="mt-4">
                    @csrf
                    <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Spracovať cez AI</button>
                </form>
            @else
                @if (! empty($ai['warnings']))
                    <div class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                        <p class="font-medium">AI upozorňuje, skontroluj:</p>
                        <ul class="mt-1 list-disc pl-4">
                            @foreach ($ai['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('products.update', $product) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('put')

                    <label class="block text-sm">Názov <span class="text-stone-400">(max 80)</span>
                        <input name="name" value="{{ old('name', $ai['name']) }}" maxlength="80" class="{{ $field }}">
                    </label>
                    <label class="block text-sm">Krátky popis <span class="text-stone-400">(max 300)</span>
                        <textarea name="short_description" rows="2" maxlength="300" class="{{ $field }}">{{ old('short_description', $ai['short_description']) }}</textarea>
                    </label>
                    <label class="block text-sm">Popis
                        <textarea name="description" rows="6" maxlength="2000" class="{{ $field }}">{{ old('description', $ai['description']) }}</textarea>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm">Kategória
                            <select name="category" class="{{ $field }}">
                                @foreach (config('enricher.categories') as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category', $ai['category']) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">Pre koho
                            <select name="gender" class="{{ $field }}">
                                @foreach (['panske' => 'Pánske', 'damske' => 'Dámske', 'detske' => 'Detské', 'unisex' => 'Unisex'] as $key => $label)
                                    <option value="{{ $key }}" @selected(old('gender', $ai['gender']) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <fieldset class="text-sm">
                        <legend>Parametre</legend>
                        <div class="mt-1 space-y-2">
                            @foreach ([...($ai['attributes'] ?? []), ['name' => '', 'value' => '']] as $i => $attribute)
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="attributes[{{ $i }}][name]" value="{{ $attribute['name'] }}" placeholder="Názov" class="{{ $field }}">
                                    <input name="attributes[{{ $i }}][value]" value="{{ $attribute['value'] }}" placeholder="Hodnota" class="{{ $field }}">
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <label class="block text-sm">SEO titulok <span class="text-stone-400">(max 60)</span>
                        <input name="seo_title" value="{{ old('seo_title', $ai['seo_title']) }}" maxlength="60" class="{{ $field }}">
                    </label>
                    <label class="block text-sm">SEO popis <span class="text-stone-400">(max 160)</span>
                        <textarea name="seo_description" rows="2" maxlength="160" class="{{ $field }}">{{ old('seo_description', $ai['seo_description']) }}</textarea>
                    </label>

                    <div class="flex flex-wrap gap-2 border-t border-stone-100 pt-4">
                        <button name="approve" value="1" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Uložiť a schváliť</button>
                        <button class="rounded-lg border border-stone-300 px-4 py-2 text-sm hover:bg-stone-50">Len uložiť</button>
                        <button form="reject" class="rounded-lg px-4 py-2 text-sm text-red-700 hover:bg-red-50">Zamietnuť</button>
                        <button form="retry" class="ml-auto rounded-lg px-4 py-2 text-sm text-stone-600 hover:bg-stone-100">Vygenerovať znova</button>
                    </div>
                </form>
                <form id="reject" method="post" action="{{ route('products.reject', $product) }}">@csrf</form>
                <form id="retry" method="post" action="{{ route('products.retry', $product) }}">@csrf</form>
            @endif
        </section>
    </div>
</x-layout>
