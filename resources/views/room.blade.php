@extends('layouts.game')

@section('game-content')
@php
    $totalLevels = app(\App\Game\Content\ContentRepository::class)->getRule('dungeon.total_levels') ?? 10;
@endphp
<div class="h-full flex flex-col">
    <!-- Floor Progress + Room Header -->
    <div class="text-center mb-4 shrink-0">
        <!-- Progress Dots -->
        <div class="flex justify-center items-center gap-1 mb-2">
            @for($i = 1; $i <= $totalLevels; $i++)
                <div class="w-2.5 h-2.5 rounded-full {{ $i < $room->level ? 'bg-green-500' : ($i === $room->level ? 'bg-blue-500' : 'bg-gray-700') }}"></div>
            @endfor
        </div>
        <!-- Room Type -->
        <h1 class="text-xl font-bold">
            @if($room->displayed_type !== $room->actual_type && $room->visited)
                <span class="line-through text-gray-500">{{ ucfirst($room->displayed_type) }}</span>
                <span class="text-yellow-400">{{ ucfirst($room->actual_type) }}!</span>
            @else
                {{ ucfirst($room->actual_type) }}
            @endif
        </h1>
    </div>

    <!-- Room Content -->
    <div class="flex-1 flex flex-col items-center justify-center overflow-y-auto">
        @php $content = $room->getContent(); @endphp

        @if($room->isEnemy() || $room->isElite() || $room->isBoss())
            {{-- Enemy Room --}}
            @php $enemy = $content['enemy'] ?? null; @endphp
            @if($enemy && !$room->completed)
                <div class="text-center">
                    <div class="text-7xl mb-4">
                        @if($room->isBoss()) &#x1F409; @elseif($room->isElite()) &#x1F480; @else &#x1F47E; @endif
                    </div>
                    <h2 class="text-2xl font-bold mb-2
                        @if($room->isBoss()) text-red-500 @elseif($room->isElite()) text-purple-400 @else text-red-400 @endif
                    ">{{ $enemy['name'] }}</h2>
                    <div class="flex justify-center gap-6 mb-4 text-sm">
                        <span class="text-red-400">HP: {{ $enemy['hp'] }}</span>
                        <span class="text-orange-400">ATK: {{ $enemy['attack'] ?? 10 }}</span>
                        <span class="text-blue-400">DEF: {{ $enemy['defense'] ?? 0 }}</span>
                    </div>
                    <p class="text-gray-400 text-sm mb-6">
                        Rewards: {{ $enemy['gold_reward'] }} gold, {{ $enemy['xp_reward'] }} XP
                    </p>

                    <form action="{{ route('match.room.action', [$match->id, $room->id]) }}" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="fight">
                        <button type="submit"
                                class="bg-red-600 hover:bg-red-700 active:scale-95 text-white font-bold py-3 px-10 rounded-lg text-lg transition-all">
                            Fight!
                        </button>
                    </form>
                </div>
            @else
                <div class="text-center">
                    <div class="text-7xl mb-4 opacity-50">&#x2620;</div>
                    <p class="text-gray-400">Enemy defeated</p>
                </div>
            @endif

        @elseif($room->isLoot())
            {{-- Loot Room --}}
            @php $loot = $content['loot'] ?? null; @endphp
            @if($loot && !$room->completed)
                <div class="text-center">
                    @if(isset($loot['icon']))
                        <div class="mb-4 flex justify-center">
                            <div class="w-24 h-24 flex items-center justify-center">
                                {!! icon($loot['icon'], 'scale-[3] origin-center') !!}
                            </div>
                        </div>
                    @else
                        <div class="text-7xl mb-4">&#x1F4B0;</div>
                    @endif
                    <h2 class="text-2xl font-bold text-yellow-400 mb-2">{{ $loot['name'] }}</h2>
                    <p class="text-gray-400 mb-2">{{ ucfirst($loot['type']) }} - {{ ucfirst($loot['rarity'] ?? 'common') }}</p>

                    <div class="bg-gray-800 rounded-lg p-4 mb-6 text-sm inline-block">
                        @if(isset($loot['attack']))
                            @php
                                $minDmg = $loot['attack'] - ($loot['attack_variance'] ?? 0);
                                $maxDmg = $loot['attack'] + ($loot['attack_variance'] ?? 0);
                            @endphp
                            <p class="text-orange-400">DMG: {{ $minDmg }}-{{ $maxDmg }}</p>
                        @endif
                        @if(isset($loot['defense']))
                            <p class="text-blue-400">DEF: +{{ $loot['defense'] }}</p>
                        @endif
                        @if(isset($loot['max_hp_bonus']))
                            <p class="text-green-400">Max HP: +{{ $loot['max_hp_bonus'] }}</p>
                        @endif
                        @if(isset($loot['heal']))
                            <p class="text-green-400">Heal: {{ $loot['heal'] }} HP</p>
                        @endif
                        @if(isset($loot['crit_chance']) || isset($loot['crit_bonus']))
                            <p class="text-purple-400">Crit: +{{ $loot['crit_chance'] ?? $loot['crit_bonus'] ?? 0 }}%</p>
                        @endif
                        @if(isset($loot['attack_bonus']))
                            <p class="text-orange-400">ATK: +{{ $loot['attack_bonus'] }}</p>
                        @endif
                        @if(isset($loot['defense_bonus']))
                            <p class="text-blue-400">DEF: +{{ $loot['defense_bonus'] }}</p>
                        @endif
                    </div>

                    @php
                        $canLoot = $player->hasInventorySpace() ||
                                   (isset($loot['type']) && in_array($loot['type'], ['weapon', 'armor', 'accessory']) && !$player->getEquippedItem($loot['type']));
                    @endphp
                    <div class="flex gap-4 justify-center">
                        <form action="{{ route('match.room.action', [$match->id, $room->id]) }}" method="POST">
                            @csrf
                            <input type="hidden" name="action" value="loot">
                            <button type="submit"
                                    class="{{ $canLoot ? 'bg-yellow-600 hover:bg-yellow-700 active:scale-95' : 'bg-gray-600 cursor-not-allowed' }} text-white font-bold py-3 px-10 rounded-lg text-lg transition-all"
                                    @if(!$canLoot) disabled @endif>
                                Collect Loot
                            </button>
                        </form>
                        <form action="{{ route('match.room.action', [$match->id, $room->id]) }}" method="POST">
                            @csrf
                            <input type="hidden" name="action" value="skip_loot">
                            <button type="submit"
                                    class="bg-gray-600 hover:bg-gray-700 active:scale-95 text-white font-bold py-3 px-10 rounded-lg text-lg transition-all">
                                Leave It
                            </button>
                        </form>
                    </div>
                    @if(!$canLoot)
                        <p class="text-red-400 text-sm mt-3">Reppu on täynnä! Myy tai pudota esine.</p>
                    @endif
                </div>
            @else
                <div class="text-center">
                    <div class="text-7xl mb-4 opacity-50">&#x1F4E6;</div>
                    <p class="text-gray-400">Loot collected</p>
                </div>
            @endif

        @elseif($room->isShop())
            {{-- Shop Room --}}
            @php
                $shopItems = $content['shop_items'] ?? [];
                $dungeon = $player->targetDungeon;
                $hasPriceHike = $dungeon && $dungeon->hasModifier('price_hike');
                $hasDiscount = $dungeon && $dungeon->hasModifier('discount');
            @endphp
            <div class="w-full max-w-md">
                <!-- Shop Header (compact) -->
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">&#x1F6D2;</span>
                        <h2 class="text-xl font-bold text-green-400">Shop</h2>
                    </div>
                    <div class="text-right">
                        <span class="text-yellow-400 font-bold">{{ $player->gold }}g</span>
                        @if($hasPriceHike)
                            <span class="text-red-400 text-xs ml-2">+50%</span>
                        @endif
                        @if($hasDiscount)
                            <span class="text-green-400 text-xs ml-2">-25%</span>
                        @endif
                    </div>
                </div>

                <!-- Shop Items -->
                <div class="grid grid-cols-2 gap-2">
                    @foreach($shopItems as $item)
                        @php
                            $isSold = $item['sold'] ?? false;
                            $price = $item['shop_price'] ?? 0;
                            if ($hasPriceHike) $price = (int) ceil($price * 1.5);
                            if ($hasDiscount) $price = (int) ceil($price * 0.75);
                            $canAfford = $player->gold >= $price;
                            $rarityColor = match($item['rarity'] ?? 'common') {
                                'legendary' => 'border-yellow-500 bg-yellow-900/30',
                                'epic' => 'border-purple-500 bg-purple-900/30',
                                'rare' => 'border-blue-500 bg-blue-900/30',
                                'uncommon' => 'border-green-500 bg-green-900/30',
                                default => 'border-gray-600 bg-gray-700'
                            };
                        @endphp
                        @if($isSold)
                            {{-- Sold item - show empty slot --}}
                            <div class="bg-gray-800/50 rounded-lg p-2 border border-dashed border-gray-700">
                                <div class="flex gap-2">
                                    <div class="w-10 h-10 flex items-center justify-center rounded border-2 border-gray-700 border-dashed shrink-0">
                                        <span class="text-gray-600 text-xs">SOLD</span>
                                    </div>
                                    <div class="flex-1 min-w-0 flex items-center">
                                        <div class="text-gray-600 text-sm">Empty</div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-gray-800 rounded-lg p-2 {{ !$canAfford ? 'opacity-60' : '' }}">
                                <div class="flex gap-2">
                                    <div class="tooltip-shop">
                                        <div class="w-10 h-10 flex items-center justify-center rounded border-2 {{ $rarityColor }} shrink-0 overflow-hidden">
                                            @if(isset($item['icon']))
                                                {!! icon($item['icon']) !!}
                                            @else
                                                <span class="text-xl">&#x1F4E6;</span>
                                            @endif
                                        </div>
                                        <div class="tooltip-shop-content">
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
                                            @if(isset($item['heal']))
                                                <div class="text-green-400">+{{ $item['heal'] }} HP</div>
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
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-white text-sm font-bold truncate">{{ $item['name'] }}</div>
                                        <div class="text-yellow-400 text-xs">{{ $price }}g</div>
                                    </div>
                                </div>
                                <form action="{{ route('match.room.action', [$match->id, $room->id]) }}" method="POST" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="action" value="buy">
                                    <input type="hidden" name="item_id" value="{{ $item['id'] }}">
                                    <button type="submit"
                                            class="w-full {{ $canAfford ? 'bg-green-600 hover:bg-green-500' : 'bg-gray-600 cursor-not-allowed' }} py-1 rounded text-white text-xs transition"
                                            @if(!$canAfford) disabled @endif>
                                        Buy
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endforeach
                </div>

            </div>

        @elseif($room->isEmpty())
            {{-- Empty Room --}}
            <div class="text-center">
                <div class="text-7xl mb-4">&#x1F32C;</div>
                <h2 class="text-2xl font-bold mb-2">Empty Room</h2>
                <p class="text-gray-400">Nothing here... the room is empty.</p>
            </div>

        @else
            <div class="text-center">
                <p class="text-gray-400">Unknown room type</p>
            </div>
        @endif
    </div>

    <!-- Continue Button -->
    <div class="text-center py-4 shrink-0 border-t border-gray-700">
        <form action="{{ route('match.room.action', [$match->id, $room->id]) }}" method="POST" class="inline">
            @csrf
            <input type="hidden" name="action" value="leave">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-bold py-2 px-8 rounded transition-all">
                @if($room->completed || $room->isEmpty() || $room->isShop())
                    Continue
                @else
                    Leave Room
                @endif
            </button>
        </form>
    </div>
</div>
@endsection
