<?php

namespace App\Game\Engine;

use App\Game\Content\ContentRepository;
use App\Models\Player;
use App\Models\GameMatch;

class PvpSimulator
{
    private ContentRepository $content;

    public function __construct(ContentRepository $content)
    {
        $this->content = $content;
    }

    /**
     * Simulate PvP between two players
     * Returns the winner and battle details
     */
    public function simulate(GameMatch $match): array
    {
        $players = $match->players()->get();

        if ($players->count() !== 2) {
            throw new \RuntimeException('Match must have exactly 2 players for PvP');
        }

        $playerA = $players[0];
        $playerB = $players[1];

        $scoreA = $this->calculatePowerScore($playerA);
        $scoreB = $this->calculatePowerScore($playerB);

        // Add random factor
        $rules = $this->content->getRules();
        $randomFactor = $rules['pvp']['random_factor'] ?? 10;

        $finalScoreA = $scoreA + rand(0, $randomFactor);
        $finalScoreB = $scoreB + rand(0, $randomFactor);

        $winner = $finalScoreA >= $finalScoreB ? $playerA : $playerB;
        $loser = $finalScoreA >= $finalScoreB ? $playerB : $playerA;

        return [
            'winner_id' => $winner->id,
            'winner_name' => $winner->name,
            'loser_id' => $loser->id,
            'loser_name' => $loser->name,
            'scores' => [
                $playerA->id => [
                    'base_score' => $scoreA,
                    'final_score' => $finalScoreA,
                    'breakdown' => $this->getScoreBreakdown($playerA),
                ],
                $playerB->id => [
                    'base_score' => $scoreB,
                    'final_score' => $finalScoreB,
                    'breakdown' => $this->getScoreBreakdown($playerB),
                ],
            ],
            'margin' => abs($finalScoreA - $finalScoreB),
        ];
    }

    /**
     * Calculate a player's power score for PvP
     */
    public function calculatePowerScore(Player $player): int
    {
        $rules = $this->content->getRules();
        $pvpRules = $rules['pvp'];

        $hpWeight = $pvpRules['hp_weight'] ?? 1.0;
        $xpWeight = $pvpRules['xp_weight'] ?? 0.5;
        $lootWeight = $pvpRules['loot_power_weight'] ?? 2.0;

        $hpScore = $player->current_hp * $hpWeight;
        $xpScore = $player->xp * $xpWeight;
        $lootScore = $this->calculateLootPower($player) * $lootWeight;

        return (int) round($hpScore + $xpScore + $lootScore);
    }

    private function calculateLootPower(Player $player): int
    {
        $loot = $player->getLoot();
        $totalPower = 0;

        foreach ($loot as $item) {
            $totalPower += $item['power'] ?? 0;
        }

        return $totalPower;
    }

    private function getScoreBreakdown(Player $player): array
    {
        $rules = $this->content->getRules();
        $pvpRules = $rules['pvp'];

        return [
            'hp' => [
                'value' => $player->current_hp,
                'weight' => $pvpRules['hp_weight'] ?? 1.0,
                'contribution' => (int) round($player->current_hp * ($pvpRules['hp_weight'] ?? 1.0)),
            ],
            'xp' => [
                'value' => $player->xp,
                'weight' => $pvpRules['xp_weight'] ?? 0.5,
                'contribution' => (int) round($player->xp * ($pvpRules['xp_weight'] ?? 0.5)),
            ],
            'loot' => [
                'value' => $this->calculateLootPower($player),
                'weight' => $pvpRules['loot_power_weight'] ?? 2.0,
                'contribution' => (int) round($this->calculateLootPower($player) * ($pvpRules['loot_power_weight'] ?? 2.0)),
            ],
        ];
    }
}
