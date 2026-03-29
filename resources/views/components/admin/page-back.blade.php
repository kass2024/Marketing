@props([
    'href',
    'label' => 'Back',
])

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-900/5 transition hover:border-xander-navy/25 hover:bg-xander-navy/5 hover:text-xander-navy',
    ]) }}
>
    <svg class="h-4 w-4 text-xander-navy" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
    </svg>
    {{ $label }}
</a>
