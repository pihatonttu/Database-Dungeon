<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('content_version')->default('v0.1.0');
            $table->string('state')->default('lobby'); // lobby, setup, running, pvp, finished
            $table->uuid('winner_player_id')->nullable();
            $table->timestamps();

            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_matches');
    }
};
