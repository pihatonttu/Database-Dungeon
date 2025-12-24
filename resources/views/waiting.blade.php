@extends('layouts.app')

@section('content')
<div x-data="waitingPolling()" x-init="startPolling()">
    <div class="text-center">
        <h1 class="text-3xl font-bold mb-4">{{ $message }}</h1>

        <div class="flex justify-center mb-6">
            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
        </div>

        <!-- Player Status -->
        <div class="bg-gray-800 p-6 rounded-lg max-w-md mx-auto">
            <h2 class="text-xl font-semibold mb-4">Player Status</h2>
            <div class="space-y-2 text-left">
                <div class="flex justify-between">
                    <span>Your name:</span>
                    <span class="font-medium">{{ $player->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>HP:</span>
                    <span class="font-medium text-green-400">{{ $player->current_hp }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Gold:</span>
                    <span class="font-medium text-yellow-400">{{ $player->gold }}</span>
                </div>
                <div class="flex justify-between">
                    <span>XP:</span>
                    <span class="font-medium text-blue-400">{{ $player->xp }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function waitingPolling() {
    return {
        startPolling() {
            setInterval(() => {
                fetch('{{ route("match.state", $match->id) }}')
                    .then(r => r.json())
                    .then(data => {
                        if (data.state === 'running' && data.all_setup_complete) {
                            window.location.href = '{{ route("match.dungeon", $match->id) }}';
                        } else if (data.state === 'pvp' || data.state === 'finished') {
                            window.location.href = '{{ route("match.pvp", $match->id) }}';
                        }
                    });
            }, 2000);
        }
    }
}
</script>
@endsection
