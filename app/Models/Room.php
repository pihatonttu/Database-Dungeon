<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasUuids;

    protected $fillable = [
        'dungeon_id',
        'level',
        'position',
        'displayed_type',
        'actual_type',
        'content_json',
        'visited',
        'completed',
    ];

    protected $casts = [
        'content_json' => 'array',
        'visited' => 'boolean',
        'completed' => 'boolean',
    ];

    // Room types
    public const TYPE_ENEMY = 'enemy';
    public const TYPE_LOOT = 'loot';
    public const TYPE_ELITE = 'elite';
    public const TYPE_SHOP = 'shop';
    public const TYPE_BOSS = 'boss';
    public const TYPE_UNKNOWN = 'unknown';
    public const TYPE_EMPTY = 'empty';

    // Positions
    public const POSITION_LEFT = 'left';
    public const POSITION_RIGHT = 'right';
    public const POSITION_CENTER = 'center';

    public function dungeon(): BelongsTo
    {
        return $this->belongsTo(Dungeon::class);
    }

    public function getContent(): array
    {
        return $this->content_json ?? [];
    }

    public function isEnemy(): bool
    {
        return $this->actual_type === self::TYPE_ENEMY;
    }

    public function isLoot(): bool
    {
        return $this->actual_type === self::TYPE_LOOT;
    }

    public function isElite(): bool
    {
        return $this->actual_type === self::TYPE_ELITE;
    }

    public function isShop(): bool
    {
        return $this->actual_type === self::TYPE_SHOP;
    }

    public function isBoss(): bool
    {
        return $this->actual_type === self::TYPE_BOSS;
    }

    public function isEmpty(): bool
    {
        return $this->actual_type === self::TYPE_EMPTY;
    }

    public function isDeceptive(): bool
    {
        return $this->displayed_type !== $this->actual_type;
    }

    public function markVisited(): void
    {
        $this->visited = true;
        $this->save();
    }

    public function markCompleted(): void
    {
        $this->completed = true;
        $this->save();
    }
}
