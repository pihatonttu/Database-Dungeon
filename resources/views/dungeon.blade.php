@extends('layouts.game')

@section('game-content')
@php
    $totalLevels = app(\App\Game\Content\ContentRepository::class)->getRule('dungeon.total_levels') ?? 10;
    $pendingLoot = session('pending_bonus_loot');
    $pendingLootMatchId = session('pending_loot_match_id');
    // Get opponent's progress
    $opponent = $match->players()->where('id', '!=', $player->id)->first();
    $opponentLevel = $opponent ? $opponent->current_level : 0;
    $opponentComplete = $opponent && $opponent->hasCompletedDungeon();

    // Scout ability check
    $createdDungeon = $player->createdDungeon;
    $hasScout = $createdDungeon && $createdDungeon->hasModifier('scout');
    $scoutKey = "scout_uses_{$player->id}";
    $scoutUses = session($scoutKey);
    if ($scoutUses === null && $hasScout) {
        $card = app(\App\Game\Content\ContentRepository::class)->getCard('scout');
        $scoutUses = $card['effect']['count'] ?? 1;
        session([$scoutKey => $scoutUses]);
    }
@endphp

@if($pendingLoot && $pendingLootMatchId === $match->id)
    {{-- Bonus Loot Decision Screen --}}
    <div class="h-full flex flex-col items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full">
            <h2 class="text-xl font-bold text-yellow-400 text-center mb-4">Löysit esineen!</h2>

            @php
                $lootRarityColor = match($pendingLoot['rarity'] ?? 'common') {
                    'legendary' => 'border-yellow-500 bg-yellow-900/30',
                    'epic' => 'border-purple-500 bg-purple-900/30',
                    'rare' => 'border-blue-500 bg-blue-900/30',
                    'uncommon' => 'border-green-500 bg-green-900/30',
                    default => 'border-gray-600 bg-gray-700'
                };
            @endphp

            <div class="flex items-center gap-4 mb-4 bg-gray-700 p-3 rounded-lg border-2 {{ $lootRarityColor }}">
                <div class="w-16 h-16 flex items-center justify-center">
                    @if(isset($pendingLoot['icon']))
                        {!! icon($pendingLoot['icon'], 'scale-150') !!}
                    @else
                        <span class="text-4xl">&#x1F4E6;</span>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="font-bold text-white">{{ $pendingLoot['name'] }}</div>
                    <div class="text-sm text-gray-400">{{ ucfirst($pendingLoot['type'] ?? 'item') }} - {{ ucfirst($pendingLoot['rarity'] ?? 'common') }}</div>
                    <div class="text-xs mt-1">
                        @if(isset($pendingLoot['attack']))
                            @php $min = $pendingLoot['attack'] - ($pendingLoot['attack_variance'] ?? 0); $max = $pendingLoot['attack'] + ($pendingLoot['attack_variance'] ?? 0); @endphp
                            <span class="text-orange-400">{{ $min }}-{{ $max }} DMG</span>
                        @endif
                        @if(isset($pendingLoot['defense']))
                            <span class="text-blue-400">+{{ $pendingLoot['defense'] }} DEF</span>
                        @endif
                        @if(isset($pendingLoot['max_hp_bonus']))
                            <span class="text-green-400">+{{ $pendingLoot['max_hp_bonus'] }} HP</span>
                        @endif
                        @if(isset($pendingLoot['crit_chance']) || isset($pendingLoot['crit_bonus']))
                            <span class="text-purple-400">+{{ $pendingLoot['crit_chance'] ?? $pendingLoot['crit_bonus'] ?? 0 }}% CRIT</span>
                        @endif
                    </div>
                </div>
            </div>

            <p class="text-red-400 text-center text-sm mb-4">Reppusi on täynnä!</p>

            {{-- Inventory items to swap --}}
            @php $inventory = $player->getInventory(); @endphp
            @if(count($inventory) > 0)
                <div class="mb-4">
                    <p class="text-gray-400 text-sm mb-2">Vaihda esineeseen:</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($inventory as $index => $item)
                            @php
                                $itemRarityColor = match($item['rarity'] ?? 'common') {
                                    'legendary' => 'border-yellow-500 bg-yellow-900/30',
                                    'epic' => 'border-purple-500 bg-purple-900/30',
                                    'rare' => 'border-blue-500 bg-blue-900/30',
                                    'uncommon' => 'border-green-500 bg-green-900/30',
                                    default => 'border-gray-600 bg-gray-700'
                                };
                            @endphp
                            <form action="{{ route('match.swap-bonus-loot', $match->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="drop_index" value="{{ $index }}">
                                <button type="submit" class="w-full bg-gray-700 hover:bg-gray-600 p-2 rounded border {{ $itemRarityColor }} text-left transition">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 flex items-center justify-center shrink-0">
                                            @if(isset($item['icon']))
                                                {!! icon($item['icon']) !!}
                                            @else
                                                <span class="text-lg">&#x1F4E6;</span>
                                            @endif
                                        </div>
                                        <div class="truncate text-sm">{{ $item['name'] }}</div>
                                    </div>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex gap-3">
                <form action="{{ route('match.discard-bonus-loot', $match->id) }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full bg-gray-600 hover:bg-gray-500 text-white py-2 px-4 rounded transition">
                        Jätä esine
                    </button>
                </form>
            </div>
        </div>
    </div>
@else
<div class="h-full flex flex-col">
    <!-- Floor Progress Dots -->
    <div class="py-3 shrink-0">
        {{-- Legend --}}
        <div class="flex justify-center items-center gap-4 mb-2 text-xs text-gray-500">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> You</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Opponent</span>
        </div>
        {{-- Your progress (top row) --}}
        <div class="flex justify-center items-center gap-1 mb-1">
            @for($i = 1; $i <= $totalLevels; $i++)
                @php
                    $isYourCurrent = $i === $currentLevel + 1 && !$dungeonComplete;
                    $isYourCompleted = $i <= $currentLevel;
                @endphp
                <div class="w-2.5 h-2.5 rounded-full {{ $isYourCompleted ? 'bg-blue-500' : ($isYourCurrent ? 'bg-blue-500 animate-pulse ring-2 ring-blue-300' : 'bg-gray-700') }}"></div>
            @endfor
            @if($dungeonComplete)
                <span class="ml-2 text-xs text-green-400">✓</span>
            @endif
        </div>
        {{-- Opponent progress (bottom row) --}}
        <div class="flex justify-center items-center gap-1">
            @for($i = 1; $i <= $totalLevels; $i++)
                @php
                    $isOppCurrent = $i === $opponentLevel + 1 && !$opponentComplete;
                    $isOppCompleted = $i <= $opponentLevel;
                @endphp
                <div class="w-2.5 h-2.5 rounded-full {{ $isOppCompleted ? 'bg-red-500' : ($isOppCurrent ? 'bg-red-500 animate-pulse ring-2 ring-red-300' : 'bg-gray-700') }}"></div>
            @endfor
            @if($opponentComplete)
                <span class="ml-2 text-xs text-yellow-400">⚔️</span>
            @endif
        </div>
    </div>

    @if($dungeonComplete)
        <!-- Dungeon Complete - PvP Option -->
        <div class="flex-1 flex flex-col items-center justify-center">
            <div class="text-6xl mb-4">&#x1F3C6;</div>
            <h2 class="text-3xl font-bold text-yellow-400 mb-4">DUNGEON COMPLETE!</h2>
            <p class="text-gray-400 mb-8">You've conquered all {{ $totalLevels }} floors. Prepare for battle!</p>

            <a href="{{ route('match.pvp', $match->id) }}"
               class="bg-red-600 hover:bg-red-700 text-white text-xl font-bold py-4 px-12 rounded-lg transition-all active:scale-95 flex items-center gap-3">
                <span>&#x2694;</span>
                START PVP BATTLE
                <span>&#x2694;</span>
            </a>

            <p class="text-gray-500 text-sm mt-4">
                Tip: Use potions and adjust equipment before battling!
            </p>
        </div>
    @else
        <!-- Next Room Choices -->
        <div class="flex-1 flex flex-col items-center justify-center">
            <div class="text-gray-400 mb-6">Choose your path:</div>

            @php
                $nextLevel = $currentLevel + 1;
                $nextRooms = $roomsByLevel[$nextLevel] ?? collect();
            @endphp

            @if($nextLevel > $totalLevels)
                {{-- Safety check: should have been caught by dungeonComplete --}}
                <div class="text-center">
                    <div class="text-6xl mb-4">&#x1F3C6;</div>
                    <h2 class="text-2xl font-bold text-yellow-400 mb-4">All floors cleared!</h2>
                    <a href="{{ route('match.pvp', $match->id) }}"
                       class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg transition-all">
                        Proceed to PvP
                    </a>
                </div>
            @else
            {{-- Scout ability indicator --}}
            @if($hasScout && $scoutUses > 0)
                <div class="mb-4 text-center">
                    <span class="inline-flex items-center gap-1 text-cyan-400 text-sm bg-cyan-900/30 px-3 py-1 rounded-full">
                        <span>&#x1F441;</span> Scout: {{ $scoutUses }} {{ $scoutUses === 1 ? 'use' : 'uses' }} remaining
                    </span>
                </div>
            @endif

            <div class="flex flex-wrap gap-3 sm:gap-4 justify-center px-2">
                @forelse($nextRooms as $room)
                    @php
                        $canScout = $hasScout && $scoutUses > 0 && $room->displayed_type !== $room->actual_type && !$room->completed;
                    @endphp
                    <div class="flex flex-col items-center gap-2">
                        <form action="{{ route('match.room.enter', [$match->id, $room->id]) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="bg-gray-800 hover:bg-gray-700 border-2 border-gray-600 hover:border-blue-500
                                           rounded-lg p-3 sm:p-4 w-36 sm:w-44 transition-all transform hover:scale-105 active:scale-95
                                           @if($room->visited && $room->completed) opacity-50 cursor-not-allowed @endif"
                                    @if($room->visited && $room->completed) disabled @endif>

                                <!-- Room Icon -->
                                <div class="text-3xl sm:text-4xl mb-2">
                                    @switch($room->displayed_type)
                                        @case('enemy')
                                            &#x1F47E;
                                            @break
                                        @case('loot')
                                            &#x1F4B0;
                                            @break
                                        @case('shop')
                                            &#x1F6D2;
                                            @break
                                        @case('elite')
                                            &#x1F480;
                                            @break
                                        @case('boss')
                                            &#x1F409;
                                            @break
                                        @case('unknown')
                                            &#x2753;
                                            @break
                                        @case('empty')
                                            &#x1F6AB;
                                            @break
                                        @default
                                            &#x2753;
                                    @endswitch
                                </div>

                                <!-- Room Type -->
                                <div class="text-sm sm:text-base font-bold mb-1
                                    @switch($room->displayed_type)
                                        @case('enemy') text-red-400 @break
                                        @case('elite') text-purple-400 @break
                                        @case('boss') text-red-500 @break
                                        @case('loot') text-yellow-400 @break
                                        @case('shop') text-green-400 @break
                                        @case('unknown') text-gray-400 @break
                                        @case('empty') text-gray-500 @break
                                        @default text-gray-400
                                    @endswitch
                                ">
                                    {{ ucfirst($room->displayed_type) }}
                                </div>

                                <!-- Hint text -->
                                <div class="text-xs text-gray-500">
                                    @switch($room->displayed_type)
                                        @case('enemy')
                                            Fight for XP & Gold
                                            @break
                                        @case('elite')
                                            Tough fight, great loot
                                            @break
                                        @case('boss')
                                            Final challenge!
                                            @break
                                        @case('loot')
                                            Free treasure
                                            @break
                                        @case('shop')
                                            Buy items
                                            @break
                                        @case('unknown')
                                            ???
                                            @break
                                        @case('empty')
                                            Nothing here
                                            @break
                                    @endswitch
                                </div>

                                @if($room->completed)
                                    <div class="text-xs text-gray-600 mt-1">Completed</div>
                                @endif
                            </button>
                        </form>

                        {{-- Scout button --}}
                        @if($canScout)
                            <form action="{{ route('match.room.scout', [$match->id, $room->id]) }}" method="POST">
                                @csrf
                                <button type="submit"
                                        class="text-xs bg-cyan-700 hover:bg-cyan-600 text-white px-3 py-1 rounded transition flex items-center gap-1">
                                    <span>&#x1F441;</span> Scout
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="text-center">
                        <div class="text-gray-500 mb-2">No rooms available for level {{ $nextLevel }}</div>
                        <p class="text-gray-600 text-sm">Try refreshing the page.</p>
                    </div>
                @endforelse
            </div>
            @endif
        </div>
    @endif
</div>
@endif
@endsection
