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
            $table->string('hero_id')->nullable()->after('name');
            $table->integer('max_hp')->default(100)->after('current_level');
            $table->integer('attack')->default(10)->after('max_hp');
            $table->integer('attack_variance')->default(2)->after('attack');
            $table->integer('defense')->default(0)->after('attack_variance');
            $table->integer('crit_chance')->default(5)->after('defense');
            $table->json('available_cards_json')->nullable()->after('cards_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'hero_id',
                'max_hp',
                'attack',
                'attack_variance',
                'defense',
                'crit_chance',
                'available_cards_json',
            ]);
        });
    }
};
