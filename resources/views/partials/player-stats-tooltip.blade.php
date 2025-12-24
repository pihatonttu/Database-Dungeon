{{-- Player Stats Tooltip Content --}}
{{-- Usage: @include('partials.player-stats-tooltip', ['tooltipPlayer' => $player, 'showEquipment' => true]) --}}
@php
    $p = $tooltipPlayer;
    $eq = $p->getEquipment();
    $weapon = $eq['weapon'] ?? null;
    $armor = $eq['armor'] ?? null;
    $accessory = $eq['accessory'] ?? null;

    $minDmg = $p->getTotalAttack() - $p->getAttackVariance();
    $maxDmg = $p->getTotalAttack() + $p->getAttackVariance();
@endphp

<div class="text-xs">
    <!-- Stats -->
    <div class="grid grid-cols-3 gap-2 mb-3 text-center">
        <div>
            <div class="text-orange-400 font-bold">{{ $minDmg }}-{{ $maxDmg }}</div>
            <div class="text-gray-500 text-[10px]">DMG</div>
        </div>
        <div>
            <div class="text-blue-400 font-bold">{{ $p->getTotalDefense() }}</div>
            <div class="text-gray-500 text-[10px]">DEF</div>
        </div>
        <div>
            <div class="text-purple-400 font-bold">{{ $p->getCritChance() }}%</div>
            <div class="text-gray-500 text-[10px]">CRIT</div>
        </div>
    </div>

    <!-- HP Bar -->
    @php $hpPct = ($p->current_hp / $p->getMaxHp()) * 100; @endphp
    <div class="mb-3">
        <div class="flex justify-between text-[10px] mb-0.5">
            <span class="text-gray-400">HP</span>
            <span>{{ $p->current_hp }}/{{ $p->getMaxHp() }}</span>
        </div>
        <div class="h-2 bg-gray-700 rounded-full">
            <div class="h-full {{ $hpPct > 50 ? 'bg-green-500' : ($hpPct > 25 ? 'bg-yellow-500' : 'bg-red-500') }} rounded-full" style="width: {{ $hpPct }}%"></div>
        </div>
    </div>

    @if($showEquipment ?? true)
        <!-- Equipment -->
        <div class="border-t border-gray-600 pt-2">
            <div class="text-gray-500 text-[10px] mb-1">Equipment</div>
            <div class="grid grid-cols-3 gap-1">
                @foreach(['weapon', 'armor', 'accessory'] as $slot)
                    @php
                        $item = $eq[$slot] ?? null;
                        $rarityColor = $item ? match($item['rarity'] ?? 'common') {
                            'legendary' => 'border-yellow-500 bg-yellow-900/30',
                            'epic' => 'border-purple-500 bg-purple-900/30',
                            'rare' => 'border-blue-500 bg-blue-900/30',
                            'uncommon' => 'border-green-500 bg-green-900/30',
                            default => 'border-gray-600 bg-gray-700'
                        } : 'border-gray-700 border-dashed bg-transparent';
                        $slotIcon = match($slot) {
                            'weapon' => '&#x2694;',
                            'armor' => '&#x1F6E1;',
                            'accessory' => '&#x1F48D;',
                            default => '&#x1F4E6;'
                        };
                    @endphp
                    <div class="tooltip-shop">
                        <div class="aspect-square flex items-center justify-center rounded border {{ $rarityColor }} {{ $item ? '' : 'text-gray-600' }} overflow-hidden">
                            @if($item && isset($item['icon']))
                                {!! icon($item['icon']) !!}
                            @else
                                <span class="text-lg">{!! $slotIcon !!}</span>
                            @endif
                        </div>
                        @if($item)
                            <div class="tooltip-shop-content">
                                <div class="font-bold text-white">{{ $item['name'] }}</div>
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
    @endif
</div>
