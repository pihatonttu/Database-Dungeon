<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dungeon extends Model
{
    use HasUuids;

    protected $fillable = [
        'match_id',
        'owner_player_id',
        'target_player_id',
        'seed',
        'structure_json',
        'modifiers_json',
    ];

    protected $casts = [
        'structure_json' => 'array',
        'modifiers_json' => 'array',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    // Player who created this dungeon via their card choices
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'owner_player_id');
    }

    // Player who plays through this dungeon
    public function target(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'target_player_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function getModifiers(): array
    {
        return $this->modifiers_json ?? [];
    }

    public function hasModifier(string $modifierId): bool
    {
        return in_array($modifierId, $this->getModifiers());
    }

    public function getRoomAtLevel(int $level, string $position = 'center'): ?Room
    {
        return $this->rooms()
            ->where('level', $level)
            ->where('position', $position)
            ->first();
    }

    public function getRoomsAtLevel(int $level): \Illuminate\Database\Eloquent\Collection
    {
        return $this->rooms()
            ->where('level', $level)
            ->orderBy('position')
            ->get();
    }
}
