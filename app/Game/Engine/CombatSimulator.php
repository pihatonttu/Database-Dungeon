<?php

namespace App\Game\Engine;

use App\Game\Content\ContentRepository;
use App\Models\Player;

class CombatSimulator
{
    private ContentRepository $content;

    public function __construct(ContentRepository $content)
    {
        $this->content = $content;
    }

    /**
     * Simulate combat between player and enemy
     * Returns combat result with damage dealt to player, rewards, etc.
     */
    public function simulate(Player $player, array $enemy): array
    {
        $rules = $this->content->getRules();
        $combatRules = $rules['combat'];

        // Calculate player's combat stats
        $playerDamage = $this->calculatePlayerDamage($player, $combatRules);
        $playerDefense = $this->calculatePlayerDefense($player);

        // Enemy stats
        $enemyHp = $enemy['hp'] ?? 50;
        $enemyDamage = $enemy['damage'] ?? 15;

        // Simulate combat (simplified turn-based)
        $rounds = 0;
        $totalDamageToPlayer = 0;

        while ($enemyHp > 0 && $rounds < 10) {
            // Player attacks
            $enemyHp -= $playerDamage;

            if ($enemyHp > 0) {
                // Enemy attacks
                $damageToPlayer = max(1, $enemyDamage - $playerDefense);
                $totalDamageToPlayer += $damageToPlayer;
            }

            $rounds++;
        }

        // Check if player survived
        $playerSurvived = $player->current_hp > $totalDamageToPlayer;

        return [
            'success' => $enemyHp <= 0,
            'player_survived' => $playerSurvived,
            'damage_taken' => $totalDamageToPlayer,
            'rounds' => $rounds,
            'rewards' => $enemyHp <= 0 ? [
                'gold' => $enemy['gold_reward'] ?? 10,
                'xp' => $enemy['xp_reward'] ?? 5,
            ] : [],
            'enemy_name' => $enemy['name'] ?? 'Unknown',
        ];
    }

    private function calculatePlayerDamage(Player $player, array $combatRules): int
    {
        $baseDamage = $combatRules['player_base_damage'] ?? 20;
        $damagePerPower = $combatRules['damage_per_weapon_power'] ?? 2;

        $weaponPower = $this->getWeaponPower($player);

        return $baseDamage + ($weaponPower * $damagePerPower);
    }

    private function calculatePlayerDefense(Player $player): int
    {
        return $this->getArmorPower($player);
    }

    private function getWeaponPower(Player $player): int
    {
        $loot = $player->getLoot();
        $totalPower = 0;

        foreach ($loot as $item) {
            if (($item['type'] ?? '') === 'weapon') {
                $totalPower += $item['power'] ?? 0;
            }
        }

        return $totalPower;
    }

    private function getArmorPower(Player $player): int
    {
        $loot = $player->getLoot();
        $totalPower = 0;

        foreach ($loot as $item) {
            if (($item['type'] ?? '') === 'armor') {
                $totalPower += $item['power'] ?? 0;
            }
        }

        return $totalPower;
    }
}
