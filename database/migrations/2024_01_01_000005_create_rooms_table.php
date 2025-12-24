<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dungeon_id');

            $table->integer('level'); // 1-6 (taso dungeon-rakenteessa)
            $table->string('position'); // 'left', 'right', 'center'

            // Room type - what player SEES
            $table->string('displayed_type'); // enemy, loot, elite, shop, boss, unknown

            // Room type - what it ACTUALLY is (may differ due to cards like Ambush)
            $table->string('actual_type');

            // Room content details
            $table->json('content_json')->nullable(); // Enemy stats, loot items, shop inventory, etc.

            // State
            $table->boolean('visited')->default(false);
            $table->boolean('completed')->default(false);

            $table->timestamps();

            $table->foreign('dungeon_id')->references('id')->on('dungeons')->onDelete('cascade');

            $table->unique(['dungeon_id', 'level', 'position']);
            $table->index('dungeon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
