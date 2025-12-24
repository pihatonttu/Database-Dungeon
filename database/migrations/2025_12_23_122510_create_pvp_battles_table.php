<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pvp_battles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('game_matches')->cascadeOnDelete();

            // Player 1
            $table->foreignUuid('player1_id')->constrained('players')->cascadeOnDelete();
            $table->integer('player1_hp');
            $table->integer('player1_max_hp');
            $table->integer('player1_attack');
            $table->integer('player1_attack_variance')->default(0);
            $table->integer('player1_defense');
            $table->integer('player1_crit_chance')->default(5);

            // Player 2
            $table->foreignUuid('player2_id')->constrained('players')->cascadeOnDelete();
            $table->integer('player2_hp');
            $table->integer('player2_max_hp');
            $table->integer('player2_attack');
            $table->integer('player2_attack_variance')->default(0);
            $table->integer('player2_defense');
            $table->integer('player2_crit_chance')->default(5);

            // Battle state
            $table->foreignUuid('current_turn_player_id')->constrained('players');
            $table->integer('turn')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('winner_player_id')->nullable()->constrained('players');

            // Combat log
            $table->json('combat_log')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pvp_battles');
    }
};
