@extends('layouts.game')

@section('game-content')
@php
    $enemies = $combatState->getEnemies();
    $totalGold = 0;
    $totalXp = 0;
    foreach ($enemies as $e) {
        $totalGold += $e['gold_reward'] ?? 0;
        $totalXp += $e['xp_reward'] ?? 0;
    }
@endphp
<div class="h-full flex flex-col relative">
    <!-- Combat Header -->
    <div class="text-center py-2 shrink-0">
        <div class="text-red-400 font-bold text-lg">COMBAT</div>
        <div class="text-gray-500 text-sm">Turn {{ $combatState->turn }}</div>
        <div class="text-xs text-gray-600 mt-1">
            Rewards: <span class="text-yellow-400">{{ $totalGold }}g</span> + <span class="text-blue-400">{{ $totalXp }} XP</span>
        </div>
    </div>

    <!-- Enemies Section -->
    <div class="flex-1 flex flex-col justify-center overflow-y-auto">
        <div class="flex justify-center gap-4 mb-6">
            @foreach($enemies as $index => $enemy)
                @php $isDead = ($enemy['current_hp'] ?? 0) <= 0; @endphp
                <div class="bg-gray-800 rounded-lg p-4 min-w-[180px] {{ $isDead ? 'opacity-40' : '' }}">
                    <!-- Enemy Icon -->
                    <div class="text-4xl text-center mb-2">
                        @if($isDead)
                            &#x2620;
                        @elseif(($enemy['is_boss'] ?? false) || ($enemy['tier'] ?? '') === 'boss')
                            &#x1F409;
                        @elseif(($enemy['is_elite'] ?? false) || ($enemy['tier'] ?? '') === 'elite')
                            &#x1F480;
                        @else
                            &#x1F47E;
                        @endif
                    </div>

                    <div class="font-bold text-center text-white">{{ $enemy['name'] }}</div>

                    <!-- Enemy HP Bar -->
                    <div class="mt-2">
                        @php $enemyHpPct = $enemy['hp'] > 0 ? (($enemy['current_hp'] ?? 0) / $enemy['hp']) * 100 : 0; @endphp
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-red-400">HP</span>
                            <span>{{ $enemy['current_hp'] ?? 0 }}/{{ $enemy['hp'] }}</span>
                        </div>
                        <div class="h-2 bg-gray-700 rounded-full">
                            <div class="h-full bg-red-500 rounded-full transition-all" style="width: {{ $enemyHpPct }}%"></div>
                        </div>
                    </div>

                    <!-- Enemy Stats -->
                    <div class="flex justify-between text-xs text-gray-400 mt-2">
                        <span>ATK: {{ $enemy['attack'] ?? 0 }}</span>
                        <span>DEF: {{ $enemy['defense'] ?? 0 }}</span>
                    </div>

                    <!-- Attack Button -->
                    @if(!$isDead)
                        <form action="{{ route('combat.attack', [$match->id, $combatState->id]) }}" method="POST" class="mt-3">
                            @csrf
                            <input type="hidden" name="target" value="{{ $index }}">
                            <button type="submit"
                                    class="w-full bg-red-600 hover:bg-red-700 active:scale-95 text-white py-2 px-4 rounded transition-all font-bold">
                                Attack
                            </button>
                        </form>
                    @else
                        <div class="text-center text-gray-500 text-sm mt-3 py-2">DEFEATED</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Flee Button -->
    <div class="absolute bottom-4 right-4">
        <form action="{{ route('combat.flee', [$match->id, $combatState->id]) }}" method="POST">
            @csrf
            <button type="submit" title="Flee"
                    class="bg-gray-700 hover:bg-gray-600 active:scale-95 text-white p-3 rounded-full transition-all text-xl">
                &#x1F3C3;
            </button>
        </form>
    </div>
</div>
@endsection
