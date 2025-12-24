@extends('layouts.game')

@section('game-content')
<div class="h-full flex flex-col items-center justify-center" x-data="{ polling: true }" x-init="
    setInterval(() => {
        if (polling) {
            fetch('{{ route('match.state', $match->id) }}')
                .then(r => r.json())
                .then(data => {
                    if (data.all_dungeons_complete) {
                        window.location.reload();
                    }
                });
        }
    }, 2000)
">
    <div class="text-center">
        <div class="text-6xl mb-6 animate-pulse">&#x2694;</div>
        <h1 class="text-3xl font-bold text-yellow-400 mb-4">Waiting for Opponent</h1>
        <p class="text-gray-400 mb-2">You've completed the dungeon!</p>
        <p class="text-gray-500 text-sm mb-8">Waiting for your opponent to finish their dungeon...</p>

        <div class="flex items-center justify-center gap-2 text-gray-500">
            <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
            <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
            <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
        </div>

        <p class="text-gray-600 text-xs mt-8">
            Tip: Use this time to prepare your equipment!
        </p>
    </div>
</div>
@endsection
