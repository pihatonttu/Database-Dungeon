<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombatState extends Model
{
    use HasUuids;

    protected $fillable = [
        'player_id',
        'room_id',
        'enemies_json',
        'turn',
        'player_used_item',
        'is_active',
    ];

    protected $casts = [
        'enemies_json' => 'array',
        'player_used_item' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function getEnemies(): array
    {
        return $this->enemies_json ?? [];
    }

    public function getLivingEnemies(): array
    {
        return array_filter($this->getEnemies(), fn($e) => ($e['current_hp'] ?? 0) > 0);
    }

    public function getFrontEnemies(): array
    {
        return array_filter($this->getLivingEnemies(), fn($e) => ($e['position'] ?? 'front') === 'front');
    }

    public function getBackEnemies(): array
    {
        return array_filter($this->getLivingEnemies(), fn($e) => ($e['position'] ?? 'front') === 'back');
    }

    public function updateEnemy(int $index, array $data): void
    {
        $enemies = $this->getEnemies();
        if (isset($enemies[$index])) {
            $enemies[$index] = array_merge($enemies[$index], $data);
            $this->enemies_json = $enemies;
            $this->save();
        }
    }

    public function damageEnemy(int $index, int $damage): int
    {
        $enemies = $this->getEnemies();
        if (!isset($enemies[$index])) {
            return 0;
        }

        $actualDamage = max(0, $damage - ($enemies[$index]['defense'] ?? 0));
        $enemies[$index]['current_hp'] = max(0, ($enemies[$index]['current_hp'] ?? 0) - $actualDamage);
        $this->enemies_json = $enemies;
        $this->save();

        return $actualDamage;
    }

    public function allEnemiesDead(): bool
    {
        return count($this->getLivingEnemies()) === 0;
    }

    public function nextTurn(): void
    {
        $this->turn++;
        $this->player_used_item = false;
        $this->save();
    }

    public function endCombat(): void
    {
        $this->is_active = false;
        $this->save();
    }

    public function getTotalRewards(): array
    {
        $gold = 0;
        $xp = 0;

        foreach ($this->getEnemies() as $enemy) {
            if (($enemy['current_hp'] ?? 0) <= 0) {
                $gold += $enemy['gold_reward'] ?? 0;
                $xp += $enemy['xp_reward'] ?? 0;
            }
        }

        return ['gold' => $gold, 'xp' => $xp];
    }
}
