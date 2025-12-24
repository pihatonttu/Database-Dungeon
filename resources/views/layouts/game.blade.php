<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DataBase Dungeon' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        html, body { height: 100%; overflow: hidden; }
        .tooltip { position: relative; }
        .tooltip .tooltip-content {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            left: calc(100% + 8px);
            top: 0;
            background: #111827;
            border: 1px solid #374151;
            border-radius: 4px;
            padding: 8px;
            min-width: 150px;
            z-index: 100;
            transition: opacity 0.15s;
        }
        /* Invisible bridge to keep hover */
        .tooltip .tooltip-content::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 0;
            width: 12px;
            height: 100%;
        }
        .tooltip:hover .tooltip-content,
        .tooltip .tooltip-content:hover {
            visibility: visible;
            opacity: 1;
        }
        /* Shop tooltip - appears below icon */
        .tooltip-shop { position: relative; }
        .tooltip-shop-content {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            top: calc(100% + 4px);
            left: 50%;
            transform: translateX(-50%);
            background: #111827;
            border: 1px solid #374151;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 100;
            transition: opacity 0.15s;
        }
        .tooltip-shop:hover .tooltip-shop-content {
            visibility: visible;
            opacity: 1;
        }
        /* Player stats tooltip - appears below name */
        .tooltip-player { position: relative; display: inline-block; }
        .tooltip-player-content {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            top: calc(100% + 4px);
            left: 50%;
            transform: translateX(-50%);
            background: #111827;
            border: 1px solid #374151;
            border-radius: 6px;
            padding: 12px;
            min-width: 240px;
            z-index: 100;
            transition: opacity 0.15s;
            text-align: left;
        }
        .tooltip-player:hover .tooltip-player-content {
            visibility: visible;
            opacity: 1;
        }
        /* Rarity radial glow backgrounds */
        .rarity-glow-common {
            background: radial-gradient(circle at center, rgba(107, 114, 128, 0.4) 0%, transparent 70%);
        }
        .rarity-glow-uncommon {
            background: radial-gradient(circle at center, rgba(34, 197, 94, 0.5) 0%, transparent 70%);
        }
        .rarity-glow-rare {
            background: radial-gradient(circle at center, rgba(59, 130, 246, 0.5) 0%, transparent 70%);
        }
        .rarity-glow-epic {
            background: radial-gradient(circle at center, rgba(168, 85, 247, 0.5) 0%, transparent 70%);
        }
        .rarity-glow-legendary {
            background: radial-gradient(circle at center, rgba(234, 179, 8, 0.6) 0%, transparent 70%);
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 h-screen flex flex-col">
    <!-- Top Bar -->
    <div class="bg-gray-800 border-b border-gray-700 px-3 py-1 flex justify-between items-center shrink-0">
        <a href="{{ route('dashboard') }}" class="text-lg font-bold text-yellow-400 hover:text-yellow-300 transition-colors">DataBase Dungeon</a>
        <div class="text-xs text-gray-500">
            @if(isset($match))
                {{ substr($match->id, 0, 8) }}
            @endif
        </div>
    </div>

    <!-- Main Game Area -->
    <div class="flex-1 flex overflow-visible">
        <!-- Left Sidebar - Player Info (compact) -->
        <div class="w-56 bg-gray-800 border-r border-gray-700 flex flex-col text-xs overflow-visible shrink-0">
            @if(isset($player))
                <!-- Player Header + HP -->
                <div class="p-2 border-b border-gray-700">
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-bold text-blue-400 flex items-center gap-1">
                            @if($player->hero_id)
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
                                @endphp
                                <span>{{ $heroIcons[$player->hero_id] ?? '' }}</span>
                            @endif
                            {{ $player->name }}
                        </span>
                        @php
                            $currentLevel = $player->getLevel();
                            $levelThresholds = \App\Models\Player::LEVEL_THRESHOLDS;
                            $currentThreshold = $levelThresholds[$currentLevel] ?? 0;
                            $nextThreshold = $levelThresholds[$currentLevel + 1] ?? null;
                            if ($nextThreshold) {
                                $xpInLevel = $player->xp - $currentThreshold;
                                $xpNeeded = $nextThreshold - $currentThreshold;
                                $xpPct = ($xpInLevel / $xpNeeded) * 100;
                            } else {
                                $xpPct = 100;
                                $xpInLevel = 0;
                                $xpNeeded = 0;
                            }
                            $atkPerLevel = \App\Models\Player::ATTACK_PER_LEVEL;
                            $hpPerLevel = \App\Models\Player::MAX_HP_PER_LEVEL;
                            $levelAtkBonus = ($currentLevel - 1) * $atkPerLevel;
                            $levelHpBonus = ($currentLevel - 1) * $hpPerLevel;
                        @endphp
                        <div class="tooltip-player cursor-help">
                            <span class="text-purple-400 hover:text-purple-300">Lv.{{ $currentLevel }}</span>
                            <div class="tooltip-player-content text-xs">
                                <div class="font-bold text-white mb-2">Level {{ $currentLevel }}</div>

                                <!-- XP Progress -->
                                @if($nextThreshold)
                                    <div class="mb-3">
                                        <div class="flex justify-between text-gray-400 mb-1">
                                            <span>XP Progress</span>
                                            <span>{{ $player->xp }} / {{ $nextThreshold }}</span>
                                        </div>
                                        <div class="h-2 bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full bg-purple-500 transition-all" style="width: {{ $xpPct }}%"></div>
                                        </div>
                                        <div class="text-gray-500 text-center mt-1">{{ $nextThreshold - $player->xp }} XP to next level</div>
                                    </div>
                                @else
                                    <div class="text-yellow-400 mb-3">MAX LEVEL!</div>
                                @endif

                                <!-- Current Bonuses -->
                                <div class="border-t border-gray-600 pt-2 mb-2">
                                    <div class="text-gray-400 mb-1">Current Level Bonuses:</div>
                                    <div class="text-orange-400">+{{ $levelAtkBonus }} ATK</div>
                                    <div class="text-green-400">+{{ $levelHpBonus }} Max HP</div>
                                </div>

                                <!-- Next Level Preview -->
                                @if($nextThreshold)
                                    <div class="border-t border-gray-600 pt-2">
                                        <div class="text-gray-400 mb-1">Next Level ({{ $currentLevel + 1 }}):</div>
                                        <div class="text-orange-400">+{{ $atkPerLevel }} ATK</div>
                                        <div class="text-green-400">+{{ $hpPerLevel }} Max HP</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @php
                        // Check for active PvP battle to show battle HP
                        $displayHp = $player->current_hp;
                        $displayMaxHp = $player->getMaxHp();
                        if (isset($pvpBattle) && $pvpBattle && $pvpBattle->is_active) {
                            $playerBattleNum = $pvpBattle->getPlayerNumber($player);
                            if ($playerBattleNum) {
                                $displayHp = $pvpBattle->getPlayerHp($playerBattleNum);
                                $displayMaxHp = $pvpBattle->getPlayerMaxHp($playerBattleNum);
                            }
                        }
                        $hpPct = ($displayHp / $displayMaxHp) * 100;
                    @endphp
                    <div class="h-3 bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-green-500 transition-all" style="width: {{ $hpPct }}%"></div>
                    </div>
                    <div class="text-center text-gray-400 mt-0.5">{{ $displayHp }}/{{ $displayMaxHp }} HP</div>
                </div>

                <!-- Stats Row (always visible, with tooltips) -->
                @php
                    $equipment = $player->getEquipment();
                    $weapon = $equipment['weapon'] ?? null;
                    $armor = $equipment['armor'] ?? null;
                    $accessory = $equipment['accessory'] ?? null;

                    // DMG breakdown
                    $baseAtk = $player->base_attack ?? 5;
                    $weaponAtk = $weapon['attack'] ?? 0;
                    $weaponVar = $weapon['attack_variance'] ?? 0;
                    $accAtkBonus = $accessory['attack_bonus'] ?? 0;
                    $minDmg = $player->getTotalAttack() - $player->getAttackVariance();
                    $maxDmg = $player->getTotalAttack() + $player->getAttackVariance();

                    // DEF breakdown
                    $baseDef = $player->base_defense ?? 0;
                    $armorDef = $armor['defense'] ?? 0;
                    $accDefBonus = $accessory['defense_bonus'] ?? 0;

                    // CRIT breakdown - use player's crit_chance (from hero) or default 5%
                    $baseCrit = $player->crit_chance ?? 5;
                    $weaponCrit = $weapon['crit_bonus'] ?? ($weapon['crit_chance'] ?? 0);
                    $armorCrit = $armor['crit_chance'] ?? 0;
                    $accCrit = $accessory['crit_chance'] ?? ($accessory['crit_bonus'] ?? 0);
                @endphp
                <div class="px-2 py-1 border-b border-gray-700 flex justify-between text-center">
                    <div class="tooltip cursor-help">
                        <span class="text-orange-400 font-bold">{{ $minDmg }}-{{ $maxDmg }}</span>
                        <span class="text-gray-500">DMG</span>
                        <div class="tooltip-content text-xs text-left">
                            <div class="font-bold text-white mb-1">Damage Breakdown</div>
                            <div class="text-gray-400">Base: {{ $baseAtk }}</div>
                            @if($weapon)
                                <div class="text-orange-400">{{ $weapon['name'] }}: {{ $weaponAtk }}±{{ $weaponVar }}</div>
                            @endif
                            @if($accAtkBonus > 0)
                                <div class="text-orange-400">{{ $accessory['name'] }}: +{{ $accAtkBonus }}</div>
                            @endif
                            <div class="border-t border-gray-600 mt-1 pt-1 text-white">Total: {{ $minDmg }}-{{ $maxDmg }}</div>
                        </div>
                    </div>
                    <div class="tooltip cursor-help">
                        <span class="text-blue-400 font-bold">{{ $player->getTotalDefense() }}</span>
                        <span class="text-gray-500">DEF</span>
                        <div class="tooltip-content text-xs text-left">
                            <div class="font-bold text-white mb-1">Defense Breakdown</div>
                            <div class="text-gray-400">Base: {{ $baseDef }}</div>
                            @if($armor)
                                <div class="text-blue-400">{{ $armor['name'] }}: +{{ $armorDef }}</div>
                            @endif
                            @if($accDefBonus > 0)
                                <div class="text-blue-400">{{ $accessory['name'] }}: +{{ $accDefBonus }}</div>
                            @endif
                            <div class="border-t border-gray-600 mt-1 pt-1 text-white">Total: {{ $player->getTotalDefense() }}</div>
                        </div>
                    </div>
                    <div class="tooltip cursor-help">
                        <span class="text-purple-400 font-bold">{{ $player->getCritChance() }}%</span>
                        <span class="text-gray-500">CRT</span>
                        <div class="tooltip-content text-xs text-left">
                            <div class="font-bold text-white mb-1">Crit Chance Breakdown</div>
                            <div class="text-gray-400">Base: {{ $baseCrit }}%</div>
                            @if($weaponCrit > 0)
                                <div class="text-purple-400">{{ $weapon['name'] }}: +{{ $weaponCrit }}%</div>
                            @endif
                            @if($armorCrit > 0)
                                <div class="text-purple-400">{{ $armor['name'] }}: +{{ $armorCrit }}%</div>
                            @endif
                            @if($accCrit > 0)
                                <div class="text-purple-400">{{ $accessory['name'] }}: +{{ $accCrit }}%</div>
                            @endif
                            <div class="border-t border-gray-600 mt-1 pt-1 text-white">Total: {{ $player->getCritChance() }}%</div>
                        </div>
                    </div>
                </div>

                <!-- Resources Row -->
                <div class="px-2 py-1 border-b border-gray-700">
                    <div class="flex justify-center">
                        <span><span class="text-yellow-400">{{ $player->gold }}</span> <span class="text-gray-500">gold</span></span>
                    </div>
                </div>

                <!-- Equipment (icon grid with tooltips) -->
                <div class="p-2 border-b border-gray-700">
                    <div class="text-gray-500 mb-1">Equipment</div>
                    @php $equipment = $player->getEquipment(); @endphp
                    <div class="grid grid-cols-3 gap-1">
                        @foreach(['weapon', 'armor', 'accessory'] as $slot)
                            @php
                                $slotIcon = match($slot) {
                                    'weapon' => '&#x2694;',
                                    'armor' => '&#x1F6E1;',
                                    'accessory' => '&#x1F48D;',
                                    default => '&#x1F4E6;'
                                };
                                $item = $equipment[$slot] ?? null;
                                $eqRarity = $item ? ($item['rarity'] ?? 'common') : null;
                                $eqBorder = $item ? match($eqRarity) {
                                    'legendary' => 'border-yellow-500',
                                    'epic' => 'border-purple-500',
                                    'rare' => 'border-blue-500',
                                    'uncommon' => 'border-green-500',
                                    default => 'border-gray-600'
                                } : 'border-gray-700 border-dashed';
                                $eqGlow = $item ? 'rarity-glow-' . $eqRarity : 'bg-transparent';
                            @endphp
                            <div class="tooltip">
                                <div class="aspect-square flex items-center justify-center rounded border {{ $eqBorder }} {{ $eqGlow }} {{ $item ? 'cursor-pointer hover:brightness-125' : 'text-gray-600' }} overflow-hidden">
                                    @if($item && isset($item['icon']))
                                        {!! icon($item['icon']) !!}
                                    @else
                                        <span class="text-xl">{!! $slotIcon !!}</span>
                                    @endif
                                </div>
                                <div class="tooltip-content text-xs" style="min-width: 180px;">
                                    @if($item)
                                        <div class="font-bold text-white mb-1">{{ $item['name'] }}</div>
                                        <div class="text-gray-400 mb-2">{{ ucfirst($slot) }} - {{ ucfirst($item['rarity'] ?? 'common') }}</div>
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
                                        @if(isset($match) && $player->hasInventorySpace() && $player->current_hp > 0 && $match->state !== 'finished')
                                            <div class="border-t border-gray-600 mt-2 pt-2">
                                                <form action="{{ route('match.unequip', [$match->id]) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="slot" value="{{ $slot }}">
                                                    <button type="submit" class="bg-red-600 hover:bg-red-500 px-2 py-1 rounded text-white w-full">Unequip</button>
                                                </form>
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-gray-500">No {{ $slot }} equipped</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Inventory (icon grid with tooltips) -->
                @php $inShop = isset($room) && $room->isShop(); @endphp
                <div class="p-2">
                    <div class="text-gray-500 mb-1">
                        Bag ({{ $player->getInventoryCount() }}/{{ $player::MAX_INVENTORY_SLOTS }})
                        @if($inShop)
                            <span class="text-green-400 text-xs ml-1">- Shop</span>
                        @endif
                    </div>
                    @if(!$player->hasInventorySpace())
                        <div class="text-yellow-500 text-xs mb-1">Bag full - unequip disabled</div>
                    @endif
                    <div class="grid grid-cols-4 gap-1">
                        @foreach($player->getInventory() as $index => $item)
                            @php
                                $fallbackIcon = match($item['type']) {
                                    'weapon' => '&#x2694;',
                                    'armor' => '&#x1F6E1;',
                                    'accessory' => '&#x1F48D;',
                                    'consumable' => '&#x1F9EA;',
                                    default => '&#x1F4E6;'
                                };
                                $itemRarity = $item['rarity'] ?? 'common';
                                $rarityBorder = match($itemRarity) {
                                    'legendary' => 'border-yellow-500',
                                    'epic' => 'border-purple-500',
                                    'rare' => 'border-blue-500',
                                    'uncommon' => 'border-green-500',
                                    default => 'border-gray-600'
                                };
                                $rarityGlow = 'rarity-glow-' . $itemRarity;
                                $sellPrice = (int) floor(($item['shop_price'] ?? 10) * 0.5);
                            @endphp
                            <div class="tooltip relative">
                                <div class="aspect-square flex items-center justify-center rounded border {{ $rarityBorder }} {{ $rarityGlow }} cursor-pointer hover:brightness-125 overflow-hidden">
                                    @if(isset($item['icon']))
                                        {!! icon($item['icon']) !!}
                                    @else
                                        <span class="text-xl">{!! $fallbackIcon !!}</span>
                                    @endif
                                </div>
                                <span class="absolute -bottom-0.5 -right-0.5 bg-yellow-700/90 text-yellow-200 text-[8px] px-0.5 rounded leading-tight z-10">{{ $sellPrice }}g</span>
                                <div class="tooltip-content text-xs" style="min-width: 180px;">
                                    <div class="font-bold text-white mb-1">{{ $item['name'] }}</div>
                                    <div class="text-gray-400 mb-2">{{ ucfirst($item['type']) }} - {{ ucfirst($item['rarity'] ?? 'common') }}</div>
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
                                        <div class="text-green-400">Heals {{ $item['heal'] }} HP</div>
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
                                    <div class="text-yellow-400 mt-1">Sell: {{ $sellPrice }}g</div>
                                    @if(isset($match))
                                        <div class="border-t border-gray-600 mt-2 pt-2 flex flex-wrap gap-1">
                                            @if(in_array($item['type'], ['weapon', 'armor', 'accessory']) && $player->current_hp > 0 && $match->state !== 'finished')
                                                <form action="{{ route('match.equip', [$match->id]) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="item_index" value="{{ $index }}">
                                                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 px-2 py-1 rounded text-white">Equip</button>
                                                </form>
                                            @endif
                                            @if($item['type'] === 'consumable' && isset($item['heal']) && $player->current_hp > 0 && $match->state !== 'finished')
                                                <form action="{{ route('match.use_item', [$match->id]) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="item_index" value="{{ $index }}">
                                                    <button type="submit" class="bg-green-600 hover:bg-green-500 px-2 py-1 rounded text-white">Use</button>
                                                </form>
                                            @endif
                                            @if($inShop)
                                                <form action="{{ route('match.sell_item', [$match->id]) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="item_index" value="{{ $index }}">
                                                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-500 px-2 py-1 rounded text-white">Sell +{{ $sellPrice }}g</button>
                                                </form>
                                            @endif
                                            <form action="{{ route('match.drop_item', [$match->id]) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="item_index" value="{{ $index }}">
                                                <button type="submit" class="bg-gray-600 hover:bg-gray-500 px-2 py-1 rounded text-white">Drop</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @for($i = $player->getInventoryCount(); $i < $player::MAX_INVENTORY_SLOTS; $i++)
                            <div class="aspect-square flex items-center justify-center rounded border border-gray-700 border-dashed text-gray-600">
                            </div>
                        @endfor
                    </div>
                </div>

                {{-- Selected Cards Section --}}
                @php
                    $selectedCardIds = $player->getCards();
                    $contentRepo = app(\App\Game\Content\ContentRepository::class);
                    $cardIcons = [
                        'deception' => '🎭',
                        'difficulty' => '💀',
                        'utility' => '✨',
                    ];
                @endphp
                @if(count($selectedCardIds) > 0)
                    <div class="p-2 border-t border-gray-700" x-data="{ showCards: false }">
                        <button @click="showCards = !showCards" class="w-full flex justify-between items-center text-gray-500 hover:text-gray-300 transition">
                            <span>Cards ({{ count($selectedCardIds) }})</span>
                            <span x-text="showCards ? '▼' : '▶'" class="text-[10px]"></span>
                        </button>
                        <div x-show="showCards" x-collapse class="mt-2 space-y-1">
                            @foreach($selectedCardIds as $cardId)
                                @php $card = $contentRepo->getCard($cardId); @endphp
                                @if($card)
                                    @php
                                        $cardTypeColor = match($card['type']) {
                                            'deception' => 'border-purple-500/50 bg-purple-900/20',
                                            'difficulty' => 'border-red-500/50 bg-red-900/20',
                                            'utility' => 'border-green-500/50 bg-green-900/20',
                                            default => 'border-gray-600 bg-gray-800'
                                        };
                                    @endphp
                                    <div class="tooltip-shop">
                                        <div class="flex items-center gap-1 p-1 rounded border {{ $cardTypeColor }} text-xs">
                                            <span>{{ $cardIcons[$card['type']] ?? '🃏' }}</span>
                                            <span class="truncate">{{ $card['name'] }}</span>
                                        </div>
                                        <div class="tooltip-shop-content text-left" style="min-width: 180px;">
                                            <div class="font-bold text-white">{{ $card['name'] }}</div>
                                            <div class="text-gray-400 text-xs mb-1">{{ ucfirst($card['type']) }} - {{ $card['target'] === 'opponent' ? 'vs Opponent' : 'For You' }}</div>
                                            <div class="text-gray-300 text-xs">{{ $card['description'] }}</div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <div class="p-2 text-gray-500">No player data</div>
            @endif
        </div>

        <!-- Right Side - Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden relative">
            <!-- Toast Notifications (fixed overlay) -->
            <div class="absolute top-2 right-2 z-50 flex flex-col gap-2 max-w-sm">
                @if(session('error'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-x-4"
                         x-transition:enter-end="opacity-100 translate-x-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-x-0"
                         x-transition:leave-end="opacity-0 translate-x-4"
                         class="bg-red-600 text-white px-4 py-2 rounded shadow-lg text-sm flex items-center gap-2">
                        <span class="flex-1">{{ session('error') }}</span>
                        <button @click="show = false" class="text-white/80 hover:text-white">&times;</button>
                    </div>
                @endif
                @if(session('message'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-x-4"
                         x-transition:enter-end="opacity-100 translate-x-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-x-0"
                         x-transition:leave-end="opacity-0 translate-x-4"
                         class="bg-blue-600 text-white px-4 py-2 rounded shadow-lg text-sm flex items-center gap-2">
                        <span class="flex-1">{{ session('message') }}</span>
                        <button @click="show = false" class="text-white/80 hover:text-white">&times;</button>
                    </div>
                @endif
                @if(session('combat_log'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-x-4"
                         x-transition:enter-end="opacity-100 translate-x-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-x-0"
                         x-transition:leave-end="opacity-0 translate-x-4"
                         class="bg-gray-800 border border-gray-600 p-3 rounded shadow-lg text-xs max-h-32 overflow-y-auto flex flex-col gap-1">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-gray-400 font-bold">Combat Log</span>
                            <button @click="show = false" class="text-gray-500 hover:text-white">&times;</button>
                        </div>
                        @foreach(session('combat_log') as $log)
                            <p class="{{ $log['type'] === 'damage_dealt' ? 'text-green-400' : ($log['type'] === 'damage_taken' ? 'text-red-400' : 'text-gray-300') }}">
                                {{ $log['message'] }}
                            </p>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Main Game Content -->
            <div class="flex-1 overflow-y-auto p-4">
                @yield('game-content')
            </div>
        </div>
    </div>
</body>
</html>
