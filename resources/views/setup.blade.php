@extends('layouts.app')

@section('content')
@php
    $isGambler = ($hero['id'] ?? '') === 'gambler';
    $heroTextColor = match($hero['color'] ?? 'gray') {
        'purple' => 'text-purple-400',
        'red' => 'text-red-400',
        'green' => 'text-green-400',
        'yellow' => 'text-yellow-400',
        'cyan' => 'text-cyan-400',
        'orange' => 'text-orange-400',
        'pink' => 'text-pink-400',
        default => 'text-gray-400'
    };
    $cardIcons = [
        'deception' => '🎭',
        'difficulty' => '💀',
        'utility' => '✨',
    ];
@endphp

@if($isGambler)
    {{-- Gambler gets random cards - no selection --}}
    <div class="max-w-lg mx-auto text-center py-12">
        <div class="bg-gray-800 rounded-xl p-8 border-2 border-pink-500/50">
            <div class="text-6xl mb-4">🎲</div>
            <h1 class="text-3xl font-bold text-pink-400 mb-2">{{ $hero['name'] }}</h1>
            <p class="text-gray-400 mb-6">{{ $hero['description'] }}</p>

            <div class="bg-gray-900 rounded-lg p-4 mb-6">
                <p class="text-yellow-400 font-bold mb-2">Fate decides your cards!</p>
                <p class="text-gray-500 text-sm">As a Gambler, {{ $cardsToSelect }} random cards will be chosen for you. Embrace the chaos!</p>
            </div>

            <form action="{{ route('match.setup.submit', $match->id) }}" method="POST">
                @csrf
                <input type="hidden" name="gambler_random" value="1">
                <button type="submit"
                        class="bg-pink-600 hover:bg-pink-700 active:scale-95 text-white font-bold py-4 px-12 rounded-lg transition-all text-xl shadow-lg hover:shadow-pink-500/25">
                    🎲 Roll the Dice! 🎲
                </button>
            </form>
        </div>
    </div>
@else
    {{-- Normal card selection --}}
    <div x-data="{
        selectedCards: [],
        maxCards: {{ $cardsToSelect }},
        toggleCard(cardId) {
            const index = this.selectedCards.indexOf(cardId);
            if (index > -1) {
                this.selectedCards.splice(index, 1);
            } else if (this.selectedCards.length < this.maxCards) {
                this.selectedCards.push(cardId);
            }
        },
        isSelected(cardId) {
            return this.selectedCards.includes(cardId);
        }
    }">
        <div class="text-center mb-6">
            @if(isset($hero))
                <div class="inline-flex items-center gap-3 bg-gray-800 rounded-lg px-4 py-2 mb-4 border border-gray-700">
                    <span class="text-2xl">{{ $hero['icon'] }}</span>
                    <span class="font-bold {{ $heroTextColor }}">{{ $hero['name'] }}</span>
                    <span class="text-gray-500 text-sm">|</span>
                    <span class="text-gray-400 text-sm">{{ $cardsToSelect }} card slots</span>
                </div>
            @endif
            <h1 class="text-3xl font-bold mb-2">Select Your Cards</h1>
            <p class="text-gray-400">Choose cards to affect your opponent's dungeon or boost yourself</p>
            <div class="mt-3 inline-flex items-center gap-2 bg-gray-800 rounded-full px-4 py-2">
                <span class="text-gray-400">Selected:</span>
                <span class="text-xl font-bold" :class="selectedCards.length === {{ $cardsToSelect }} ? 'text-green-400' : 'text-blue-400'">
                    <span x-text="selectedCards.length"></span>/{{ $cardsToSelect }}
                </span>
            </div>
        </div>

        {{-- Card Type Legend --}}
        <div class="flex justify-center gap-4 mb-6">
            <div class="flex items-center gap-1 text-sm">
                <span class="w-3 h-3 rounded bg-purple-600"></span>
                <span class="text-gray-400">🎭 Deception</span>
            </div>
            <div class="flex items-center gap-1 text-sm">
                <span class="w-3 h-3 rounded bg-red-600"></span>
                <span class="text-gray-400">💀 Difficulty</span>
            </div>
            <div class="flex items-center gap-1 text-sm">
                <span class="w-3 h-3 rounded bg-green-600"></span>
                <span class="text-gray-400">✨ Utility</span>
            </div>
        </div>

        <form action="{{ route('match.setup.submit', $match->id) }}" method="POST">
            @csrf

            {{-- Cards grouped by type --}}
            @php
                $groupedCards = collect($cards)->groupBy('type');
                $typeOrder = ['deception', 'difficulty', 'utility'];
            @endphp

            @foreach($typeOrder as $type)
                @if(isset($groupedCards[$type]) && count($groupedCards[$type]) > 0)
                    @php
                        $typeColor = match($type) {
                            'deception' => 'border-purple-600 bg-purple-900/20',
                            'difficulty' => 'border-red-600 bg-red-900/20',
                            'utility' => 'border-green-600 bg-green-900/20',
                            default => 'border-gray-600 bg-gray-800'
                        };
                        $typeBg = match($type) {
                            'deception' => 'bg-purple-600',
                            'difficulty' => 'bg-red-600',
                            'utility' => 'bg-green-600',
                            default => 'bg-gray-600'
                        };
                    @endphp
                    <div class="mb-6">
                        <h2 class="text-lg font-bold mb-3 flex items-center gap-2">
                            <span class="w-2 h-6 rounded {{ $typeBg }}"></span>
                            <span>{{ $cardIcons[$type] ?? '' }} {{ ucfirst($type) }} Cards</span>
                            <span class="text-gray-500 text-sm font-normal">
                                @if($type === 'deception') - Mislead your opponent
                                @elseif($type === 'difficulty') - Make their dungeon harder
                                @else - Boost yourself
                                @endif
                            </span>
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                            @foreach($groupedCards[$type] as $card)
                                <div
                                    class="relative rounded-lg cursor-pointer transition-all duration-150 border-2 select-none active:scale-[0.98] p-3"
                                    :class="isSelected('{{ $card['id'] }}') ? '{{ $typeColor }} ring-2 ring-white/30 shadow-lg' : 'bg-gray-800 border-gray-700 hover:border-gray-500'"
                                    @click="toggleCard('{{ $card['id'] }}')"
                                >
                                    {{-- Checkmark indicator --}}
                                    <div
                                        class="absolute top-2 right-2 w-5 h-5 rounded-full {{ $typeBg }} flex items-center justify-center shadow"
                                        x-show="isSelected('{{ $card['id'] }}')"
                                        x-transition
                                    >
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>

                                    <div class="flex items-start gap-2 mb-1 pr-6">
                                        <span class="text-lg">{{ $cardIcons[$card['type']] ?? '🃏' }}</span>
                                        <h3 class="font-bold">{{ $card['name'] }}</h3>
                                    </div>
                                    <p class="text-gray-400 text-sm leading-snug">{{ $card['description'] }}</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <span class="text-xs px-1.5 py-0.5 rounded {{ $card['target'] === 'opponent' ? 'bg-red-900/50 text-red-300' : 'bg-blue-900/50 text-blue-300' }}">
                                            {{ $card['target'] === 'opponent' ? '⚔️ vs Opponent' : '🛡️ For You' }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Hidden input for form submission --}}
                                <template x-if="isSelected('{{ $card['id'] }}')">
                                    <input type="hidden" name="cards[]" value="{{ $card['id'] }}">
                                </template>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Submit Button --}}
            <div class="text-center py-6">
                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 active:scale-95 active:bg-blue-800 disabled:bg-gray-600 disabled:cursor-not-allowed disabled:active:scale-100 text-white font-bold py-3 px-10 rounded-lg transition-all duration-150 shadow-lg hover:shadow-xl text-lg"
                    :disabled="selectedCards.length !== {{ $cardsToSelect }}"
                >
                    <span x-show="selectedCards.length !== {{ $cardsToSelect }}">Select {{ $cardsToSelect }} Cards</span>
                    <span x-show="selectedCards.length === {{ $cardsToSelect }}">✓ Confirm Selection</span>
                </button>
            </div>
        </form>
    </div>
@endif
@endsection
