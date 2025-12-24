@php
    $typeColors = [
        'enemy' => 'bg-red-800 border-red-600',
        'loot' => 'bg-yellow-800 border-yellow-600',
        'elite' => 'bg-purple-800 border-purple-600',
        'shop' => 'bg-green-800 border-green-600',
        'boss' => 'bg-red-900 border-red-500',
        'unknown' => 'bg-gray-700 border-gray-500',
        'empty' => 'bg-gray-800 border-gray-600',
    ];

    $typeIcons = [
        'enemy' => '&#x2694;', // Crossed swords
        'loot' => '&#x1F4B0;', // Money bag
        'elite' => '&#x1F480;', // Skull
        'shop' => '&#x1F6D2;', // Shopping cart
        'boss' => '&#x1F409;', // Dragon
        'unknown' => '&#x2753;', // Question mark
        'empty' => '&#x274C;', // X
    ];

    $displayedType = $room->displayed_type;
    $colorClass = $typeColors[$displayedType] ?? 'bg-gray-700 border-gray-500';
    $icon = $typeIcons[$displayedType] ?? '?';
@endphp

@if($isClickable)
    <form action="{{ route('match.room.enter', [$match->id, $room->id]) }}" method="POST">
        @csrf
        <button
            type="submit"
            class="w-full p-4 rounded-lg border-2 {{ $colorClass }} hover:brightness-110 active:scale-95 transition-all duration-150 cursor-pointer text-center"
        >
            <div class="text-2xl mb-1">{!! $icon !!}</div>
            <div class="font-bold text-sm">{{ ucfirst($displayedType) }}</div>
            @if($room->visited)
                <div class="text-xs text-gray-400 mt-1">Visited</div>
            @endif
        </button>
    </form>
@else
    <div class="w-full p-4 rounded-lg border-2 {{ $colorClass }} {{ $isFuture ? 'opacity-50' : '' }} {{ $isPast && $room->completed ? 'opacity-30' : '' }} text-center">
        <div class="text-2xl mb-1">{!! $icon !!}</div>
        <div class="font-bold text-sm">{{ ucfirst($displayedType) }}</div>
        @if($room->completed)
            <div class="text-xs text-green-400 mt-1">Completed</div>
        @elseif($room->visited)
            <div class="text-xs text-blue-400 mt-1">Visited</div>
        @elseif($isFuture)
            <div class="text-xs text-gray-500 mt-1">Locked</div>
        @endif
    </div>
@endif
