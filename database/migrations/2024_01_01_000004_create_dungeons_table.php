<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dungeons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->uuid('owner_player_id');  // Who created this dungeon (via cards)
            $table->uuid('target_player_id'); // Who plays this dungeon

            $table->integer('seed');
            $table->json('structure_json')->nullable(); // Generated room layout
            $table->json('modifiers_json')->nullable(); // Active card effects

            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('game_matches')->onDelete('cascade');
            $table->foreign('owner_player_id')->references('id')->on('players')->onDelete('cascade');
            $table->foreign('target_player_id')->references('id')->on('players')->onDelete('cascade');

            $table->index('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dungeons');
    }
};
