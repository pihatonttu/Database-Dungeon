@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto px-2">
    <div class="text-center mb-4 sm:mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold mb-1">Battle Results</h1>
        @if($winner)
            <p class="text-lg sm:text-xl">
                @if($winner->id === $player->id)
                    <span class="text-green-400">You Won!</span>
                @else
                    <span class="text-red-400">You Lost</span>
                @endif
            </p>
        @else
            <p class="text-gray-400">Calculating results...</p>
        @endif
    </div>

    @if($winner)
        <!-- Winner Display -->
        <div class="bg-yellow-800 p-4 sm:p-6 rounded-lg text-center mb-4 sm:mb-6">
            <div class="text-4xl sm:text-5xl mb-2">&#x1F3C6;</div>
            <h2 class="text-xl sm:text-2xl font-bold">{{ $winner->name }}</h2>
            <p class="text-yellow-300 text-sm sm:text-base">Champion of the Dungeon!</p>
        </div>
    @endif

    @if($pvpResult)
        <!-- Score Breakdown -->
        <div class="bg-gray-800 p-4 sm:p-6 rounded-lg mb-4 sm:mb-6">
            <h2 class="text-lg sm:text-xl font-bold mb-3 text-center">Score Breakdown</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                @foreach($match->players as $p)
                    @php
                        $scores = $pvpResult['scores'][$p->id] ?? [];
                        $breakdown = $scores['breakdown'] ?? [];
                        $isWinner = $p->id === ($winner->id ?? null);
                    @endphp
                    <div class="{{ $isWinner ? 'bg-green-900/30' : 'bg-gray-700/30' }} p-3 sm:p-4 rounded">
                        <h3 class="font-bold text-base sm:text-lg mb-2 {{ $isWinner ? 'text-green-400' : '' }}">
                            {{ $p->name }}
                            @if($p->id === $player->id) (You) @endif
                            @if($isWinner) &#x1F451; @endif
                        </h3>

                        <div class="space-y-1.5 text-xs sm:text-sm">
                            @if(isset($breakdown['hp']))
                                <div class="flex justify-between">
                                    <span class="text-gray-400">HP ({{ $breakdown['hp']['value'] }})</span>
                                    <span class="text-green-400">+{{ $breakdown['hp']['contribution'] }}</span>
                                </div>
                            @endif
                            @if(isset($breakdown['xp']))
                                <div class="flex justify-between">
                                    <span class="text-gray-400">XP ({{ $breakdown['xp']['value'] }})</span>
                                    <span class="text-blue-400">+{{ $breakdown['xp']['contribution'] }}</span>
                                </div>
                            @endif
                            @if(isset($breakdown['loot']))
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Loot Power ({{ $breakdown['loot']['value'] }})</span>
                                    <span class="text-purple-400">+{{ $breakdown['loot']['contribution'] }}</span>
                                </div>
                            @endif
                            <div class="border-t border-gray-600 pt-2 mt-2">
                                <div class="flex justify-between font-bold">
                                    <span>Base Score</span>
                                    <span>{{ $scores['base_score'] ?? 0 }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">+ Random</span>
                                    <span class="text-gray-400">{{ ($scores['final_score'] ?? 0) - ($scores['base_score'] ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between font-bold text-base sm:text-lg mt-1">
                                    <span>Final Score</span>
                                    <span class="{{ $isWinner ? 'text-green-400' : '' }}">{{ $scores['final_score'] ?? 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(isset($pvpResult['margin']))
                <p class="text-center mt-3 text-gray-400 text-sm">
                    Victory margin: {{ $pvpResult['margin'] }} points
                </p>
            @endif
        </div>

        <!-- Player's Final Stats -->
        <div class="bg-gray-800 p-4 sm:p-6 rounded-lg mb-4 sm:mb-6">
            <h2 class="text-lg sm:text-xl font-bold mb-3">Your Final Stats</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                <div>
                    <p class="text-xl sm:text-2xl font-bold text-green-400">{{ $player->current_hp }}</p>
                    <p class="text-xs sm:text-sm text-gray-400">HP Remaining</p>
                </div>
                <div>
                    <p class="text-xl sm:text-2xl font-bold text-yellow-400">{{ $player->gold }}</p>
                    <p class="text-xs sm:text-sm text-gray-400">Gold</p>
                </div>
                <div>
                    <p class="text-xl sm:text-2xl font-bold text-blue-400">{{ $player->xp }}</p>
                    <p class="text-xs sm:text-sm text-gray-400">XP Earned</p>
                </div>
                <div>
                    <p class="text-xl sm:text-2xl font-bold text-purple-400">{{ count($player->getLoot()) }}</p>
                    <p class="text-xs sm:text-sm text-gray-400">Items Collected</p>
                </div>
            </div>

            @if(count($player->getLoot()) > 0)
                <div class="mt-3">
                    <h3 class="font-semibold mb-2 text-sm">Items:</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($player->getLoot() as $item)
                            <span class="bg-gray-700 px-2 py-0.5 rounded text-xs">
                                {{ $item['name'] ?? 'Unknown' }}
                                @if(isset($item['power']))
                                    (+{{ $item['power'] }})
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Play Again -->
    <div class="text-center pb-4">
        <a
            href="{{ route('home') }}"
            class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded transition"
        >
            Play Again
        </a>
    </div>
</div>
@endsection
