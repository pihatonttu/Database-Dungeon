@extends('layouts.app')

@section('content')
<div x-data="lobbyPolling()" x-init="startPolling()">
    <div class="text-center">
        <h1 class="text-3xl font-bold mb-2">Game Lobby</h1>
        <p class="text-gray-400 mb-6">Waiting for players...</p>

        <!-- Players List -->
        <div class="bg-gray-800 p-6 rounded-lg max-w-md mx-auto mb-6">
            <h2 class="text-xl font-semibold mb-4">Players</h2>
            <div class="space-y-3">
                @foreach($match->players as $p)
                    <div class="flex items-center justify-between bg-gray-700 p-3 rounded">
                        <span class="font-medium">{{ $p->name }}</span>
                        @if($p->id === $player->id)
                            <span class="text-sm text-blue-400">(You)</span>
                        @endif
                    </div>
                @endforeach

                @if($match->players->count() < 2)
                    <div class="flex items-center justify-center bg-gray-700 p-3 rounded border-2 border-dashed border-gray-600">
                        <span class="text-gray-400">Waiting for opponent...</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Share Link -->
        @if($match->players->count() < 2)
            <div class="bg-gray-800 p-6 rounded-lg max-w-md mx-auto mb-6">
                <h2 class="text-xl font-semibold mb-4">Share This Link</h2>
                <div class="flex gap-2">
                    <input
                        type="text"
                        value="{{ $shareUrl }}"
                        readonly
                        class="flex-1 px-4 py-2 bg-gray-700 rounded border border-gray-600 text-sm"
                        id="shareLink"
                    >
                    <button
                        onclick="navigator.clipboard.writeText(document.getElementById('shareLink').value)"
                        class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded transition"
                    >
                        Copy
                    </button>
                </div>
                <p class="text-sm text-gray-400 mt-2">Send this link to your opponent</p>
            </div>
        @else
            <div class="bg-green-800 p-6 rounded-lg max-w-md mx-auto mb-6">
                <p class="font-semibold">Both players ready!</p>
                <p class="text-sm text-gray-300 mt-1">Redirecting to hero selection...</p>
            </div>
        @endif
    </div>
</div>

<script>
function lobbyPolling() {
    return {
        startPolling() {
            setInterval(() => {
                fetch('{{ route("match.state", $match->id) }}')
                    .then(r => r.json())
                    .then(data => {
                        if (data.state !== 'lobby') {
                            window.location.href = '{{ route("match.setup", $match->id) }}';
                        } else if (data.player_count === 2) {
                            window.location.reload();
                        }
                    });
            }, 2000);
        }
    }
}
</script>
@endsection
