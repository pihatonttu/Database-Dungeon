<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->uuid('account_id')->nullable();
            $table->string('name');

            // Setup phase
            $table->json('cards_json')->nullable(); // Selected cards

            // Dungeon progress
            $table->integer('current_level')->default(0);
            $table->integer('current_hp')->default(100);
            $table->integer('gold')->default(0);
            $table->integer('xp')->default(0);
            $table->json('loot_json')->default('[]'); // Collected items

            // Completion tracking
            $table->boolean('setup_complete')->default(false);
            $table->timestamp('dungeon_completed_at')->nullable();

            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('game_matches')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');

            $table->index('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
