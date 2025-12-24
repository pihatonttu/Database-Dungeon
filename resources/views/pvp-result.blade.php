@extends('layouts.game')

@section('game-content')
@php
    $playerWon = $pvpBattle->winner_player_id === $player->id;
    $opponent = $pvpBattle->player1_id === $player->id ? $pvpBattle->player2 : $pvpBattle->player1;
    $playerNum = $pvpBattle->getPlayerNumber($player);
    $oppNum = $pvpBattle->getOpponentNumber($player);
@endphp

<div class="min-h-full flex flex-col items-center py-4 px-2">
    <!-- Result Banner -->
    <div class="text-center mb-4 sm:mb-6">
        @if($playerWon)
            <div class="text-5xl sm:text-6xl mb-2">&#x1F3C6;</div>
            <h1 class="text-3xl sm:text-4xl font-bold text-green-400 mb-1">VICTORY!</h1>
            <p class="text-base sm:text-lg text-gray-300">You defeated {{ $opponent->name }}!</p>
        @else
            <div class="text-5xl sm:text-6xl mb-2">&#x1F480;</div>
            <h1 class="text-3xl sm:text-4xl font-bold text-red-400 mb-1">DEFEAT</h1>
            <p class="text-base sm:text-lg text-gray-300">{{ $opponent->name }} won...</p>
        @endif
    </div>

    <!-- Battle Summary -->
    <div class="bg-gray-800 rounded-lg p-4 mb-4 w-full max-w-md">
        <h2 class="text-base font-bold mb-3 text-center text-gray-400">Battle Summary</h2>

        <div class="flex justify-between items-center mb-3">
            <!-- You -->
            <div class="text-center">
                <div class="text-blue-400 font-bold text-sm">{{ $player->name }}</div>
                <div class="text-xs text-gray-500">Lv.{{ $player->getLevel() }}</div>
                @php $finalHp = $pvpBattle->getPlayerHp($playerNum); @endphp
                <div class="text-base mt-1 {{ $finalHp > 0 ? 'text-green-400' : 'text-red-400' }}">
                    {{ $finalHp }} HP
                </div>
            </div>

            <!-- VS -->
            <div class="text-xl text-gray-600">VS</div>

            <!-- Opponent -->
            <div class="text-center">
                <div class="text-red-400 font-bold text-sm">{{ $opponent->name }}</div>
                <div class="text-xs text-gray-500">Lv.{{ $opponent->getLevel() }}</div>
                @php $oppFinalHp = $pvpBattle->getPlayerHp($oppNum); @endphp
                <div class="text-base mt-1 {{ $oppFinalHp > 0 ? 'text-green-400' : 'text-red-400' }}">
                    {{ $oppFinalHp }} HP
                </div>
            </div>
        </div>

        <div class="text-center text-gray-500 text-xs">
            Battle lasted {{ $pvpBattle->turn }} turns
        </div>
    </div>

    <!-- Match Result -->
    <div class="bg-gray-800 rounded-lg p-4 mb-4 w-full max-w-md text-center">
        @if($playerWon)
            <div class="text-3xl mb-1">&#x1F451;</div>
            <div class="text-xl font-bold text-yellow-400">
                MATCH CHAMPION!
            </div>
        @else
            <div class="text-lg text-gray-400">
                {{ $opponent->name }} wins the match
            </div>
        @endif
    </div>

    <!-- Combat Log -->
    @php $logs = $pvpBattle->getRecentLogs(10); @endphp
    @if(count($logs) > 0)
        <div class="bg-gray-800 rounded-lg p-3 mb-4 w-full max-w-md max-h-24 overflow-y-auto">
            <h3 class="text-xs text-gray-400 mb-1">Combat Log</h3>
            @foreach($logs as $log)
                <p class="text-xs text-gray-300">
                    <span class="text-gray-500">[{{ $log['turn'] }}]</span>
                    {{ $log['message'] }}
                </p>
            @endforeach
        </div>
    @endif

    <!-- Back to Dashboard -->
    <a href="{{ route('dashboard') }}"
       class="bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-bold py-2.5 px-6 rounded-lg transition-all">
        Back to Dashboard
    </a>
</div>
@endsection
