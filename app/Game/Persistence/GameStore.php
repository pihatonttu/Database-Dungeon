<?php

namespace App\Game\Persistence;

use App\Models\GameEvent;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class GameStore
{
    /**
     * Record a card selection event
     */
    public function recordCardSelection(Player $player, array $cards): void
    {
        GameEvent::record($player, GameEvent::ACTION_CARD_SELECTED, [
            'cards' => $cards,
        ]);

        $player->cards_json = $cards;
        $player->setup_complete = true;
        $player->save();
    }

    /**
     * Record entering a room
     */
    public function recordRoomEntered(Player $player, Room $room): void
    {
        GameEvent::record($player, GameEvent::ACTION_ROOM_ENTERED, [
            'room_id' => $room->id,
            'level' => $room->level,
            'position' => $room->position,
            'displayed_type' => $room->displayed_type,
            'actual_type' => $room->actual_type,
        ]);

        $room->markVisited();
        $player->current_level = $room->level;
        $player->save();
    }

    /**
     * Record combat result
     */
    public function recordCombat(Player $player, Room $room, array $combatResult): void
    {
        DB::transaction(function () use ($player, $room, $combatResult) {
            GameEvent::record($player, GameEvent::ACTION_COMBAT, [
                'room_id' => $room->id,
                'enemy_name' => $combatResult['enemy_name'],
                'success' => $combatResult['success'],
                'damage_taken' => $combatResult['damage_taken'],
                'rounds' => $combatResult['rounds'],
                'rewards' => $combatResult['rewards'],
            ]);

            // Apply damage
            $player->takeDamage($combatResult['damage_taken']);

            // Apply rewards if successful
            if ($combatResult['success']) {
                $rewards = $combatResult['rewards'];
                $player->addGold($rewards['gold'] ?? 0);
                $player->addXp($rewards['xp'] ?? 0);
                $room->markCompleted();
            }
        });
    }

    /**
     * Record loot collection
     */
    public function recordLootCollected(Player $player, Room $room, array $item): void
    {
        DB::transaction(function () use ($player, $room, $item) {
            GameEvent::record($player, GameEvent::ACTION_LOOT_COLLECTED, [
                'room_id' => $room->id,
                'item' => $item,
            ]);

            $player->addItem($item);
            $room->markCompleted();
        });
    }

    /**
     * Record shop purchase
     */
    public function recordShopPurchase(Player $player, Room $room, array $item, int $price): void
    {
        DB::transaction(function () use ($player, $room, $item, $price) {
            GameEvent::record($player, GameEvent::ACTION_SHOP_PURCHASE, [
                'room_id' => $room->id,
                'item' => $item,
                'price' => $price,
            ]);

            $player->gold -= $price;
            $player->addItem($item);
            $player->save();
        });
    }

    /**
     * Record dungeon completion
     */
    public function recordDungeonCompleted(Player $player): void
    {
        GameEvent::record($player, GameEvent::ACTION_DUNGEON_COMPLETED, [
            'final_hp' => $player->current_hp,
            'final_gold' => $player->gold,
            'final_xp' => $player->xp,
            'loot_count' => count($player->getLoot()),
        ]);

        $player->dungeon_completed_at = now();
        $player->save();
    }

    /**
     * Record PvP result and update match
     */
    public function recordPvpResult(GameMatch $match, array $pvpResult): void
    {
        DB::transaction(function () use ($match, $pvpResult) {
            $winner = Player::find($pvpResult['winner_id']);

            GameEvent::record($winner, GameEvent::ACTION_PVP_RESULT, $pvpResult);

            $match->winner_player_id = $pvpResult['winner_id'];
            $match->state = GameMatch::STATE_FINISHED;
            $match->save();
        });
    }

    /**
     * Transition match to next state
     */
    public function transitionMatchState(GameMatch $match, string $newState): void
    {
        $match->state = $newState;
        $match->save();
    }

    /**
     * Check if all players have completed setup
     */
    public function allPlayersSetupComplete(GameMatch $match): bool
    {
        return $match->players()->where('setup_complete', false)->count() === 0;
    }

    /**
     * Check if all players have completed their dungeon
     */
    public function allPlayersCompletedDungeon(GameMatch $match): bool
    {
        return $match->players()->whereNull('dungeon_completed_at')->count() === 0;
    }
}
