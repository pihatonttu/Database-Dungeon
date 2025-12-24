<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->uuid('player_id');

            $table->string('action'); // card_selected, room_entered, combat, loot_collected, shop_purchase, pvp_result
            $table->json('payload_json');

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('match_id')->references('id')->on('game_matches')->onDelete('cascade');
            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');

            $table->index(['match_id', 'created_at']);
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
