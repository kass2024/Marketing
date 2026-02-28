@php
    $colors = [
        'blue' => 'bg-blue-50 text-blue-600',
        'green' => 'bg-green-50 text-green-600',
        'red' => 'bg-red-50 text-red-600',
        'yellow' => 'bg-yellow-50 text-yellow-600',
        'purple' => 'bg-purple-50 text-purple-600',
    ];

    $colorClass = $colors[$color] ?? $colors['blue'];
@endphp

<div class="bg-white shadow-sm rounded-xl p-6 border border-gray-100">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500 font-medium">
                {{ $title }}
            </p>

            <h3 class="text-2xl font-bold text-gray-800 mt-2">
                {{ $value }}
            </h3>
        </div>

        @if($icon)
            <div class="w-12 h-12 flex items-center justify-center rounded-lg {{ $colorClass }}">
                <i class="{{ $icon }} text-xl"></i>
            </div>
        @endif
    </div>
</div>