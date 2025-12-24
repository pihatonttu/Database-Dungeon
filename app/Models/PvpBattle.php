<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvpBattle extends Model
{
    use HasUuids;

    protected $fillable = [
        'match_id',
        'player1_id',
        'player1_hp',
        'player1_max_hp',
        'player1_attack',
        'player1_attack_variance',
        'player1_defense',
        'player1_crit_chance',
        'player2_id',
        'player2_hp',
        'player2_max_hp',
        'player2_attack',
        'player2_attack_variance',
        'player2_defense',
        'player2_crit_chance',
        'current_turn_player_id',
        'turn',
        'is_active',
        'winner_player_id',
        'combat_log',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'combat_log' => 'array',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player2_id');
    }

    public function currentTurnPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'current_turn_player_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    public function isPlayersTurn(Player $player): bool
    {
        return $this->current_turn_player_id === $player->id;
    }

    public function getPlayerNumber(Player $player): ?int
    {
        if ($this->player1_id === $player->id) return 1;
        if ($this->player2_id === $player->id) return 2;
        return null;
    }

    public function getOpponentNumber(Player $player): ?int
    {
        $playerNum = $this->getPlayerNumber($player);
        return $playerNum === 1 ? 2 : ($playerNum === 2 ? 1 : null);
    }

    public function getPlayerHp(int $playerNum): int
    {
        return $playerNum === 1 ? $this->player1_hp : $this->player2_hp;
    }

    public function getPlayerMaxHp(int $playerNum): int
    {
        return $playerNum === 1 ? $this->player1_max_hp : $this->player2_max_hp;
    }

    public function getPlayerAttack(int $playerNum): int
    {
        return $playerNum === 1 ? $this->player1_attack : $this->player2_attack;
    }

    public function getPlayerDefense(int $playerNum): int
    {
        return $playerNum === 1 ? $this->player1_defense : $this->player2_defense;
    }

    public function getPlayerCritChance(int $playerNum): int
    {
        return $playerNum === 1 ? $this->player1_crit_chance : $this->player2_crit_chance;
    }

    public function rollAttack(int $playerNum): int
    {
        $base = $playerNum === 1 ? $this->player1_attack : $this->player2_attack;
        $variance = $playerNum === 1 ? $this->player1_attack_variance : $this->player2_attack_variance;
        return $base + rand(-$variance, $variance);
    }

    public function damagePlayer(int $playerNum, int $damage): int
    {
        $defense = $this->getPlayerDefense($playerNum);
        $actualDamage = max(1, $damage - $defense);

        if ($playerNum === 1) {
            $this->player1_hp = max(0, $this->player1_hp - $actualDamage);
        } else {
            $this->player2_hp = max(0, $this->player2_hp - $actualDamage);
        }

        return $actualDamage;
    }

    public function healPlayer(int $playerNum, int $amount): int
    {
        $maxHp = $this->getPlayerMaxHp($playerNum);
        $currentHp = $this->getPlayerHp($playerNum);
        $actualHeal = min($amount, $maxHp - $currentHp);

        if ($playerNum === 1) {
            $this->player1_hp = min($maxHp, $this->player1_hp + $actualHeal);
        } else {
            $this->player2_hp = min($maxHp, $this->player2_hp + $actualHeal);
        }

        return $actualHeal;
    }

    public function isPlayerDead(int $playerNum): bool
    {
        return $this->getPlayerHp($playerNum) <= 0;
    }

    public function addCombatLog(string $message, string $type = 'info'): void
    {
        $log = $this->combat_log ?? [];
        $log[] = [
            'turn' => $this->turn,
            'message' => $message,
            'type' => $type,
            'timestamp' => now()->toISOString(),
        ];
        $this->combat_log = $log;
    }

    public function getRecentLogs(int $count = 10): array
    {
        $log = $this->combat_log ?? [];
        return array_slice($log, -$count);
    }

    public function switchTurn(): void
    {
        $this->current_turn_player_id = $this->current_turn_player_id === $this->player1_id
            ? $this->player2_id
            : $this->player1_id;
        $this->turn++;
        $this->save();
    }

    public function endBattle(string $winnerId): void
    {
        $this->is_active = false;
        $this->winner_player_id = $winnerId;
        $this->save();

        // Update players' HP to their final battle HP
        $this->player1->current_hp = $this->player1_hp;
        $this->player1->save();
        $this->player2->current_hp = $this->player2_hp;
        $this->player2->save();

        // Update match
        $match = $this->match;
        $match->winner_player_id = $winnerId;
        $match->state = 'finished';
        $match->save();
    }

    public static function createForMatch(GameMatch $match): self
    {
        $players = $match->players()->get();
        if ($players->count() !== 2) {
            throw new \Exception('Match must have exactly 2 players');
        }

        $player1 = $players[0];
        $player2 = $players[1];

        // Use current HP from PvE - no healing! This is the risk of PvE.
        // Death cases (HP = 0) are handled in the controller before this is called.
        $p1Hp = $player1->current_hp;
        $p2Hp = $player2->current_hp;

        // Randomly decide who goes first
        $firstPlayer = rand(0, 1) === 0 ? $player1 : $player2;

        $battle = self::create([
            'match_id' => $match->id,
            'player1_id' => $player1->id,
            'player1_hp' => $p1Hp,
            'player1_max_hp' => $player1->getMaxHp(),
            'player1_attack' => $player1->getTotalAttack(),
            'player1_attack_variance' => $player1->getAttackVariance(),
            'player1_defense' => $player1->getTotalDefense(),
            'player1_crit_chance' => $player1->getCritChance(),
            'player2_id' => $player2->id,
            'player2_hp' => $p2Hp,
            'player2_max_hp' => $player2->getMaxHp(),
            'player2_attack' => $player2->getTotalAttack(),
            'player2_attack_variance' => $player2->getAttackVariance(),
            'player2_defense' => $player2->getTotalDefense(),
            'player2_crit_chance' => $player2->getCritChance(),
            'current_turn_player_id' => $firstPlayer->id,
            'turn' => 1,
            'is_active' => true,
            'combat_log' => [],
        ]);

        // Add initial combat log entry
        $battle->addCombatLog("{$firstPlayer->name} goes first!", 'info');
        $battle->save();

        return $battle;
    }
}
