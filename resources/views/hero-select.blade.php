@extends('layouts.app')

@section('content')
<style>
    .weapon-tooltip { position: relative; }
    .weapon-tooltip-content {
        visibility: hidden;
        opacity: 0;
        position: absolute;
        bottom: calc(100% + 8px);
        left: 50%;
        transform: translateX(-50%);
        background: #111827;
        border: 1px solid #374151;
        border-radius: 6px;
        padding: 10px;
        min-width: 160px;
        z-index: 100;
        transition: opacity 0.15s;
        pointer-events: none;
    }
    .weapon-tooltip:hover .weapon-tooltip-content {
        visibility: visible;
        opacity: 1;
    }
</style>
<div x-data="{ selectedHero: null }">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold mb-2">Choose Your Hero</h1>
        <p class="text-gray-400">Each hero has unique starting stats and card options</p>
    </div>

    <form action="{{ route('match.hero.submit', $match->id) }}" method="POST">
        @csrf

        <!-- Hero Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8 max-w-5xl mx-auto">
            @foreach($heroes as $hero)
                @php
                    $borderColor = match($hero['color'] ?? 'gray') {
                        'purple' => 'border-purple-500',
                        'red' => 'border-red-500',
                        'green' => 'border-green-500',
                        'yellow' => 'border-yellow-500',
                        'cyan' => 'border-cyan-500',
                        'orange' => 'border-orange-500',
                        default => 'border-gray-500'
                    };
                    $bgColor = match($hero['color'] ?? 'gray') {
                        'purple' => 'bg-purple-900/30',
                        'red' => 'bg-red-900/30',
                        'green' => 'bg-green-900/30',
                        'yellow' => 'bg-yellow-900/30',
                        'cyan' => 'bg-cyan-900/30',
                        'orange' => 'bg-orange-900/30',
                        default => 'bg-gray-900/30'
                    };
                    $textColor = match($hero['color'] ?? 'gray') {
                        'purple' => 'text-purple-400',
                        'red' => 'text-red-400',
                        'green' => 'text-green-400',
                        'yellow' => 'text-yellow-400',
                        'cyan' => 'text-cyan-400',
                        'orange' => 'text-orange-400',
                        default => 'text-gray-400'
                    };

                    // Get weapon data
                    $weaponId = $hero['starting_weapon'] ?? 'rusty_sword';
                    $weapon = $weapons[$weaponId] ?? null;
                    $weaponRarityColor = $weapon ? match($weapon['rarity'] ?? 'common') {
                        'legendary' => 'border-yellow-500 bg-yellow-900/30',
                        'epic' => 'border-purple-500 bg-purple-900/30',
                        'rare' => 'border-blue-500 bg-blue-900/30',
                        'uncommon' => 'border-green-500 bg-green-900/30',
                        default => 'border-gray-600 bg-gray-700'
                    } : 'border-gray-600 bg-gray-700';
                @endphp
                <div
                    class="relative bg-gray-800 rounded-lg cursor-pointer transition-all duration-150 border-2 select-none active:scale-[0.98]"
                    :class="selectedHero === '{{ $hero['id'] }}' ? '{{ $borderColor }} ring-2 ring-opacity-50 {{ str_replace('border-', 'ring-', $borderColor) }}' : 'border-transparent hover:border-gray-600'"
                    @click="selectedHero = '{{ $hero['id'] }}'"
                >
                    <!-- Hero Header -->
                    <div class="p-4 {{ $bgColor }} border-b border-gray-700">
                        <div class="text-5xl text-center mb-2">{{ $hero['icon'] }}</div>
                        <h3 class="font-bold text-xl text-center {{ $textColor }}">{{ $hero['name'] }}</h3>
                    </div>

                    <!-- Hero Stats -->
                    <div class="p-4">
                        <p class="text-gray-400 text-sm mb-4 text-center">{{ $hero['description'] }}</p>

                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">HP</span>
                                <span class="text-red-400 font-bold">{{ $hero['stats']['base_hp'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Starting Gold</span>
                                <span class="text-yellow-400 font-bold">{{ $hero['stats']['base_gold'] }}g</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Crit Chance</span>
                                <span class="text-purple-400 font-bold">{{ $hero['stats']['base_crit'] }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Card Slots</span>
                                <span class="text-blue-400 font-bold">{{ $hero['stats']['card_slots'] }}</span>
                            </div>
                        </div>

                        <!-- Starting Weapon -->
                        <div class="mt-4 pt-4 border-t border-gray-700">
                            <div class="text-xs text-gray-500 mb-2">Starting Weapon</div>
                            @if($weapon)
                                <div class="flex items-center gap-3">
                                    <div class="weapon-tooltip">
                                        <div class="w-12 h-12 flex items-center justify-center rounded border-2 {{ $weaponRarityColor }}">
                                            @if(isset($weapon['icon']))
                                                {!! icon($weapon['icon']) !!}
                                            @else
                                                <span class="text-2xl">&#x2694;</span>
                                            @endif
                                        </div>
                                        <div class="weapon-tooltip-content text-xs text-left">
                                            <div class="font-bold text-white mb-2">{{ $weapon['name'] }}</div>
                                            @if(isset($weapon['attack']))
                                                @php
                                                    $minDmg = $weapon['attack'] - ($weapon['attack_variance'] ?? 0);
                                                    $maxDmg = $weapon['attack'] + ($weapon['attack_variance'] ?? 0);
                                                @endphp
                                                <div class="text-orange-400">{{ $minDmg }}-{{ $maxDmg }} DMG</div>
                                            @endif
                                            @if(isset($weapon['crit_bonus']) || isset($weapon['crit_chance']))
                                                <div class="text-purple-400">+{{ $weapon['crit_bonus'] ?? $weapon['crit_chance'] ?? 0 }}% CRIT</div>
                                            @endif
                                            @if(isset($weapon['armor_pierce']))
                                                <div class="text-red-400">{{ $weapon['armor_pierce'] }} Armor Pierce</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-white font-semibold text-sm">{{ $weapon['name'] }}</div>
                                    </div>
                                </div>
                            @else
                                <div class="text-gray-500">No weapon</div>
                            @endif
                        </div>
                    </div>

                    <!-- Selection Indicator -->
                    <div
                        class="absolute top-3 right-3 w-6 h-6 rounded-full bg-white flex items-center justify-center"
                        x-show="selectedHero === '{{ $hero['id'] }}'"
                        x-transition
                    >
                        <svg class="w-4 h-4 {{ $textColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </div>

                <!-- Hidden input for form submission -->
                <template x-if="selectedHero === '{{ $hero['id'] }}'">
                    <input type="hidden" name="hero_id" value="{{ $hero['id'] }}">
                </template>
            @endforeach
        </div>

        <!-- Submit Button -->
        <div class="text-center">
            <button
                type="submit"
                class="bg-blue-600 hover:bg-blue-700 active:scale-95 active:bg-blue-800 disabled:bg-gray-600 disabled:cursor-not-allowed disabled:active:scale-100 text-white font-bold py-3 px-8 rounded-lg transition-all duration-150 shadow-lg hover:shadow-xl"
                :disabled="!selectedHero"
            >
                Choose Hero
            </button>
        </div>
    </form>
</div>
@endsection
