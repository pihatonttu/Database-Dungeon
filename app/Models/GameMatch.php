<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMatch extends Model
{
    use HasUuids;

    protected $table = 'game_matches';

    protected $fillable = [
        'content_version',
        'state',
        'is_public',
        'winner_player_id',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    // Match states
    public const STATE_LOBBY = 'lobby';
    public const STATE_SETUP = 'setup';
    public const STATE_RUNNING = 'running';
    public const STATE_PVP = 'pvp';
    public const STATE_FINISHED = 'finished';

    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'match_id');
    }

    public function dungeons(): HasMany
    {
        return $this->hasMany(Dungeon::class, 'match_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class, 'match_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    public function isLobby(): bool
    {
        return $this->state === self::STATE_LOBBY;
    }

    public function isSetup(): bool
    {
        return $this->state === self::STATE_SETUP;
    }

    public function isRunning(): bool
    {
        return $this->state === self::STATE_RUNNING;
    }

    public function isPvp(): bool
    {
        return $this->state === self::STATE_PVP;
    }

    public function isFinished(): bool
    {
        return $this->state === self::STATE_FINISHED;
    }

    public function isFull(): bool
    {
        return $this->players()->count() >= 2;
    }
}
