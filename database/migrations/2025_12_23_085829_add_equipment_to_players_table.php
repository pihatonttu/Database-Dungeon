<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->json('equipment_json')->nullable()->after('loot_json');
            $table->json('inventory_json')->nullable()->after('equipment_json');
            $table->integer('base_attack')->default(5)->after('xp');
            $table->integer('base_defense')->default(0)->after('base_attack');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['equipment_json', 'inventory_json', 'base_attack', 'base_defense']);
        });
    }
};
