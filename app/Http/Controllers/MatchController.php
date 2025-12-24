<?php

namespace App\Http\Controllers;

use App\Game\Content\ContentRepository;
use App\Game\Dungeon\DungeonGenerator;
use App\Game\Engine\CombatSimulator;
use App\Game\Engine\PvpSimulator;
use App\Game\Persistence\GameStore;
use App\Models\Account;
use App\Models\CombatState;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PvpBattle;
use App\Models\Room;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    private ContentRepository $content;
    private GameStore $gameStore;

    public function __construct()
    {
        $this->content = new ContentRepository();
        $this->gameStore = new GameStore();
    }

    /**
     * Home page - create or join match
     */
    public function home()
    {
        return view('home');
    }

    /**
     * Create a new match
     */
    public function create(Request $request)
    {
        // Require logged in account
        $account = $this->getAccount();
        if (!$account) {
            return redirect()->route('login')->with('error', 'Please login first');
        }

        // Create match
        $match = GameMatch::create([
            'content_version' => $this->content->getVersion(),
            'state' => GameMatch::STATE_LOBBY,
            'is_public' => $request->boolean('is_public', true),
        ]);

        // Create player for match creator (no hero yet)
        $player = Player::create([
            'match_id' => $match->id,
            'account_id' => $account->id,
            'name' => $account->display_name,
            'current_hp' => 100, // Temporary, will be set by hero
            'gold' => 0,
            'xp' => 0,
        ]);

        // Store player ID in session
        session(['player_id' => $player->id]);

        return redirect()->route('match.lobby', $match->id);
    }

    /**
     * Join an existing match
     */
    public function join(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);

        if (!$match->isLobby()) {
            return redirect()->route('dashboard')->with('error', 'Match has already started');
        }

        if ($match->isFull()) {
            return redirect()->route('dashboard')->with('error', 'Match is full');
        }

        // Require logged in account
        $account = $this->getAccount();
        if (!$account) {
            return redirect()->route('login')->with('error', 'Please login first');
        }

        // Check if this account already has a player in this match
        $existingPlayer = $match->players()->where('account_id', $account->id)->first();
        if ($existingPlayer) {
            session(['player_id' => $existingPlayer->id]);
            return redirect()->route('match.lobby', $match->id);
        }

        // Create player for joiner (no hero yet)
        $player = Player::create([
            'match_id' => $match->id,
            'account_id' => $account->id,
            'name' => $request->input('name', $account->display_name),
            'current_hp' => 100, // Temporary, will be set by hero
            'gold' => 0,
            'xp' => 0,
        ]);

        session(['player_id' => $player->id]);

        // If match is now full, transition to setup
        if ($match->isFull()) {
            $this->gameStore->transitionMatchState($match, GameMatch::STATE_SETUP);
        }

        return redirect()->route('match.lobby', $match->id);
    }

    /**
     * Show lobby (waiting for players)
     */
    public function lobby(string $matchId)
    {
        $match = GameMatch::with('players')->findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);

        if ($match->isSetup() || $match->isRunning()) {
            return redirect()->route('match.setup', $match->id);
        }

        return view('lobby', [
            'match' => $match,
            'player' => $currentPlayer,
            'shareUrl' => route('match.join.form', $match->id),
        ]);
    }

    /**
     * Show join form for shared link
     */
    public function joinForm(string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);

        // Check if user already has a player in this match
        $playerId = session('player_id');
        if ($playerId) {
            $existingPlayer = Player::where('id', $playerId)->where('match_id', $match->id)->first();
            if ($existingPlayer) {
                return redirect()->route('match.lobby', $match->id);
            }
        }

        if (!$match->isLobby()) {
            return redirect()->route('dashboard')->with('error', 'Match has already started');
        }

        return view('join', ['match' => $match]);
    }

    /**
     * Show hero selection
     */
    public function heroSelect(string $matchId)
    {
        $match = GameMatch::with('players')->findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        // If already selected hero, go to setup
        if ($currentPlayer->hero_id) {
            return redirect()->route('match.setup', $match->id);
        }

        $heroes = $this->content->getHeroes();

        // Get weapon data for each hero
        $weapons = [];
        foreach ($heroes as $hero) {
            $weaponId = $hero['starting_weapon'] ?? 'rusty_sword';
            $weapons[$weaponId] = $this->content->getItem($weaponId);
        }

        return view('hero-select', [
            'match' => $match,
            'player' => $currentPlayer,
            'heroes' => $heroes,
            'weapons' => $weapons,
        ]);
    }

    /**
     * Submit hero selection
     */
    public function submitHeroSelect(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        $heroId = $request->input('hero_id');
        $hero = $this->content->getHero($heroId);

        if (!$hero) {
            return back()->with('error', 'Invalid hero selection');
        }

        // Apply hero stats
        $stats = $hero['stats'] ?? [];
        $currentPlayer->hero_id = $heroId;
        $currentPlayer->current_hp = $stats['base_hp'] ?? 100;
        $currentPlayer->max_hp = $stats['base_hp'] ?? 100;
        $currentPlayer->gold = $stats['base_gold'] ?? 10;
        $currentPlayer->crit_chance = $stats['base_crit'] ?? 5;

        // Generate available cards for this hero
        $availableCards = $this->content->generateAvailableCards($heroId);
        $currentPlayer->available_cards_json = $availableCards;

        // Give starting equipment based on hero
        $this->giveHeroStartingEquipment($currentPlayer, $hero);

        $currentPlayer->save();

        return redirect()->route('match.setup', $match->id);
    }

    /**
     * Give starting equipment based on hero
     */
    private function giveHeroStartingEquipment(Player $player, array $hero): void
    {
        $weaponId = $hero['starting_weapon'] ?? 'rusty_sword';
        $weapon = $this->content->getItem($weaponId);

        if (!$weapon) {
            // Fallback to rusty sword from loot.json
            $weapon = $this->content->getItem('rusty_sword') ?? [
                'id' => 'rusty_sword',
                'name' => 'Rusty Sword',
                'icon' => [5, 0],
                'type' => 'weapon',
                'subtype' => 'melee',
                'rarity' => 'common',
                'attack' => 8,
                'attack_variance' => 4,
                'shop_price' => 20,
            ];
        }

        // Starting armor from loot.json
        $startingArmor = $this->content->getItem('leather_armor') ?? [
            'id' => 'leather_armor',
            'name' => 'Leather Armor',
            'icon' => [7, 6],
            'type' => 'armor',
            'rarity' => 'common',
            'defense' => 3,
            'shop_price' => 18,
        ];

        // Starting potion from loot.json
        $startingPotion = $this->content->getItem('health_potion') ?? [
            'id' => 'health_potion',
            'name' => 'Health Potion',
            'icon' => [9, 0],
            'type' => 'consumable',
            'rarity' => 'common',
            'heal' => 30,
            'shop_price' => 12,
        ];

        // Equip the items
        $player->equipment_json = [
            'weapon' => $weapon,
            'armor' => $startingArmor,
            'accessory' => null,
        ];

        // Give starting potion
        $player->inventory_json = [$startingPotion];
    }

    /**
     * Show setup phase (card selection)
     */
    public function setup(string $matchId)
    {
        $match = GameMatch::with('players')->findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        // Must select hero first
        if (!$currentPlayer->hero_id) {
            return redirect()->route('match.hero', $match->id);
        }

        if ($match->isRunning() && $currentPlayer->setup_complete) {
            return redirect()->route('match.dungeon', $match->id);
        }

        // Use hero's available cards instead of all cards
        $hero = $this->content->getHero($currentPlayer->hero_id);
        $cards = $currentPlayer->available_cards_json ?? $this->content->getCards();
        $cardsToSelect = $hero['stats']['card_slots'] ?? $this->content->getRule('game.cards_to_select');

        return view('setup', [
            'match' => $match,
            'player' => $currentPlayer,
            'cards' => $cards,
            'cardsToSelect' => $cardsToSelect,
            'hero' => $hero,
        ]);
    }

    /**
     * Submit card selection
     */
    public function submitSetup(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        // Get hero's card slots
        $hero = $this->content->getHero($currentPlayer->hero_id);
        $requiredCards = $hero['stats']['card_slots'] ?? $this->content->getRule('game.cards_to_select');

        // Handle Gambler's random selection
        if ($request->input('gambler_random') && ($hero['id'] ?? '') === 'gambler') {
            $availableCards = $currentPlayer->available_cards_json ?? $this->content->getCards();
            $cardIds = collect($availableCards)->pluck('id')->toArray();
            shuffle($cardIds);
            $selectedCards = array_slice($cardIds, 0, $requiredCards);
        } else {
            $selectedCards = $request->input('cards', []);

            if (count($selectedCards) !== $requiredCards) {
                return back()->with('error', "Please select exactly {$requiredCards} cards");
            }
        }

        // Record card selection
        $this->gameStore->recordCardSelection($currentPlayer, $selectedCards);

        // Check if all players have completed setup
        if ($this->gameStore->allPlayersSetupComplete($match)) {
            // Generate dungeons
            $generator = new DungeonGenerator($this->content);
            $generator->generateForMatch($match);

            // Apply starting card effects to both players
            $this->applyStartingCardEffects($match);

            // Transition to running state
            $this->gameStore->transitionMatchState($match, GameMatch::STATE_RUNNING);
        }

        return redirect()->route('match.dungeon', $match->id);
    }

    /**
     * Show dungeon map
     */
    public function dungeon(string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        // Waiting for other player to complete setup
        if (!$currentPlayer->setup_complete) {
            return redirect()->route('match.setup', $match->id);
        }

        if ($match->isSetup()) {
            return view('waiting', [
                'match' => $match,
                'player' => $currentPlayer,
                'message' => 'Waiting for other player to select cards...',
            ]);
        }

        // Get player's dungeon (created by opponent)
        $dungeon = $currentPlayer->targetDungeon;

        if (!$dungeon) {
            return view('waiting', [
                'match' => $match,
                'player' => $currentPlayer,
                'message' => 'Generating dungeon...',
            ]);
        }

        $rooms = $dungeon->rooms()->orderBy('level')->orderBy('position')->get();

        // If dungeon exists but has no rooms, try to regenerate it
        if ($rooms->isEmpty()) {
            $generator = new DungeonGenerator($this->content);
            $opponent = $match->players()->where('id', '!=', $currentPlayer->id)->first();

            if ($opponent) {
                // Delete the empty dungeon and regenerate
                $dungeon->delete();
                $generator->generateDungeon($match, $opponent, $currentPlayer);

                // Reload dungeon and rooms
                $currentPlayer->refresh();
                $dungeon = $currentPlayer->targetDungeon;
                $rooms = $dungeon ? $dungeon->rooms()->orderBy('level')->orderBy('position')->get() : collect();
            }
        }

        $roomsByLevel = $rooms->groupBy('level');

        // Check if dungeon is complete (player can still stay here to manage inventory)
        $dungeonComplete = $currentPlayer->hasCompletedDungeon() || $this->isDungeonComplete($currentPlayer);

        return view('dungeon', [
            'match' => $match,
            'player' => $currentPlayer,
            'dungeon' => $dungeon,
            'roomsByLevel' => $roomsByLevel,
            'currentLevel' => $currentPlayer->current_level,
            'dungeonComplete' => $dungeonComplete,
        ]);
    }

    /**
     * Enter a room
     */
    public function enterRoom(Request $request, string $matchId, string $roomId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $room = Room::findOrFail($roomId);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        // Validate room belongs to player's dungeon
        $dungeon = $currentPlayer->targetDungeon;
        if ($room->dungeon_id !== $dungeon->id) {
            return back()->with('error', 'Invalid room');
        }

        // Validate room is next level
        $nextLevel = $currentPlayer->current_level + 1;
        if ($room->level !== $nextLevel) {
            return back()->with('error', 'You must progress level by level');
        }

        // Record entering the room
        $this->gameStore->recordRoomEntered($currentPlayer, $room);

        // Poison fog - take damage when entering any room
        $poisonMessage = null;
        if ($dungeon->hasModifier('poison_fog')) {
            $poisonDamage = 5;
            $currentPlayer->takeDamage($poisonDamage);
            $poisonMessage = "Poison fog deals {$poisonDamage} damage!";

            // Check if player died from poison
            if ($currentPlayer->current_hp <= 0) {
                $this->gameStore->recordDungeonCompleted($currentPlayer);
                return redirect()->route('match.dungeon', $match->id)
                    ->with('error', 'You died from poison fog!');
            }
        }

        // For enemy rooms, go directly to combat
        if ($room->isEnemy() || $room->isElite() || $room->isBoss()) {
            return $this->handleCombat($match, $currentPlayer, $room, $poisonMessage);
        }

        return redirect()->route('match.room', [$match->id, $room->id])
            ->with('message', $poisonMessage);
    }

    /**
     * Show room content
     */
    public function room(string $matchId, string $roomId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $room = Room::findOrFail($roomId);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        return view('room', [
            'match' => $match,
            'player' => $currentPlayer,
            'room' => $room,
        ]);
    }

    /**
     * Handle room action (fight, loot, shop)
     */
    public function roomAction(Request $request, string $matchId, string $roomId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $room = Room::findOrFail($roomId);
        $action = $request->input('action');

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        switch ($action) {
            case 'fight':
                return $this->handleCombat($match, $currentPlayer, $room);

            case 'loot':
                return $this->handleLoot($match, $currentPlayer, $room);

            case 'skip_loot':
                return $this->handleSkipLoot($match, $currentPlayer, $room);

            case 'buy':
                $itemId = $request->input('item_id');
                return $this->handleShopPurchase($match, $currentPlayer, $room, $itemId);

            case 'leave':
                return $this->handleLeaveRoom($match, $currentPlayer, $room);

            default:
                return back()->with('error', 'Invalid action');
        }
    }

    private function handleCombat(GameMatch $match, Player $player, Room $room, ?string $entryMessage = null): \Illuminate\Http\RedirectResponse
    {
        $content = $room->getContent();
        $enemy = $content['enemy'] ?? null;

        if (!$enemy) {
            return back()->with('error', 'No enemy in this room');
        }

        // Check for existing active combat
        $existingCombat = CombatState::where('player_id', $player->id)
            ->where('room_id', $room->id)
            ->where('is_active', true)
            ->first();

        if ($existingCombat) {
            return redirect()->route('combat', [$match->id, $existingCombat->id])
                ->with('message', $entryMessage);
        }

        // Generate encounter - can be single enemy or pair
        $enemies = $this->generateEncounter($enemy);

        // Create combat state
        $combatState = CombatState::create([
            'player_id' => $player->id,
            'room_id' => $room->id,
            'enemies_json' => $enemies,
            'turn' => 1,
            'player_used_item' => false,
            'is_active' => true,
        ]);

        return redirect()->route('combat', [$match->id, $combatState->id])
            ->with('message', $entryMessage);
    }

    private function generateEncounter(array $baseEnemy): array
    {
        // Initialize enemy with current_hp
        $enemy = array_merge($baseEnemy, [
            'current_hp' => $baseEnemy['hp'],
            'position' => $baseEnemy['position'] ?? 'front',
        ]);

        // 30% chance for a pair encounter on uncommon/elite
        $tier = $baseEnemy['tier'] ?? 'common';
        if (in_array($tier, ['uncommon', 'elite']) && rand(1, 100) <= 30) {
            // Add a weaker support enemy
            $supportEnemy = $this->content->getRandomEnemy('common');
            $supportEnemy = array_merge($supportEnemy, [
                'current_hp' => $supportEnemy['hp'],
                'position' => 'back',
            ]);
            return [$enemy, $supportEnemy];
        }

        return [$enemy];
    }

    private function handleLoot(GameMatch $match, Player $player, Room $room): \Illuminate\Http\RedirectResponse
    {
        $content = $room->getContent();
        $loot = $content['loot'] ?? null;

        if (!$loot) {
            return back()->with('error', 'No loot in this room');
        }

        $this->gameStore->recordLootCollected($player, $room, $loot);

        // Check if dungeon is complete (reached final level)
        if ($this->isDungeonComplete($player)) {
            $this->gameStore->recordDungeonCompleted($player);
        }

        return redirect()->route('match.dungeon', $match->id)->with('loot_collected', $loot);
    }

    private function handleSkipLoot(GameMatch $match, Player $player, Room $room): \Illuminate\Http\RedirectResponse
    {
        $content = $room->getContent();
        $loot = $content['loot'] ?? null;

        // Mark room as completed without collecting loot
        $room->completed = true;
        $room->save();

        // Check if dungeon is complete (reached final level)
        if ($this->isDungeonComplete($player)) {
            $this->gameStore->recordDungeonCompleted($player);
        }

        $message = $loot ? "Left {$loot['name']} behind" : 'Left the loot behind';
        return redirect()->route('match.dungeon', $match->id)->with('message', $message);
    }

    private function handleShopPurchase(GameMatch $match, Player $player, Room $room, string $itemId): \Illuminate\Http\RedirectResponse
    {
        $content = $room->getContent();
        $shopItems = $content['shop_items'] ?? [];

        $item = null;
        $itemIndex = null;
        foreach ($shopItems as $index => $shopItem) {
            if ($shopItem['id'] === $itemId) {
                $item = $shopItem;
                $itemIndex = $index;
                break;
            }
        }

        if (!$item) {
            return back()->with('error', 'Item not found in shop');
        }

        $price = $item['shop_price'] ?? 0;

        // Check for price modifiers from cards
        $dungeon = $player->targetDungeon;
        if ($dungeon->hasModifier('price_hike')) {
            $price = (int) ceil($price * 1.5);
        }
        if ($dungeon->hasModifier('discount')) {
            $price = (int) ceil($price * 0.75);
        }

        if ($player->gold < $price) {
            return back()->with('error', 'Not enough gold');
        }

        $this->gameStore->recordShopPurchase($player, $room, $item, $price);

        // Mark item as sold instead of removing
        $shopItems[$itemIndex]['sold'] = true;
        $content['shop_items'] = $shopItems;
        $room->content_json = $content;
        $room->save();

        return back()->with('message', "Bought {$item['name']} for {$price} gold");
    }

    private function handleLeaveRoom(GameMatch $match, Player $player, Room $room): \Illuminate\Http\RedirectResponse
    {
        $room->markCompleted();

        // Check if dungeon is complete (reached final level)
        if ($this->isDungeonComplete($player)) {
            $this->gameStore->recordDungeonCompleted($player);
            return redirect()->route('match.pvp', $match->id)->with('message', 'Dungeon complete! Prepare for PvP!');
        }

        return redirect()->route('match.dungeon', $match->id);
    }

    private function isDungeonComplete(Player $player): bool
    {
        $totalLevels = $this->content->getRule('dungeon.total_levels');
        return $player->current_level >= $totalLevels;
    }

    /**
     * Show PvP page - waits for both players, then starts battle
     */
    public function pvp(string $matchId)
    {
        $match = GameMatch::with('players')->findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        // Must have completed dungeon to access PvP
        if (!$currentPlayer->hasCompletedDungeon()) {
            return redirect()->route('match.dungeon', $match->id)
                ->with('error', 'Complete the dungeon first!');
        }

        // Check if both players have completed dungeon
        $allComplete = $this->gameStore->allPlayersCompletedDungeon($match);

        if (!$allComplete) {
            // Wait for other player
            return view('pvp-waiting', [
                'match' => $match,
                'player' => $currentPlayer,
            ]);
        }

        // Check if either player died in PvE - automatic loss
        $players = $match->players;
        $opponent = $players->where('id', '!=', $currentPlayer->id)->first();

        // Handle PvE death - check before creating battle
        $existingBattle = PvpBattle::where('match_id', $match->id)->first();
        if (!$existingBattle) {
            // If current player died in PvE, opponent wins automatically
            if ($currentPlayer->current_hp <= 0) {
                $pvpBattle = $this->createInstantWinBattle($match, $opponent, $currentPlayer, 'died in the dungeon');
                return view('pvp-result', [
                    'match' => $match,
                    'player' => $currentPlayer,
                    'pvpBattle' => $pvpBattle,
                ]);
            }
            // If opponent died in PvE, current player wins automatically
            if ($opponent && $opponent->current_hp <= 0) {
                $pvpBattle = $this->createInstantWinBattle($match, $currentPlayer, $opponent, 'died in the dungeon');
                return view('pvp-result', [
                    'match' => $match,
                    'player' => $currentPlayer,
                    'pvpBattle' => $pvpBattle,
                ]);
            }
        }

        // Both players ready - get or create battle (use transaction to prevent race condition)
        $pvpBattle = \Illuminate\Support\Facades\DB::transaction(function () use ($match) {
            // Lock the match row to prevent concurrent battle creation
            GameMatch::where('id', $match->id)->lockForUpdate()->first();

            $battle = PvpBattle::with(['player1', 'player2'])->where('match_id', $match->id)->first();
            if (!$battle) {
                $battle = PvpBattle::createForMatch($match);
                $battle->load(['player1', 'player2']);
            }
            return $battle;
        });

        // If battle is still active, redirect to battle
        if ($pvpBattle->is_active) {
            return redirect()->route('pvp.battle', [$match->id, $pvpBattle->id]);
        }

        // Battle is done - show result
        return view('pvp-result', [
            'match' => $match,
            'player' => $currentPlayer,
            'pvpBattle' => $pvpBattle,
        ]);
    }

    /**
     * Create an instant win battle when one player died in PvE
     */
    private function createInstantWinBattle(GameMatch $match, Player $winner, Player $loser, string $reason): PvpBattle
    {
        $battle = PvpBattle::create([
            'match_id' => $match->id,
            'player1_id' => $winner->id,
            'player1_hp' => $winner->current_hp,
            'player1_max_hp' => $winner->getMaxHp(),
            'player1_attack' => $winner->getTotalAttack(),
            'player1_attack_variance' => $winner->getAttackVariance(),
            'player1_defense' => $winner->getTotalDefense(),
            'player1_crit_chance' => $winner->getCritChance(),
            'player2_id' => $loser->id,
            'player2_hp' => 0,
            'player2_max_hp' => $loser->getMaxHp(),
            'player2_attack' => $loser->getTotalAttack(),
            'player2_attack_variance' => $loser->getAttackVariance(),
            'player2_defense' => $loser->getTotalDefense(),
            'player2_crit_chance' => $loser->getCritChance(),
            'current_turn_player_id' => $winner->id,
            'turn' => 1,
            'is_active' => false,
            'winner_player_id' => $winner->id,
            'combat_log' => [
                ['turn' => 0, 'message' => "{$loser->name} {$reason}!", 'type' => 'death'],
                ['turn' => 0, 'message' => "{$winner->name} wins by default!", 'type' => 'info'],
            ],
        ]);

        // Update match state
        $match->winner_player_id = $winner->id;
        $match->state = 'finished';
        $match->save();

        return $battle;
    }

    /**
     * Show PvP battle screen
     */
    public function pvpBattle(string $matchId, string $pvpId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $pvpBattle = PvpBattle::with(['player1', 'player2'])->findOrFail($pvpId);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        // Verify player is part of this battle
        if ($pvpBattle->player1_id !== $currentPlayer->id && $pvpBattle->player2_id !== $currentPlayer->id) {
            return redirect()->route('dashboard')->with('error', 'Invalid PvP battle');
        }

        if (!$pvpBattle->is_active) {
            return redirect()->route('match.pvp', $match->id);
        }

        // Get opponent player
        $opponent = $pvpBattle->player1_id === $currentPlayer->id
            ? $pvpBattle->player2
            : $pvpBattle->player1;

        return view('pvp-battle', [
            'match' => $match,
            'player' => $currentPlayer,
            'opponent' => $opponent,
            'pvpBattle' => $pvpBattle,
        ]);
    }

    /**
     * Handle attack in PvP (turn-based)
     */
    public function pvpAttack(Request $request, string $matchId, string $pvpId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $pvpBattle = PvpBattle::findOrFail($pvpId);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        // Verify player is part of this battle
        $playerNum = $pvpBattle->getPlayerNumber($currentPlayer);
        if (!$playerNum) {
            return redirect()->route('dashboard')->with('error', 'Invalid PvP battle');
        }

        // Check if it's this player's turn
        if (!$pvpBattle->isPlayersTurn($currentPlayer)) {
            return redirect()->route('pvp.battle', [$match->id, $pvpBattle->id])
                ->with('error', 'Not your turn! Wait for opponent.');
        }

        $opponentNum = $pvpBattle->getOpponentNumber($currentPlayer);
        $opponentPlayer = $opponentNum === 1 ? $pvpBattle->player1 : $pvpBattle->player2;

        // Calculate attack
        $attackRoll = $pvpBattle->rollAttack($playerNum);
        $critChance = $pvpBattle->getPlayerCritChance($playerNum);
        $isCrit = rand(1, 100) <= $critChance;
        if ($isCrit) {
            $attackRoll = (int) ($attackRoll * 1.5);
        }

        // Deal damage to opponent
        $damageDealt = $pvpBattle->damagePlayer($opponentNum, $attackRoll);

        // Log the attack
        $pvpBattle->addCombatLog(
            "{$currentPlayer->name} attacks {$opponentPlayer->name} for {$damageDealt} damage" . ($isCrit ? ' (CRIT!)' : ''),
            'attack'
        );

        // Save damage and log before checking death
        $pvpBattle->save();

        // Check if opponent is dead
        if ($pvpBattle->isPlayerDead($opponentNum)) {
            $pvpBattle->addCombatLog("{$opponentPlayer->name} has been defeated!", 'death');
            $pvpBattle->endBattle($currentPlayer->id);

            return redirect()->route('match.pvp', $match->id);
        }

        // Switch turn (also saves)
        $pvpBattle->switchTurn();

        return redirect()->route('pvp.battle', [$match->id, $pvpBattle->id]);
    }

    /**
     * Get match state for polling
     */
    public function state(string $matchId)
    {
        $match = GameMatch::with('players')->findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);

        return response()->json([
            'state' => $match->state,
            'player_count' => $match->players->count(),
            'all_setup_complete' => $this->gameStore->allPlayersSetupComplete($match),
            'all_dungeons_complete' => $this->gameStore->allPlayersCompletedDungeon($match),
            'current_player' => $currentPlayer ? [
                'id' => $currentPlayer->id,
                'setup_complete' => $currentPlayer->setup_complete,
                'dungeon_completed' => $currentPlayer->hasCompletedDungeon(),
            ] : null,
        ]);
    }

    /**
     * Show combat screen
     */
    public function combat(string $matchId, string $combatId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $combatState = CombatState::findOrFail($combatId);

        if (!$currentPlayer || $combatState->player_id !== $currentPlayer->id) {
            return redirect()->route('dashboard')->with('error', 'Invalid combat');
        }

        if (!$combatState->is_active) {
            return redirect()->route('match.dungeon', $match->id);
        }

        return view('combat', [
            'match' => $match,
            'player' => $currentPlayer,
            'combatState' => $combatState,
            'room' => $combatState->room,
        ]);
    }

    /**
     * Handle attack action in combat
     */
    public function combatAttack(Request $request, string $matchId, string $combatId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $combatState = CombatState::findOrFail($combatId);
        $targetIndex = (int) $request->input('target');

        if (!$currentPlayer || $combatState->player_id !== $currentPlayer->id) {
            return redirect()->route('dashboard')->with('error', 'Invalid combat');
        }

        $log = [];

        // Get temp buffs from session
        $buffKey = "combat_buffs_{$combatId}";
        $buffs = session($buffKey, ['attack' => 0, 'defense' => 0, 'crit' => 0]);

        // Player attacks
        $enemies = $combatState->getEnemies();
        if (!isset($enemies[$targetIndex]) || ($enemies[$targetIndex]['current_hp'] ?? 0) <= 0) {
            return back()->with('error', 'Invalid target');
        }

        $playerAttack = $currentPlayer->rollAttack() + $buffs['attack'];
        $critChance = $currentPlayer->getCritChance() + $buffs['crit'];
        $isCrit = rand(1, 100) <= $critChance;
        if ($isCrit) {
            $playerAttack = (int) ($playerAttack * 1.5);
        }

        $damageDealt = $combatState->damageEnemy($targetIndex, $playerAttack);
        $log[] = [
            'type' => 'damage_dealt',
            'message' => "You attack {$enemies[$targetIndex]['name']} for {$damageDealt} damage" . ($isCrit ? ' (CRIT!)' : ''),
        ];

        // Check if all enemies dead
        if ($combatState->allEnemiesDead()) {
            session()->forget($buffKey); // Clear temp buffs
            return $this->endCombatVictory($match, $currentPlayer, $combatState, $log);
        }

        // Enemies attack
        $playerDefense = $currentPlayer->getTotalDefense() + $buffs['defense'];
        $targetDungeon = $currentPlayer->targetDungeon;

        // Armor Break - opponent card, enemies ignore 3 defense
        if ($targetDungeon && $targetDungeon->hasModifier('armor_break')) {
            $playerDefense = max(0, $playerDefense - 3);
        }

        foreach ($combatState->getLivingEnemies() as $index => $enemy) {
            $enemyAttack = ($enemy['attack'] ?? 10) + rand(-($enemy['attack_variance'] ?? 2), $enemy['attack_variance'] ?? 2);

            // Boss Rage - opponent card, boss deals +20% damage
            $tier = $enemy['tier'] ?? 'common';
            if ($tier === 'boss' && $targetDungeon && $targetDungeon->hasModifier('boss_rage')) {
                $enemyAttack = (int) ceil($enemyAttack * 1.2);
            }

            // Double Trap - enemy_buff from room content (applied during dungeon generation)
            $room = $combatState->room;
            $content = $room->getContent();
            if (isset($content['enemy_buff'])) {
                $enemyAttack += $content['enemy_buff']['damage_bonus'] ?? 0;
            }

            $damageToPlayer = max(0, $enemyAttack - $playerDefense);
            $currentPlayer->takeDamage($damageToPlayer);

            $attackMessage = "{$enemy['name']} attacks you for {$damageToPlayer} damage";

            // Lifesteal - enemy heals based on damage dealt
            if (isset($enemy['lifesteal']) && $enemy['lifesteal'] > 0 && $damageToPlayer > 0) {
                $healAmount = min($enemy['lifesteal'], $damageToPlayer);
                $enemies = $combatState->getEnemies();
                if (isset($enemies[$index])) {
                    $maxHp = $enemies[$index]['hp'] ?? 100;
                    $currentHp = $enemies[$index]['current_hp'] ?? 0;
                    $enemies[$index]['current_hp'] = min($maxHp, $currentHp + $healAmount);
                    $combatState->enemies_json = $enemies;
                    $combatState->save();
                    $attackMessage .= " and drains {$healAmount} HP";
                }
            }

            $log[] = [
                'type' => 'damage_taken',
                'message' => $attackMessage,
            ];
        }

        // Check if player died
        if ($currentPlayer->current_hp <= 0) {
            return $this->endCombatDefeat($match, $currentPlayer, $combatState, $log);
        }

        // Next turn
        $combatState->nextTurn();

        return redirect()->route('combat', [$match->id, $combatState->id])
            ->with('combat_log', $log);
    }

    /**
     * Handle using an item in combat (free action)
     */
    public function combatUseItem(Request $request, string $matchId, string $combatId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $combatState = CombatState::findOrFail($combatId);
        $itemIndex = (int) $request->input('item_index');

        if (!$currentPlayer || $combatState->player_id !== $currentPlayer->id) {
            return redirect()->route('dashboard')->with('error', 'Invalid combat');
        }

        if ($combatState->player_used_item) {
            return back()->with('error', 'Already used an item this turn');
        }

        $item = $currentPlayer->removeFromInventory($itemIndex);
        if (!$item || ($item['type'] ?? '') !== 'consumable') {
            return back()->with('error', 'Invalid item');
        }

        $log = [];
        $buffKey = "combat_buffs_{$combatId}";
        $buffs = session($buffKey, ['attack' => 0, 'defense' => 0, 'crit' => 0]);

        // Apply item effect
        if (isset($item['heal'])) {
            $currentPlayer->heal($item['heal']);
            $log[] = [
                'type' => 'heal',
                'message' => "You use {$item['name']} and restore {$item['heal']} HP",
            ];
        }

        // Temp attack bonus
        if (isset($item['temp_attack_bonus'])) {
            $buffs['attack'] += $item['temp_attack_bonus'];
            $log[] = [
                'type' => 'buff',
                'message' => "You use {$item['name']} and gain +{$item['temp_attack_bonus']} attack for this combat",
            ];
        }

        // Temp defense bonus
        if (isset($item['temp_defense_bonus'])) {
            $buffs['defense'] += $item['temp_defense_bonus'];
            $log[] = [
                'type' => 'buff',
                'message' => "You use {$item['name']} and gain +{$item['temp_defense_bonus']} defense for this combat",
            ];
        }

        // Temp crit bonus
        if (isset($item['temp_crit_bonus'])) {
            $buffs['crit'] += $item['temp_crit_bonus'];
            $log[] = [
                'type' => 'buff',
                'message' => "You use {$item['name']} and gain +{$item['temp_crit_bonus']}% crit for this combat",
            ];
        }

        session([$buffKey => $buffs]);

        $combatState->player_used_item = true;
        $combatState->save();

        return redirect()->route('combat', [$match->id, $combatState->id])
            ->with('combat_log', $log);
    }

    /**
     * Handle fleeing from combat
     */
    public function combatFlee(Request $request, string $matchId, string $combatId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $combatState = CombatState::findOrFail($combatId);

        if (!$currentPlayer || $combatState->player_id !== $currentPlayer->id) {
            return redirect()->route('dashboard')->with('error', 'Invalid combat');
        }

        // Clear temp buffs
        session()->forget("combat_buffs_{$combatId}");

        // End combat without rewards
        $combatState->endCombat();
        $room = $combatState->room;
        $room->markCompleted();

        return redirect()->route('match.dungeon', $match->id)
            ->with('message', 'You fled from combat. No rewards gained.');
    }

    private function endCombatVictory(GameMatch $match, Player $player, CombatState $combatState, array $log)
    {
        $rewards = $combatState->getTotalRewards();
        $room = $combatState->room;
        $content = $room->getContent();

        $gold = $rewards['gold'];
        $xp = $rewards['xp'];

        // Gold Rush - self card, check createdDungeon (+30% gold)
        $createdDungeon = $player->createdDungeon;
        if ($createdDungeon && $createdDungeon->hasModifier('gold_rush')) {
            $gold = (int) ceil($gold * 1.3);
        }

        // XP Drain - opponent card, check targetDungeon (-25% XP)
        $targetDungeon = $player->targetDungeon;
        if ($targetDungeon && $targetDungeon->hasModifier('xp_drain')) {
            $xp = (int) ceil($xp * 0.75);
        }

        // Give rewards
        $player->addGold($gold);
        $player->addXp($xp);

        $log[] = [
            'type' => 'reward',
            'message' => "Victory! Earned {$gold} gold and {$xp} XP",
        ];

        // Healing aura - heal after combat (self card, check createdDungeon)
        $createdDungeon = $player->createdDungeon;
        if ($createdDungeon && $createdDungeon->hasModifier('healing_aura')) {
            $healAmount = 5;
            $player->heal($healAmount);
            $log[] = [
                'type' => 'heal',
                'message' => "Healing aura restores {$healAmount} HP",
            ];
        }

        // Check for bonus loot
        if (isset($content['bonus_loot'])) {
            $bonusLoot = $content['bonus_loot'];
            $added = $player->addItem($bonusLoot);

            if ($added) {
                $log[] = [
                    'type' => 'loot',
                    'message' => "Found: {$bonusLoot['name']}",
                ];
            } else {
                // Store pending loot in session for decision screen
                session(['pending_bonus_loot' => $bonusLoot, 'pending_loot_match_id' => $match->id]);
                $log[] = [
                    'type' => 'loot',
                    'message' => "Found: {$bonusLoot['name']} (inventory full!)",
                ];
            }
        }

        // End combat
        $combatState->endCombat();
        $room->markCompleted();

        // Check if dungeon complete - just mark it, don't auto-redirect to PvP
        if ($this->isDungeonComplete($player)) {
            $this->gameStore->recordDungeonCompleted($player);
        }

        return redirect()->route('match.dungeon', $match->id)
            ->with('combat_log', $log);
    }

    private function endCombatDefeat(GameMatch $match, Player $player, CombatState $combatState, array $log)
    {
        // Clear temp buffs
        session()->forget("combat_buffs_{$combatState->id}");

        $combatState->endCombat();
        $this->gameStore->recordDungeonCompleted($player);

        // Return to dungeon view where player can see they died and start PvP when ready
        return redirect()->route('match.dungeon', $match->id)
            ->with('message', 'You died in combat! Dungeon complete.');
    }

    /**
     * Scout (reveal) a room's true type
     */
    public function scoutRoom(Request $request, string $matchId, string $roomId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $room = Room::findOrFail($roomId);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        // Validate room belongs to player's dungeon
        $dungeon = $currentPlayer->targetDungeon;
        if ($room->dungeon_id !== $dungeon->id) {
            return back()->with('error', 'Invalid room');
        }

        // Check if room is already revealed (displayed matches actual)
        if ($room->displayed_type === $room->actual_type) {
            return back()->with('error', 'Room is already revealed');
        }

        // Check if player has scout ability (from their own cards in createdDungeon)
        $createdDungeon = $currentPlayer->createdDungeon;
        if (!$createdDungeon || !$createdDungeon->hasModifier('scout')) {
            return back()->with('error', 'You do not have scout ability');
        }

        // Check remaining scout uses
        $scoutKey = "scout_uses_{$currentPlayer->id}";
        $scoutUses = session($scoutKey);

        // Initialize scout uses if not set (based on card effect count)
        if ($scoutUses === null) {
            $card = $this->content->getCard('scout');
            $scoutUses = $card['effect']['count'] ?? 1;
            session([$scoutKey => $scoutUses]);
        }

        if ($scoutUses <= 0) {
            return back()->with('error', 'No scout uses remaining');
        }

        // Reveal the room
        $room->displayed_type = $room->actual_type;
        $room->save();

        // Decrement scout uses
        session([$scoutKey => $scoutUses - 1]);

        return back()->with('message', "Room revealed: {$room->actual_type}!");
    }

    /**
     * Equip an item from inventory
     */
    public function equipItem(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $itemIndex = (int) $request->input('item_index');

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        if ($currentPlayer->current_hp <= 0 || $match->state === 'finished') {
            return back()->with('error', 'You cannot equip items after the match has ended');
        }

        $item = $currentPlayer->removeFromInventory($itemIndex);
        if (!$item) {
            return back()->with('error', 'Invalid item');
        }

        if (!in_array($item['type'], Player::EQUIPMENT_SLOTS)) {
            $currentPlayer->addToInventory($item);
            return back()->with('error', 'Cannot equip this item type');
        }

        $currentPlayer->equipItem($item);
        return back()->with('message', "Equipped {$item['name']}");
    }

    /**
     * Unequip an item to inventory
     */
    public function unequipItem(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $slot = $request->input('slot');

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        if ($currentPlayer->current_hp <= 0 || $match->state === 'finished') {
            return back()->with('error', 'You cannot unequip items after the match has ended');
        }

        if (!in_array($slot, Player::EQUIPMENT_SLOTS)) {
            return back()->with('error', 'Invalid equipment slot');
        }

        if (!$currentPlayer->hasInventorySpace()) {
            return back()->with('error', 'Inventory is full');
        }

        $equipment = $currentPlayer->getEquipment();
        $item = $equipment[$slot] ?? null;

        if (!$item) {
            return back()->with('error', 'Nothing equipped in that slot');
        }

        $equipment[$slot] = null;
        $currentPlayer->equipment_json = $equipment;
        $currentPlayer->save();
        $currentPlayer->addToInventory($item);

        return back()->with('message', "Unequipped {$item['name']}");
    }

    /**
     * Use a consumable item from inventory
     */
    public function useItem(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $itemIndex = (int) $request->input('item_index');

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        if ($currentPlayer->current_hp <= 0 || $match->state === 'finished') {
            return back()->with('error', 'You cannot use items after the match has ended');
        }

        // Check if there's an active PvP battle
        $pvpBattle = PvpBattle::where('match_id', $matchId)
            ->where('is_active', true)
            ->first();

        if ($pvpBattle) {
            // During PvP, can only use items on your turn
            if (!$pvpBattle->isPlayersTurn($currentPlayer)) {
                return back()->with('error', 'You can only use items on your turn!');
            }
        }

        $item = $currentPlayer->removeFromInventory($itemIndex);
        if (!$item) {
            return back()->with('error', 'Invalid item');
        }

        if (($item['type'] ?? '') !== 'consumable') {
            $currentPlayer->addToInventory($item);
            return back()->with('error', 'Cannot use this item');
        }

        $message = "Used {$item['name']}";

        if (isset($item['heal'])) {
            if ($pvpBattle) {
                // During PvP, heal the battle HP
                $playerNum = $pvpBattle->getPlayerNumber($currentPlayer);
                $actualHeal = $pvpBattle->healPlayer($playerNum, $item['heal']);
                $pvpBattle->addCombatLog("{$currentPlayer->name} used {$item['name']} and restored {$actualHeal} HP!", 'heal');
                $pvpBattle->save();
                $message .= " - restored {$actualHeal} HP";
            } else {
                // Normal dungeon healing
                $currentPlayer->heal($item['heal']);
                $message .= " - restored {$item['heal']} HP";
            }
        }

        return back()->with('message', $message);
    }

    /**
     * Sell an item from inventory (only works in shop rooms)
     */
    public function sellItem(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $itemIndex = (int) $request->input('item_index');

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        $item = $currentPlayer->removeFromInventory($itemIndex);
        if (!$item) {
            return back()->with('error', 'Invalid item');
        }

        // Calculate sell price (50% of shop price)
        $sellPrice = (int) floor(($item['shop_price'] ?? 10) * 0.5);

        $currentPlayer->addGold($sellPrice);

        return back()->with('message', "Sold {$item['name']} for {$sellPrice} gold");
    }

    public function dropItem(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $itemIndex = (int) $request->input('item_index');

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        $item = $currentPlayer->removeFromInventory($itemIndex);
        if (!$item) {
            return back()->with('error', 'Invalid item');
        }

        return back()->with('message', "Dropped {$item['name']}");
    }

    /**
     * Swap an inventory item with pending bonus loot
     */
    public function swapBonusLoot(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);
        $dropIndex = (int) $request->input('drop_index');

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        $pendingLoot = session('pending_bonus_loot');
        $pendingMatchId = session('pending_loot_match_id');

        if (!$pendingLoot || $pendingMatchId !== $match->id) {
            return redirect()->route('match.dungeon', $match->id)->with('error', 'No pending loot');
        }

        // Remove item from inventory
        $droppedItem = $currentPlayer->removeFromInventory($dropIndex);
        if (!$droppedItem) {
            return back()->with('error', 'Invalid item');
        }

        // Add pending loot to inventory
        $currentPlayer->addToInventory($pendingLoot);

        // Clear session
        session()->forget(['pending_bonus_loot', 'pending_loot_match_id']);

        return redirect()->route('match.dungeon', $match->id)
            ->with('message', "Swapped {$droppedItem['name']} for {$pendingLoot['name']}");
    }

    /**
     * Discard pending bonus loot
     */
    public function discardBonusLoot(Request $request, string $matchId)
    {
        $match = GameMatch::findOrFail($matchId);
        $currentPlayer = $this->getCurrentPlayer($match);

        if (!$currentPlayer) {
            return redirect()->route('dashboard')->with('error', 'You are not in this match');
        }

        $pendingLoot = session('pending_bonus_loot');

        // Clear session
        session()->forget(['pending_bonus_loot', 'pending_loot_match_id']);

        $itemName = $pendingLoot['name'] ?? 'item';
        return redirect()->route('match.dungeon', $match->id)
            ->with('message', "Left {$itemName} behind");
    }

    /**
     * Apply starting card effects to all players
     */
    private function applyStartingCardEffects(GameMatch $match): void
    {
        $players = $match->players()->get();

        foreach ($players as $player) {
            // Self-targeting cards: check player's createdDungeon (cards they selected)
            $createdDungeon = $player->createdDungeon;
            if ($createdDungeon) {
                $selfModifiers = $createdDungeon->getModifiers();
                foreach ($selfModifiers as $cardId) {
                    $card = $this->content->getCard($cardId);
                    if (!$card || ($card['target'] ?? '') !== 'self') continue;
                    $this->applyStartingEffect($player, $card);
                }
            }

            // Opponent-targeting cards: check player's targetDungeon (cards opponent selected)
            $targetDungeon = $player->targetDungeon;
            if ($targetDungeon) {
                $opponentModifiers = $targetDungeon->getModifiers();
                foreach ($opponentModifiers as $cardId) {
                    $card = $this->content->getCard($cardId);
                    if (!$card || ($card['target'] ?? '') !== 'opponent') continue;
                    $this->applyStartingEffect($player, $card);
                }
            }

            $player->save();
        }
    }

    /**
     * Apply a single card's starting effect to a player
     */
    private function applyStartingEffect(Player $player, array $card): void
    {
        $effect = $card['effect'] ?? [];
        $action = $effect['action'] ?? '';

        switch ($action) {
            case 'modify_starting_hp':
                $amount = $effect['amount'] ?? 0;
                $player->current_hp = max(1, $player->current_hp + $amount);
                $player->max_hp = max(1, $player->max_hp + $amount);
                break;

            case 'modify_starting_gold':
                $amount = $effect['amount'] ?? 0;
                $player->gold = max(0, $player->gold + $amount);
                break;

            case 'modify_crit':
                $amount = $effect['amount'] ?? 0;
                $player->crit_chance = ($player->crit_chance ?? 5) + $amount;
                break;

            case 'modify_attack':
                $amount = $effect['amount'] ?? 0;
                $player->base_attack = ($player->base_attack ?? 5) + $amount;
                break;

            case 'modify_defense':
                $amount = $effect['amount'] ?? 0;
                $player->base_defense = ($player->base_defense ?? 0) + $amount;
                break;
        }
    }

    private function getAccount(): ?Account
    {
        $accountId = session('account_id');

        if (!$accountId) {
            return null;
        }

        return Account::find($accountId);
    }

    private function getCurrentPlayer(GameMatch $match): ?Player
    {
        $playerId = session('player_id');

        if ($playerId) {
            $player = $match->players()->where('id', $playerId)->first();
            if ($player) {
                return $player;
            }
        }

        // Fallback: find by account_id if player_id doesn't work
        $accountId = session('account_id');
        if ($accountId) {
            $player = $match->players()->where('account_id', $accountId)->first();
            if ($player) {
                // Restore player_id in session
                session(['player_id' => $player->id]);
                return $player;
            }
        }

        return null;
    }
}
