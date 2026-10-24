@props(['product'])
@php
    $colors = [
        'pending' => 'bg-stone-100 text-stone-700',
        'processing' => 'bg-sky-100 text-sky-800',
        'review' => 'bg-amber-100 text-amber-800',
        'failed' => 'bg-red-100 text-red-800',
        'approved' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-stone-200 text-stone-600',
        'exported' => 'bg-violet-100 text-violet-800',
    ];
@endphp
<span {{ $attributes->class(['inline-block rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', $colors[$product->status] ?? 'bg-stone-100']) }}>{{ $product->label() }}</span>
