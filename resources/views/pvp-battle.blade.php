@extends('layouts.game')

@section('game-content')
@php
    $isMyTurn = $pvpBattle->isPlayersTurn($player);
    $playerNum = $pvpBattle->getPlayerNumber($player);
    $opponentNum = $pvpBattle->getOpponentNumber($player);
    $myHp = $pvpBattle->getPlayerHp($playerNum);
    $myMaxHp = $pvpBattle->getPlayerMaxHp($playerNum);
    $oppHp = $pvpBattle->getPlayerHp($opponentNum);
    $oppMaxHp = $pvpBattle->getPlayerMaxHp($opponentNum);
    $oppEquipment = $opponent->getEquipment();
@endphp

<div class="h-full flex flex-col" x-data="{ polling: {{ $isMyTurn ? 'false' : 'true' }} }" x-init="
    setInterval(() => {
        if (polling) {
            window.location.reload();
        }
    }, 2000)
">
    <!-- PvP Header -->
    <div class="text-center py-1 shrink-0">
        <div class="text-red-400 font-bold text-lg sm:text-xl">PVP BATTLE</div>
        <div class="text-xs sm:text-sm {{ $isMyTurn ? 'text-green-400' : 'text-yellow-400' }}">
            @if($isMyTurn)
                YOUR TURN
            @else
                OPPONENT'S TURN...
            @endif
        </div>
        <div class="text-gray-500 text-xs">Turn {{ $pvpBattle->turn }}</div>
    </div>

    <!-- Combat Log -->
    @php $recentLogs = $pvpBattle->getRecentLogs(3); @endphp
    @if(count($recentLogs) > 0)
        <div class="bg-gray-800 mx-2 sm:mx-4 p-2 rounded text-sm max-h-16 overflow-y-auto shrink-0">
            @foreach($recentLogs as $log)
                <p class="text-gray-300 text-xs">
                    <span class="text-gray-500">[{{ $log['turn'] }}]</span>
                    {{ $log['message'] }}
                </p>
            @endforeach
        </div>
    @endif

    <!-- Battle Arena -->
    <div class="flex-1 flex flex-col justify-center items-center p-2 sm:p-4 min-h-0">
        <!-- Opponent Card -->
        <div class="bg-gray-800 rounded-lg p-4 sm:p-5 w-full max-w-md">
            <!-- Header: Icon, Name, Level -->
            <div class="flex items-center gap-3 mb-3">
                <div class="text-4xl sm:text-5xl">&#x1F47B;</div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-red-400">{{ $opponent->name }}</h2>
                    <span class="text-purple-400 text-sm">Lv.{{ $opponent->getLevel() }}</span>
                </div>
            </div>

            <!-- HP Bar -->
            @php $oppHpPct = ($oppHp / $oppMaxHp) * 100; @endphp
            <div class="mb-3">
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-red-400 font-bold">HP</span>
                    <span class="font-bold">{{ $oppHp }}/{{ $oppMaxHp }}</span>
                </div>
                <div class="h-4 bg-gray-700 rounded-full">
                    <div class="h-full {{ $oppHpPct > 50 ? 'bg-red-500' : ($oppHpPct > 25 ? 'bg-yellow-500' : 'bg-red-600') }} rounded-full transition-all" style="width: {{ $oppHpPct }}%"></div>
                </div>
            </div>

            <!-- Stats -->
            <div class="flex justify-between text-sm mb-3 px-1">
                <span class="text-orange-400 font-bold">ATK: {{ $pvpBattle->getPlayerAttack($opponentNum) }}</span>
                <span class="text-blue-400 font-bold">DEF: {{ $pvpBattle->getPlayerDefense($opponentNum) }}</span>
                <span class="text-purple-400 font-bold">CRIT: {{ $pvpBattle->getPlayerCritChance($opponentNum) }}%</span>
            </div>

            <!-- Equipment Icons -->
            <div class="border-t border-gray-700 pt-3">
                <div class="flex justify-center gap-2">
                    @foreach(['weapon', 'armor', 'accessory'] as $slot)
                        @php
                            $item = $oppEquipment[$slot] ?? null;
                            $itemRarity = $item ? ($item['rarity'] ?? 'common') : null;
                            $slotIcon = match($slot) {
                                'weapon' => '&#x2694;',
                                'armor' => '&#x1F6E1;',
                                'accessory' => '&#x1F48D;',
                                default => '&#x1F4E6;'
                            };
                            $rarityBorder = $item ? match($itemRarity) {
                                'legendary' => 'border-yellow-500',
                                'epic' => 'border-purple-500',
                                'rare' => 'border-blue-500',
                                'uncommon' => 'border-green-500',
                                default => 'border-gray-600'
                            } : 'border-gray-700 border-dashed';
                            $rarityGlow = $item ? 'rarity-glow-' . $itemRarity : 'bg-transparent';
                        @endphp
                        <div class="tooltip-shop">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center rounded border {{ $rarityBorder }} {{ $rarityGlow }} {{ $item ? '' : 'text-gray-600' }} overflow-hidden">
                                @if($item && isset($item['icon']))
                                    {!! icon($item['icon']) !!}
                                @else
                                    <span class="text-lg sm:text-xl">{!! $slotIcon !!}</span>
                                @endif
                            </div>
                            @if($item)
                                <div class="tooltip-shop-content text-left" style="min-width: 160px;">
                                    <div class="font-bold text-white">{{ $item['name'] }}</div>
                                    <div class="text-gray-400 text-xs mb-1">{{ ucfirst($slot) }} - {{ ucfirst($itemRarity) }}</div>
                                    @if(isset($item['attack']))
                                        @php $min = $item['attack'] - ($item['attack_variance'] ?? 0); $max = $item['attack'] + ($item['attack_variance'] ?? 0); @endphp
                                        <div class="text-orange-400">{{ $min }}-{{ $max }} DMG</div>
                                    @endif
                                    @if(isset($item['defense']))
                                        <div class="text-blue-400">+{{ $item['defense'] }} DEF</div>
                                    @endif
                                    @if(isset($item['max_hp_bonus']))
                                        <div class="text-green-400">+{{ $item['max_hp_bonus'] }} HP</div>
                                    @endif
                                    @if(isset($item['crit_chance']) || isset($item['crit_bonus']))
                                        <div class="text-purple-400">+{{ $item['crit_chance'] ?? $item['crit_bonus'] ?? 0 }}% CRIT</div>
                                    @endif
                                    @if(isset($item['attack_bonus']))
                                        <div class="text-orange-400">+{{ $item['attack_bonus'] }} ATK</div>
                                    @endif
                                    @if(isset($item['defense_bonus']))
                                        <div class="text-blue-400">+{{ $item['defense_bonus'] }} DEF</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Opponent's Cards -->
            @php
                $oppCardIds = $opponent->getCards();
                $contentRepo = app(\App\Game\Content\ContentRepository::class);
                $cardIcons = [
                    'deception' => '🎭',
                    'difficulty' => '💀',
                    'utility' => '✨',
                ];
            @endphp
            @if(count($oppCardIds) > 0)
                <div class="border-t border-gray-700 pt-3 mt-3">
                    <div class="text-gray-500 text-xs mb-2 text-center">Opponent's Cards</div>
                    <div class="flex flex-wrap justify-center gap-1">
                        @foreach($oppCardIds as $cardId)
                            @php $card = $contentRepo->getCard($cardId); @endphp
                            @if($card)
                                @php
                                    $cardTypeColor = match($card['type']) {
                                        'deception' => 'border-purple-500/50 bg-purple-900/30',
                                        'difficulty' => 'border-red-500/50 bg-red-900/30',
                                        'utility' => 'border-green-500/50 bg-green-900/30',
                                        default => 'border-gray-600 bg-gray-800'
                                    };
                                @endphp
                                <div class="tooltip-shop">
                                    <div class="px-2 py-1 rounded border {{ $cardTypeColor }} text-xs flex items-center gap-1">
                                        <span>{{ $cardIcons[$card['type']] ?? '🃏' }}</span>
                                        <span>{{ $card['name'] }}</span>
                                    </div>
                                    <div class="tooltip-shop-content text-left" style="min-width: 180px;">
                                        <div class="font-bold text-white">{{ $card['name'] }}</div>
                                        <div class="text-gray-400 text-xs mb-1">{{ ucfirst($card['type']) }} - {{ $card['target'] === 'opponent' ? 'vs You' : 'For Them' }}</div>
                                        <div class="text-gray-300 text-xs">{{ $card['description'] }}</div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Action Bar -->
    <div class="bg-gray-800 border-t border-gray-700 p-2 sm:p-3 shrink-0">
        @if($isMyTurn)
            <form action="{{ route('pvp.attack', [$match->id, $pvpBattle->id]) }}" method="POST">
                @csrf
                <button type="submit"
                        class="w-full bg-red-600 hover:bg-red-700 active:scale-95 text-white text-lg sm:text-xl font-bold py-3 rounded-lg transition-all">
                    ATTACK!
                </button>
            </form>
        @else
            <div class="text-center text-gray-400 py-2 sm:py-3">
                <div class="flex items-center justify-center gap-2">
                    <div class="w-2 h-2 bg-yellow-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                    <div class="w-2 h-2 bg-yellow-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                    <div class="w-2 h-2 bg-yellow-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                </div>
                <p class="mt-1 text-sm">Waiting for opponent's move...</p>
            </div>
        @endif
    </div>
</div>
@endsection
