@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Tervetuloa, {{ $account->display_name }}!</h1>
            <p class="text-gray-400">Valitse peli tai luo uusi</p>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="text-gray-400 hover:text-white transition-colors">
                Kirjaudu ulos
            </button>
        </form>
    </div>

    <!-- Account Statistics -->
    @if($stats['total_games'] > 0)
    @php
        $heroIcons = [
            'strategist' => '🧙',
            'warrior' => '⚔️',
            'rogue' => '🗡️',
            'paladin' => '🛡️',
            'ranger' => '🏹',
            'berserker' => '🪓',
            'gambler' => '🎲',
        ];
        $heroNames = [
            'strategist' => 'Strategist',
            'warrior' => 'Warrior',
            'rogue' => 'Rogue',
            'paladin' => 'Paladin',
            'ranger' => 'Ranger',
            'berserker' => 'Berserker',
            'gambler' => 'Gambler',
        ];
    @endphp
    <div class="bg-gray-800 p-6 rounded-lg mb-6">
        <h2 class="text-xl font-bold mb-4">Tilastot</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
            <!-- Total Games -->
            <div class="bg-gray-700 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-white">{{ $stats['total_games'] }}</div>
                <div class="text-gray-400 text-sm">Peleja</div>
            </div>
            <!-- Wins -->
            <div class="bg-gray-700 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-green-400">{{ $stats['wins'] }}</div>
                <div class="text-gray-400 text-sm">Voittoa</div>
            </div>
            <!-- Losses -->
            <div class="bg-gray-700 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-red-400">{{ $stats['losses'] }}</div>
                <div class="text-gray-400 text-sm">Tappiota</div>
            </div>
            <!-- Win Rate -->
            <div class="bg-gray-700 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold {{ $stats['win_rate'] >= 50 ? 'text-green-400' : 'text-yellow-400' }}">{{ $stats['win_rate'] }}%</div>
                <div class="text-gray-400 text-sm">Voitto-%</div>
            </div>
        </div>

        <!-- Additional Stats Row -->
        <div class="flex flex-wrap gap-4 text-sm">
            @if($stats['streak'] > 1)
                <div class="flex items-center gap-2 bg-gray-700 px-3 py-2 rounded-lg">
                    @if($stats['streak_type'] === 'win')
                        <span class="text-green-400">🔥 {{ $stats['streak'] }} voittoa putkeen!</span>
                    @else
                        <span class="text-red-400">💀 {{ $stats['streak'] }} tappiota putkeen</span>
                    @endif
                </div>
            @endif

            @if($stats['favorite_hero'])
                <div class="flex items-center gap-2 bg-gray-700 px-3 py-2 rounded-lg">
                    <span class="text-gray-400">Suosikki:</span>
                    <span class="text-white">{{ $heroIcons[$stats['favorite_hero']] ?? '' }} {{ $heroNames[$stats['favorite_hero']] ?? $stats['favorite_hero'] }}</span>
                    <span class="text-gray-500">({{ $stats['hero_stats'][$stats['favorite_hero']]['games'] ?? 0 }} peleja)</span>
                </div>
            @endif

            @if($stats['best_hero'] && $stats['best_hero'] !== $stats['favorite_hero'])
                <div class="flex items-center gap-2 bg-gray-700 px-3 py-2 rounded-lg">
                    <span class="text-gray-400">Paras:</span>
                    <span class="text-white">{{ $heroIcons[$stats['best_hero']] ?? '' }} {{ $heroNames[$stats['best_hero']] ?? $stats['best_hero'] }}</span>
                    <span class="text-green-400">({{ $stats['best_hero_win_rate'] }}% voitto)</span>
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Create New Game -->
    <div class="bg-gray-800 p-6 rounded-lg mb-6">
        <h2 class="text-xl font-bold mb-4">Luo uusi peli</h2>
        <form action="{{ route('match.create') }}" method="POST" class="flex gap-4 items-end">
            @csrf
            <div class="flex-1">
                <label class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <input type="checkbox" name="is_public" value="1" checked class="rounded bg-gray-700 border-gray-600">
                    Julkinen peli (muut voivat liittya)
                </label>
            </div>
            <button
                type="submit"
                class="bg-green-600 hover:bg-green-700 active:scale-95 text-white font-bold py-3 px-8 rounded-lg transition-all duration-150"
            >
                + Luo peli
            </button>
        </form>
    </div>

    <!-- My Active Games -->
    @if($myGames->count() > 0)
    <div class="bg-gray-800 p-6 rounded-lg mb-6">
        <h2 class="text-xl font-bold mb-4">Omat pelit</h2>
        <div class="space-y-3">
            @foreach($myGames as $game)
                @php
                    $opponent = $game->players->where('account_id', '!=', $account->id)->first();
                    $myPlayer = $game->players->where('account_id', $account->id)->first();
                    $heroIcons = [
                        'strategist' => '🧙',
                        'warrior' => '⚔️',
                        'rogue' => '🗡️',
                        'paladin' => '🛡️',
                        'ranger' => '🏹',
                        'berserker' => '🪓',
                    ];
                    $myHeroIcon = $myPlayer && $myPlayer->hero_id ? ($heroIcons[$myPlayer->hero_id] ?? '') : '';
                    $opponentHeroIcon = $opponent && $opponent->hero_id ? ($heroIcons[$opponent->hero_id] ?? '') : '';

                    // Determine the correct route
                    if ($game->isLobby()) {
                        $gameRoute = route('match.lobby', $game->id);
                    } elseif (!$myPlayer->hero_id) {
                        $gameRoute = route('match.hero', $game->id);
                    } elseif ($game->isSetup() || !$myPlayer->setup_complete) {
                        $gameRoute = route('match.setup', $game->id);
                    } elseif ($game->isFinished() || $game->isPvp()) {
                        $gameRoute = route('match.pvp', $game->id);
                    } else {
                        $gameRoute = route('match.dungeon', $game->id);
                    }
                @endphp
                <a href="{{ $gameRoute }}"
                   class="block bg-gray-700 hover:bg-gray-600 p-4 rounded-lg transition-colors">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold">
                                @if($myHeroIcon)<span class="mr-1">{{ $myHeroIcon }}</span>@endif
                                vs
                                @if($opponentHeroIcon)<span class="ml-1">{{ $opponentHeroIcon }}</span>@endif
                                {{ $opponent ? $opponent->name : 'Odottaa vastustajaa...' }}
                            </span>
                            @if($myPlayer && $myPlayer->hero_id)
                                <span class="text-gray-400 ml-2">
                                    (HP: {{ $myPlayer->current_hp }}/{{ $myPlayer->max_hp ?? 100 }}, Gold: {{ $myPlayer->gold }})
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            @switch($game->state)
                                @case('lobby')
                                    <span class="text-yellow-400 text-sm">Odottaa pelaajia</span>
                                    @break
                                @case('setup')
                                    @if(!$myPlayer->hero_id)
                                        <span class="text-purple-400 text-sm">Valitse sankari</span>
                                    @elseif(!$myPlayer->setup_complete)
                                        <span class="text-blue-400 text-sm">Korttivalinta</span>
                                    @else
                                        <span class="text-gray-400 text-sm">Odottaa vastustajaa...</span>
                                    @endif
                                    @break
                                @case('running')
                                    <span class="text-green-400 text-sm">Dungeon {{ $myPlayer->current_level }}/8</span>
                                    @break
                                @case('pvp')
                                    <span class="text-red-400 text-sm">PvP!</span>
                                    @break
                            @endswitch
                            <span class="text-gray-400">→</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Open Public Games -->
    @if($openGames->count() > 0)
    <div class="bg-gray-800 p-6 rounded-lg mb-6">
        <h2 class="text-xl font-bold mb-4">Avoimet pelit</h2>
        <div class="space-y-3">
            @foreach($openGames as $game)
                @php
                    $creator = $game->players->first();
                @endphp
                <div class="flex justify-between items-center bg-gray-700 p-4 rounded-lg">
                    <div>
                        <span class="font-bold">{{ $creator?->name ?? 'Unknown' }}</span>
                        <span class="text-gray-400 ml-2">odottaa vastustajaa</span>
                    </div>
                    <form action="{{ route('match.join', $game->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="name" value="{{ $account->display_name }}">
                        <button
                            type="submit"
                            class="bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-bold py-2 px-6 rounded-lg transition-all duration-150"
                        >
                            Liity
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="bg-gray-800 p-6 rounded-lg mb-6 text-center text-gray-400">
        <p>Ei avoimia peleja. Luo oma peli tai odota!</p>
    </div>
    @endif

    <!-- Recent Finished Games -->
    @if($finishedGames->count() > 0)
    <div class="bg-gray-800 p-6 rounded-lg">
        <h2 class="text-xl font-bold mb-4">Viimeisimmat pelit</h2>
        <div class="space-y-3">
            @foreach($finishedGames as $game)
                @php
                    $opponent = $game->players->where('account_id', '!=', $account->id)->first();
                    $myPlayer = $game->players->where('account_id', $account->id)->first();
                    $won = $game->winner_player_id === $myPlayer?->id;
                @endphp
                <a href="{{ route('match.pvp', $game->id) }}"
                   class="block bg-gray-700 hover:bg-gray-600 p-4 rounded-lg transition-colors">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-bold">vs {{ $opponent?->name ?? 'Unknown' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            @if($won)
                                <span class="text-green-400 font-bold">VOITTO!</span>
                            @else
                                <span class="text-red-400">Tappio</span>
                            @endif
                            <span class="text-gray-400">→</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
