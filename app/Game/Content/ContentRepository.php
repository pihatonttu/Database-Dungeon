<?php

namespace App\Game\Content;

class ContentRepository
{
    private string $version;
    private ?array $cards = null;
    private ?array $enemies = null;
    private ?array $loot = null;
    private ?array $rules = null;
    private ?array $heroes = null;

    public function __construct(string $version = 'v0.1.0')
    {
        $this->version = $version;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getCards(): array
    {
        if ($this->cards === null) {
            $this->cards = $this->loadJson('cards.json');
        }
        return $this->cards['cards'] ?? [];
    }

    public function getCard(string $id): ?array
    {
        foreach ($this->getCards() as $card) {
            if ($card['id'] === $id) {
                return $card;
            }
        }
        return null;
    }

    public function getEnemies(): array
    {
        if ($this->enemies === null) {
            $this->enemies = $this->loadJson('enemies.json');
        }
        return $this->enemies['enemies'] ?? [];
    }

    public function getEnemy(string $id): ?array
    {
        foreach ($this->getEnemies() as $enemy) {
            if ($enemy['id'] === $id) {
                return $enemy;
            }
        }
        return null;
    }

    public function getEnemiesByTier(string $tier): array
    {
        return array_filter($this->getEnemies(), fn($e) => $e['tier'] === $tier);
    }

    public function getRandomEnemy(string $tier = 'common'): ?array
    {
        $enemies = array_values($this->getEnemiesByTier($tier));
        if (empty($enemies)) {
            return null;
        }
        return $enemies[array_rand($enemies)];
    }

    public function getLoot(): array
    {
        if ($this->loot === null) {
            $this->loot = $this->loadJson('loot.json');
        }
        return $this->loot;
    }

    public function getItems(): array
    {
        return $this->getLoot()['items'] ?? [];
    }

    public function getItem(string $id): ?array
    {
        foreach ($this->getItems() as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }
        return null;
    }

    public function getLootTable(string $tableId): ?array
    {
        return $this->getLoot()['loot_tables'][$tableId] ?? null;
    }

    public function rollLoot(string $tableId): ?array
    {
        $table = $this->getLootTable($tableId);
        if (!$table) {
            return null;
        }

        $items = $table['items'];
        $weights = $table['weights'];
        $totalWeight = array_sum($weights);
        $roll = rand(1, $totalWeight);

        $cumulative = 0;
        foreach ($items as $index => $itemId) {
            $cumulative += $weights[$index];
            if ($roll <= $cumulative) {
                return $this->getItem($itemId);
            }
        }

        return null;
    }

    public function getRules(): array
    {
        if ($this->rules === null) {
            $this->rules = $this->loadJson('rules.json');
        }
        return $this->rules;
    }

    // Hero methods
    public function getHeroes(): array
    {
        if ($this->heroes === null) {
            $this->heroes = $this->loadJson('heroes.json');
        }
        return $this->heroes['heroes'] ?? [];
    }

    public function getHero(string $id): ?array
    {
        foreach ($this->getHeroes() as $hero) {
            if ($hero['id'] === $id) {
                return $hero;
            }
        }
        return null;
    }

    public function getCardPools(): array
    {
        if ($this->heroes === null) {
            $this->heroes = $this->loadJson('heroes.json');
        }
        return $this->heroes['card_pools'] ?? [];
    }

    /**
     * Generate available cards for a hero based on their card pool config
     */
    public function generateAvailableCards(string $heroId): array
    {
        $hero = $this->getHero($heroId);
        if (!$hero) {
            return [];
        }

        $cardPool = $hero['card_pool'] ?? [];
        $allCards = $this->getCards();
        $cardPools = $this->getCardPools();
        $availableCards = [];

        // Add guaranteed cards
        $guaranteed = $cardPool['guaranteed'] ?? [];
        foreach ($guaranteed as $cardId) {
            $card = $this->getCard($cardId);
            if ($card) {
                $availableCards[$cardId] = $card;
            }
        }

        // Add random cards from specified pools
        $randomFrom = $cardPool['random_from'] ?? [];
        $randomCount = $cardPool['random_count'] ?? 0;

        // Collect all cards from the specified pools
        $poolCards = [];
        foreach ($randomFrom as $poolName) {
            $pool = $cardPools[$poolName] ?? null;
            if ($pool && isset($pool['cards'])) {
                foreach ($pool['cards'] as $cardId) {
                    // Don't add duplicates or already guaranteed cards
                    if (!isset($availableCards[$cardId]) && !isset($poolCards[$cardId])) {
                        $card = $this->getCard($cardId);
                        if ($card) {
                            $poolCards[$cardId] = $card;
                        }
                    }
                }
            }
        }

        // Randomly select cards from the pool
        $poolCardIds = array_keys($poolCards);
        shuffle($poolCardIds);
        $selectedCount = min($randomCount, count($poolCardIds));

        for ($i = 0; $i < $selectedCount; $i++) {
            $cardId = $poolCardIds[$i];
            $availableCards[$cardId] = $poolCards[$cardId];
        }

        return array_values($availableCards);
    }

    public function getRule(string $path): mixed
    {
        $rules = $this->getRules();
        $keys = explode('.', $path);

        $value = $rules;
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    private function loadJson(string $filename): array
    {
        $path = storage_path("app/content/{$this->version}/{$filename}");

        if (!file_exists($path)) {
            throw new \RuntimeException("Content file not found: {$path}");
        }

        $content = file_get_contents($path);
        return json_decode($content, true) ?? [];
    }
}
