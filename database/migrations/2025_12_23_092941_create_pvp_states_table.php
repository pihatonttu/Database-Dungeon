<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pvp_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('game_matches')->onDelete('cascade');
            $table->foreignUuid('player_id')->constrained()->onDelete('cascade');

            // Opponent snapshot (their stats when dungeon completed)
            $table->string('opponent_name');
            $table->integer('opponent_level')->default(1);
            $table->integer('opponent_hp');
            $table->integer('opponent_current_hp');
            $table->integer('opponent_attack');
            $table->integer('opponent_attack_variance')->default(2);
            $table->integer('opponent_defense');
            $table->integer('opponent_crit_chance')->default(5);
            $table->json('opponent_equipment_json')->nullable();

            // Battle state
            $table->integer('turn')->default(1);
            $table->boolean('player_used_item')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('player_won')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pvp_states');
    }
};
