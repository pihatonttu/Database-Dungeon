<?php

namespace App\Game\Dungeon;

use App\Game\Content\ContentRepository;
use App\Models\Dungeon;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class DungeonGenerator
{
    private ContentRepository $content;

    public function __construct(ContentRepository $content)
    {
        $this->content = $content;
    }

    /**
     * Generate dungeons for both players based on their card selections
     */
    public function generateForMatch(GameMatch $match): void
    {
        $players = $match->players()->get();

        if ($players->count() !== 2) {
            throw new \RuntimeException('Match must have exactly 2 players');
        }

        $playerA = $players[0];
        $playerB = $players[1];

        // Player A's cards create Player B's dungeon
        $this->generateDungeon($match, $playerA, $playerB);

        // Player B's cards create Player A's dungeon
        $this->generateDungeon($match, $playerB, $playerA);
    }

    /**
     * Generate a dungeon for target player based on owner's cards
     */
    public function generateDungeon(GameMatch $match, Player $owner, Player $target): Dungeon
    {
        $seed = random_int(1, 999999);

        return DB::transaction(function () use ($match, $owner, $target, $seed) {
            $dungeon = Dungeon::create([
                'match_id' => $match->id,
                'owner_player_id' => $owner->id,
                'target_player_id' => $target->id,
                'seed' => $seed,
                'modifiers_json' => $owner->getCards(),
            ]);

            // Seed the random generator for deterministic generation
            srand($seed);

            $this->generateRooms($dungeon, $owner->getCards());

            // Reset random seed
            srand();

            return $dungeon;
        });
    }

    private function generateRooms(Dungeon $dungeon, array $cards): void
    {
        $rules = $this->content->getRules();
        $totalLevels = $rules['dungeon']['total_levels'];
        $choiceLevels = $rules['dungeon']['choice_levels'];
        $shopLevels = $rules['dungeon']['shop_levels'] ?? [];

        // Calculate modifiers from cards for generation
        $modifiers = $this->calculateGenerationModifiers($cards);

        $rooms = [];

        for ($level = 1; $level <= $totalLevels; $level++) {
            if (in_array($level, $choiceLevels)) {
                // Choice level - two options (one can be shop)
                if (in_array($level, $shopLevels)) {
                    // Shop on choice level - shop on one side, something else on other
                    $otherType = $this->rollRoomType('choice_level');
                    if (rand(0, 1)) {
                        $rooms[] = $this->createRoom($dungeon, $level, Room::POSITION_LEFT, Room::TYPE_SHOP, Room::TYPE_SHOP, $modifiers);
                        $rooms[] = $this->createRoom($dungeon, $level, Room::POSITION_RIGHT, $otherType, $otherType, $modifiers);
                    } else {
                        $rooms[] = $this->createRoom($dungeon, $level, Room::POSITION_LEFT, $otherType, $otherType, $modifiers);
                        $rooms[] = $this->createRoom($dungeon, $level, Room::POSITION_RIGHT, Room::TYPE_SHOP, Room::TYPE_SHOP, $modifiers);
                    }
                } else {
                    $leftType = $this->rollRoomType('choice_level');
                    $rightType = $this->rollRoomType('choice_level');

                    // Ensure at least one is different from the other
                    if ($leftType === $rightType && rand(0, 1)) {
                        $rightType = $this->getAlternativeType($leftType);
                    }

                    $rooms[] = $this->createRoom($dungeon, $level, Room::POSITION_LEFT, $leftType, $leftType, $modifiers);
                    $rooms[] = $this->createRoom($dungeon, $level, Room::POSITION_RIGHT, $rightType, $rightType, $modifiers);
                }
            } else {
                // Mandatory level - single room
                $type = in_array($level, $shopLevels) ? Room::TYPE_SHOP : $this->rollRoomType('mandatory_level');
                $rooms[] = $this->createRoom($dungeon, $level, Room::POSITION_CENTER, $type, $type, $modifiers);
            }
        }

        // Apply card effects that modify rooms
        $this->applyCardEffects($dungeon, $cards, $rooms);
    }

    /**
     * Calculate generation modifiers from cards
     */
    private function calculateGenerationModifiers(array $cardIds): array
    {
        $modifiers = [
            'shop_item_bonus' => 0,
            'shop_rarity_bonus' => 0,
            'loot_rarity_bonus' => 0,
        ];

        foreach ($cardIds as $cardId) {
            $card = $this->content->getCard($cardId);
            if (!$card) continue;

            $effect = $card['effect'] ?? [];
            $action = $effect['action'] ?? '';

            switch ($action) {
                case 'modify_shop_items':
                    $modifiers['shop_item_bonus'] += $effect['amount'] ?? 0;
                    break;
                case 'upgrade_shop_rarity':
                    $modifiers['shop_rarity_bonus'] += $effect['amount'] ?? 0;
                    break;
                case 'downgrade_shop_rarity':
                    $modifiers['shop_rarity_bonus'] -= $effect['amount'] ?? 0;
                    break;
                case 'upgrade_loot_rarity':
                    $modifiers['loot_rarity_bonus'] += $effect['amount'] ?? 0;
                    break;
                case 'downgrade_loot_rarity':
                    $modifiers['loot_rarity_bonus'] -= $effect['amount'] ?? 0;
                    break;
            }
        }

        return $modifiers;
    }

    private function createRoom(Dungeon $dungeon, int $level, string $position, string $displayedType, string $actualType, array $modifiers = []): Room
    {
        // Resolve unknown rooms to their actual type
        $resolvedType = $actualType;
        if ($actualType === Room::TYPE_UNKNOWN) {
            $resolvedType = rand(0, 1) ? Room::TYPE_LOOT : Room::TYPE_ENEMY;
        }

        $content = $this->generateRoomContent($resolvedType, $level, $modifiers);

        return Room::create([
            'dungeon_id' => $dungeon->id,
            'level' => $level,
            'position' => $position,
            'displayed_type' => $displayedType, // Still shows as "unknown" on map
            'actual_type' => $resolvedType,     // But actual type is enemy/loot
            'content_json' => $content,
        ]);
    }

    private function rollRoomType(string $distribution): string
    {
        $rules = $this->content->getRule("room_distribution.{$distribution}");

        $total = array_sum($rules);
        $roll = rand(1, $total);

        $cumulative = 0;
        foreach ($rules as $type => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $type === 'unknown' ? Room::TYPE_UNKNOWN : $type;
            }
        }

        return Room::TYPE_ENEMY;
    }

    private function getAlternativeType(string $currentType): string
    {
        $types = [Room::TYPE_ENEMY, Room::TYPE_LOOT, Room::TYPE_UNKNOWN];
        $alternatives = array_filter($types, fn($t) => $t !== $currentType);
        return $alternatives[array_rand($alternatives)];
    }

    private function generateRoomContent(string $type, int $level = 1, array $modifiers = []): array
    {
        $totalLevels = $this->content->getRule('dungeon.total_levels') ?? 10;

        switch ($type) {
            case Room::TYPE_ENEMY:
                // Scale enemy tier based on level
                $tier = 'common';
                if ($level >= $totalLevels * 0.7) {
                    $tier = rand(0, 1) ? 'uncommon' : 'common';
                }
                $enemy = $this->content->getRandomEnemy($tier);
                return ['enemy' => $enemy];

            case Room::TYPE_ELITE:
                $enemy = $this->content->getRandomEnemy('elite');
                return ['enemy' => $enemy];

            case Room::TYPE_BOSS:
                $enemy = $this->content->getRandomEnemy('boss');
                return ['enemy' => $enemy];

            case Room::TYPE_LOOT:
                // Scale loot rarity based on level + card modifiers
                $lootTable = $this->getLootTableForLevel($level, $totalLevels, $modifiers['loot_rarity_bonus'] ?? 0);
                $item = $this->content->rollLoot($lootTable);
                return ['loot' => $item];

            case Room::TYPE_SHOP:
                return $this->generateShopInventory($level, $totalLevels, $modifiers);

            case Room::TYPE_UNKNOWN:
                // Unknown rooms should be resolved in createRoom(), but fallback here
                return [];

            case Room::TYPE_EMPTY:
                return [];

            default:
                return [];
        }
    }

    private function getLootTableForLevel(int $level, int $totalLevels, int $rarityBonus = 0): string
    {
        $progress = $level / $totalLevels;

        // Rarity tiers: common=0, uncommon=1, rare=2, epic=3
        $baseTier = 0;

        if ($progress >= 0.8) {
            // Last 20% - rare/epic base
            $baseTier = rand(0, 100) < 30 ? 3 : 2;
        } elseif ($progress >= 0.5) {
            // Middle 30% - uncommon/rare
            $baseTier = rand(0, 100) < 40 ? 2 : 1;
        } elseif ($progress >= 0.3) {
            // Early-mid - common/uncommon
            $baseTier = rand(0, 100) < 40 ? 1 : 0;
        }

        // Apply rarity bonus from cards (can go negative for bad_luck)
        $finalTier = max(0, min(3, $baseTier + $rarityBonus));

        $tables = ['loot_room_common', 'loot_room_uncommon', 'loot_room_rare', 'loot_room_epic'];
        return $tables[$finalTier];
    }

    private function generateShopInventory(int $level = 1, int $totalLevels = 10, array $modifiers = []): array
    {
        $inventory = [];
        $progress = $level / $totalLevels;

        // Apply shop item bonus from cards (merchant_favor, sparse_shelves)
        $shopItemBonus = $modifiers['shop_item_bonus'] ?? 0;
        $shopRarityBonus = $modifiers['shop_rarity_bonus'] ?? 0;

        // Base 3-4 items + card bonus (minimum 1 item)
        $itemCount = max(1, rand(3, 4) + $shopItemBonus);

        for ($i = 0; $i < $itemCount; $i++) {
            // Base rarity tier: 0=common, 1=uncommon, 2=rare
            $baseTier = 0;
            if ($progress >= 0.7) {
                $baseTier = rand(0, 10) < 4 ? 2 : (rand(0, 10) < 6 ? 1 : 0);
            } elseif ($progress >= 0.4) {
                $baseTier = rand(0, 10) < 5 ? 1 : 0;
            }

            // Apply shop rarity bonus from cards (premium_stock, junk_dealer)
            $finalTier = max(0, min(2, $baseTier + $shopRarityBonus));
            $rarities = ['common', 'uncommon', 'rare'];
            $rarity = $rarities[$finalTier];

            $items = array_filter($this->content->getItems(), fn($item) => $item['rarity'] === $rarity && $item['type'] !== 'consumable');
            if (!empty($items)) {
                $inventory[] = array_values($items)[array_rand(array_values($items))];
            }
        }

        // Always add a health potion (better potion later)
        if ($progress >= 0.6) {
            $inventory[] = $this->content->getItem('greater_health_potion');
        } else {
            $inventory[] = $this->content->getItem('health_potion');
        }

        return ['shop_items' => $inventory];
    }

    private function applyCardEffects(Dungeon $dungeon, array $cardIds, array &$rooms): void
    {
        foreach ($cardIds as $cardId) {
            $card = $this->content->getCard($cardId);
            if (!$card) continue;

            $this->applyCardEffect($dungeon, $card, $rooms);
        }
    }

    private function applyCardEffect(Dungeon $dungeon, array $card, array &$rooms): void
    {
        $effect = $card['effect'] ?? [];
        $action = $effect['action'] ?? '';

        switch ($action) {
            case 'replace_room':
                $this->applyReplaceRoom($rooms, $effect);
                break;

            case 'swap_labels':
                $this->applySwapLabels($rooms);
                break;

            case 'upgrade_enemy':
                $this->applyUpgradeEnemy($rooms, $effect);
                break;

            case 'add_bonus_loot':
                $this->applyBonusLoot($rooms, $effect);
                break;

            case 'enemy_buff':
                $this->applyEnemyBuff($rooms, $effect);
                break;

            // Other effects (room_damage, modify_shop_prices, etc.) are applied at runtime
        }
    }

    private function applyReplaceRoom(array &$rooms, array $effect): void
    {
        $fromType = $effect['from_type'] ?? '';
        $toType = $effect['to_actual_type'] ?? '';
        $count = $effect['count'] ?? 1;

        $eligibleRooms = array_filter($rooms, fn($room) => $room->actual_type === $fromType);

        if (empty($eligibleRooms)) return;

        $selected = array_rand($eligibleRooms, min($count, count($eligibleRooms)));
        if (!is_array($selected)) $selected = [$selected];

        foreach ($selected as $index) {
            $rooms[$index]->actual_type = $toType;
            $rooms[$index]->content_json = $this->generateRoomContent($toType);
            $rooms[$index]->save();
        }
    }

    private function applySwapLabels(array &$rooms): void
    {
        $swappableRooms = array_filter($rooms, fn($room) =>
            !in_array($room->actual_type, [Room::TYPE_BOSS, Room::TYPE_SHOP])
        );

        if (count($swappableRooms) < 2) return;

        $indices = array_rand($swappableRooms, 2);
        $room1 = $swappableRooms[$indices[0]];
        $room2 = $swappableRooms[$indices[1]];

        $temp = $room1->displayed_type;
        $room1->displayed_type = $room2->displayed_type;
        $room2->displayed_type = $temp;

        $room1->save();
        $room2->save();
    }

    private function applyUpgradeEnemy(array &$rooms, array $effect): void
    {
        $count = $effect['count'] ?? 1;

        $enemyRooms = array_filter($rooms, fn($room) => $room->actual_type === Room::TYPE_ENEMY);

        if (empty($enemyRooms)) return;

        $selected = array_rand($enemyRooms, min($count, count($enemyRooms)));
        if (!is_array($selected)) $selected = [$selected];

        foreach ($selected as $index) {
            $enemyRooms[$index]->actual_type = Room::TYPE_ELITE;
            $enemyRooms[$index]->displayed_type = Room::TYPE_ELITE;
            $enemyRooms[$index]->content_json = $this->generateRoomContent(Room::TYPE_ELITE);
            $enemyRooms[$index]->save();
        }
    }

    private function applyBonusLoot(array &$rooms, array $effect): void
    {
        $roomType = $effect['room_type'] ?? Room::TYPE_ENEMY;
        $count = $effect['count'] ?? 1;

        $eligibleRooms = array_filter($rooms, fn($room) => $room->actual_type === $roomType);

        if (empty($eligibleRooms)) return;

        $selected = array_rand($eligibleRooms, min($count, count($eligibleRooms)));
        if (!is_array($selected)) $selected = [$selected];

        foreach ($selected as $index) {
            $content = $eligibleRooms[$index]->content_json ?? [];
            $content['bonus_loot'] = $this->content->rollLoot('enemy_bonus');
            $eligibleRooms[$index]->content_json = $content;
            $eligibleRooms[$index]->save();
        }
    }

    private function applyEnemyBuff(array &$rooms, array $effect): void
    {
        $count = $effect['count'] ?? 1;
        $damageBonus = $effect['damage_bonus'] ?? 0;

        $enemyRooms = array_filter($rooms, fn($room) => $room->actual_type === Room::TYPE_ENEMY);

        if (empty($enemyRooms)) return;

        $selected = array_rand($enemyRooms, min($count, count($enemyRooms)));
        if (!is_array($selected)) $selected = [$selected];

        foreach ($selected as $index) {
            $content = $enemyRooms[$index]->content_json ?? [];
            $content['enemy_buff'] = ['damage_bonus' => $damageBonus];
            $enemyRooms[$index]->content_json = $content;
            $enemyRooms[$index]->save();
        }
    }
}
