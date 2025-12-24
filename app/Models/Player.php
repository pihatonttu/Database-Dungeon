<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    use HasUuids;

    protected $fillable = [
        'match_id',
        'account_id',
        'name',
        'hero_id',
        'cards_json',
        'available_cards_json',
        'current_level',
        'current_hp',
        'max_hp',
        'gold',
        'xp',
        'attack',
        'attack_variance',
        'defense',
        'crit_chance',
        'base_attack',
        'base_defense',
        'loot_json',
        'equipment_json',
        'inventory_json',
        'setup_complete',
        'dungeon_completed_at',
        'pvp_ready',
    ];

    protected $casts = [
        'cards_json' => 'array',
        'available_cards_json' => 'array',
        'loot_json' => 'array',
        'equipment_json' => 'array',
        'inventory_json' => 'array',
        'setup_complete' => 'boolean',
        'dungeon_completed_at' => 'datetime',
        'pvp_ready' => 'boolean',
    ];

    const MAX_INVENTORY_SLOTS = 4;
    const EQUIPMENT_SLOTS = ['weapon', 'armor', 'accessory'];

    // XP thresholds for each level (cumulative)
    const LEVEL_THRESHOLDS = [
        1 => 0,
        2 => 30,
        3 => 70,
        4 => 120,
        5 => 200,
        6 => 300,
        7 => 450,
        8 => 650,
    ];

    // Stats per level
    const ATTACK_PER_LEVEL = 2;
    const MAX_HP_PER_LEVEL = 10;
    const BASE_MAX_HP = 100;

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // Dungeon this player created (for their opponent to play)
    public function createdDungeon(): HasOne
    {
        return $this->hasOne(Dungeon::class, 'owner_player_id');
    }

    // Dungeon this player plays (created by opponent)
    public function targetDungeon(): HasOne
    {
        return $this->hasOne(Dungeon::class, 'target_player_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class, 'player_id');
    }

    public function getCards(): array
    {
        return $this->cards_json ?? [];
    }

    public function getLoot(): array
    {
        return $this->loot_json ?? [];
    }

    public function hasCompletedDungeon(): bool
    {
        return $this->dungeon_completed_at !== null;
    }

    public function addGold(int $amount): void
    {
        $this->gold += $amount;
        $this->save();
    }

    public function addXp(int $amount): void
    {
        $oldLevel = $this->getLevel();
        $this->xp += $amount;
        $newLevel = $this->getLevel();

        // If leveled up, heal the HP bonus amount
        if ($newLevel > $oldLevel) {
            $hpGain = ($newLevel - $oldLevel) * self::MAX_HP_PER_LEVEL;
            $this->current_hp = min($this->getMaxHp(), $this->current_hp + $hpGain);
        }

        $this->save();
    }

    public function getLevel(): int
    {
        foreach (array_reverse(self::LEVEL_THRESHOLDS, true) as $level => $threshold) {
            if ($this->xp >= $threshold) {
                return $level;
            }
        }
        return 1;
    }

    public function getMaxHp(): int
    {
        // Use stored max_hp from hero, or default to constant
        $baseMaxHp = $this->max_hp ?? self::BASE_MAX_HP;
        $maxHp = $baseMaxHp + (($this->getLevel() - 1) * self::MAX_HP_PER_LEVEL);

        // Add max_hp_bonus from equipment
        foreach (['weapon', 'armor', 'accessory'] as $slot) {
            $item = $this->getEquippedItem($slot);
            if ($item && isset($item['max_hp_bonus'])) {
                $maxHp += $item['max_hp_bonus'];
            }
        }

        return $maxHp;
    }

    public function getXpToNextLevel(): ?int
    {
        $currentLevel = $this->getLevel();
        $nextLevel = $currentLevel + 1;

        if (!isset(self::LEVEL_THRESHOLDS[$nextLevel])) {
            return null; // Max level
        }

        return self::LEVEL_THRESHOLDS[$nextLevel] - $this->xp;
    }

    public function getLevelAttackBonus(): int
    {
        return ($this->getLevel() - 1) * self::ATTACK_PER_LEVEL;
    }

    public function takeDamage(int $amount): void
    {
        $this->current_hp = max(0, $this->current_hp - $amount);
        $this->save();
    }

    public function heal(int $amount): void
    {
        $this->current_hp = min($this->getMaxHp(), $this->current_hp + $amount);
        $this->save();
    }

    public function addLoot(array $item): void
    {
        $loot = $this->getLoot();
        $loot[] = $item;
        $this->loot_json = $loot;
        $this->save();
    }

    // Equipment methods
    public function getEquipment(): array
    {
        return $this->equipment_json ?? ['weapon' => null, 'armor' => null, 'accessory' => null];
    }

    public function getEquippedItem(string $slot): ?array
    {
        return $this->getEquipment()[$slot] ?? null;
    }

    public function equipItem(array $item): bool
    {
        $slot = $item['type'] ?? null;
        if (!in_array($slot, self::EQUIPMENT_SLOTS)) {
            return false;
        }

        $equipment = $this->getEquipment();
        $oldItem = $equipment[$slot] ?? null;

        // Equip new item
        $equipment[$slot] = $item;
        $this->equipment_json = $equipment;

        // If old item exists, add to inventory
        if ($oldItem) {
            $this->addToInventory($oldItem);
        }

        $this->save();
        return true;
    }

    // Inventory methods
    public function getInventory(): array
    {
        return $this->inventory_json ?? [];
    }

    public function getInventoryCount(): int
    {
        return count($this->getInventory());
    }

    public function hasInventorySpace(): bool
    {
        return $this->getInventoryCount() < self::MAX_INVENTORY_SLOTS;
    }

    public function addToInventory(array $item): bool
    {
        if (!$this->hasInventorySpace()) {
            return false;
        }

        $inventory = $this->getInventory();
        $inventory[] = $item;
        $this->inventory_json = $inventory;
        $this->save();
        return true;
    }

    public function removeFromInventory(int $index): ?array
    {
        $inventory = $this->getInventory();
        if (!isset($inventory[$index])) {
            return null;
        }

        $item = $inventory[$index];
        array_splice($inventory, $index, 1);
        $this->inventory_json = array_values($inventory);
        $this->save();
        return $item;
    }

    public function getConsumables(): array
    {
        return array_filter($this->getInventory(), fn($item) => ($item['type'] ?? '') === 'consumable');
    }

    // Combat stats
    public function getTotalAttack(): int
    {
        $attack = $this->base_attack ?? 5;
        $attack += $this->getLevelAttackBonus();

        $weapon = $this->getEquippedItem('weapon');
        $accessory = $this->getEquippedItem('accessory');

        if ($weapon) {
            $attack += $weapon['attack'] ?? 0;
        }
        if ($accessory && isset($accessory['attack_bonus'])) {
            $attack += $accessory['attack_bonus'];
        }

        return $attack;
    }

    public function getAttackVariance(): int
    {
        $weapon = $this->getEquippedItem('weapon');
        return $weapon['attack_variance'] ?? 2;
    }

    public function getTotalDefense(): int
    {
        $defense = $this->base_defense ?? 0;

        // Add defense from armor
        $armor = $this->getEquippedItem('armor');
        if ($armor) {
            $defense += $armor['defense'] ?? 0;
        }

        // Add defense_bonus from accessory
        $accessory = $this->getEquippedItem('accessory');
        if ($accessory && isset($accessory['defense_bonus'])) {
            $defense += $accessory['defense_bonus'];
        }

        return max(0, $defense); // Can't go below 0
    }

    public function getCritChance(): int
    {
        // Use stored crit_chance from hero, or default to 5%
        $baseCrit = $this->crit_chance ?? 5;

        // Add crit from all equipment
        foreach (['weapon', 'armor', 'accessory'] as $slot) {
            $item = $this->getEquippedItem($slot);
            if ($item) {
                if (isset($item['crit_chance'])) {
                    $baseCrit += $item['crit_chance'];
                }
                if (isset($item['crit_bonus'])) {
                    $baseCrit += $item['crit_bonus'];
                }
            }
        }

        return min(75, $baseCrit); // Cap at 75%
    }

    public function rollAttack(): int
    {
        $base = $this->getTotalAttack();
        $variance = $this->getAttackVariance();
        return $base + rand(-$variance, $variance);
    }

    // Add item to either equipment or inventory
    public function addItem(array $item): bool
    {
        $type = $item['type'] ?? '';

        // Consumables go to inventory
        if ($type === 'consumable') {
            return $this->addToInventory($item);
        }

        // Equipment: equip if slot empty, otherwise add to inventory
        if (in_array($type, self::EQUIPMENT_SLOTS)) {
            $currentEquipped = $this->getEquippedItem($type);
            if (!$currentEquipped) {
                return $this->equipItem($item);
            }
            return $this->addToInventory($item);
        }

        // Unknown type, try inventory
        return $this->addToInventory($item);
    }
}
