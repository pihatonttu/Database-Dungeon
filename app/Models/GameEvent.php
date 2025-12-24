<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'match_id',
        'player_id',
        'action',
        'payload_json',
        'created_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];

    // Event actions
    public const ACTION_CARD_SELECTED = 'card_selected';
    public const ACTION_ROOM_ENTERED = 'room_entered';
    public const ACTION_COMBAT = 'combat';
    public const ACTION_LOOT_COLLECTED = 'loot_collected';
    public const ACTION_SHOP_PURCHASE = 'shop_purchase';
    public const ACTION_DUNGEON_COMPLETED = 'dungeon_completed';
    public const ACTION_PVP_RESULT = 'pvp_result';

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function getPayload(): array
    {
        return $this->payload_json ?? [];
    }

    public static function record(Player $player, string $action, array $payload = []): self
    {
        return self::create([
            'match_id' => $player->match_id,
            'player_id' => $player->id,
            'action' => $action,
            'payload_json' => $payload,
            'created_at' => now(),
        ]);
    }
}
