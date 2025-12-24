<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\GameMatch;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Show login page (or redirect to dashboard if logged in)
     */
    public function loginForm()
    {
        $accountId = session('account_id');

        if ($accountId && Account::find($accountId)) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle login/registration
     */
    public function login(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:30',
        ]);

        $name = trim($request->input('name'));

        // Try to find existing account with this name
        $account = Account::where('display_name', $name)->first();

        if (!$account) {
            // Create new account
            $account = Account::create([
                'display_name' => $name,
                'auth_provider' => 'simple',
            ]);
        }

        session(['account_id' => $account->id]);

        return redirect()->route('dashboard');
    }

    /**
     * Logout
     */
    public function logout()
    {
        session()->forget(['account_id', 'player_id']);

        return redirect()->route('login');
    }

    /**
     * Show dashboard with active games and open games
     */
    public function dashboard()
    {
        $accountId = session('account_id');

        if (!$accountId) {
            return redirect()->route('login');
        }

        $account = Account::find($accountId);

        if (!$account) {
            session()->forget('account_id');
            return redirect()->route('login');
        }

        // Get my active games (where I'm a player)
        $myGames = GameMatch::whereHas('players', function ($query) use ($accountId) {
            $query->where('account_id', $accountId);
        })
        ->whereIn('state', [
            GameMatch::STATE_LOBBY,
            GameMatch::STATE_SETUP,
            GameMatch::STATE_RUNNING,
            GameMatch::STATE_PVP,
        ])
        ->with(['players'])
        ->orderBy('updated_at', 'desc')
        ->get();

        // Get open public games waiting for players (not mine)
        $openGames = GameMatch::where('state', GameMatch::STATE_LOBBY)
            ->where('is_public', true)
            ->whereDoesntHave('players', function ($query) use ($accountId) {
                $query->where('account_id', $accountId);
            })
            ->whereRaw('(SELECT COUNT(*) FROM players WHERE players.match_id = game_matches.id) < 2')
            ->with(['players'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get recent finished games
        $finishedGames = GameMatch::whereHas('players', function ($query) use ($accountId) {
            $query->where('account_id', $accountId);
        })
        ->where('state', GameMatch::STATE_FINISHED)
        ->with(['players', 'winner'])
        ->orderBy('updated_at', 'desc')
        ->limit(5)
        ->get();

        // Calculate account statistics
        $stats = $this->calculateAccountStats($account);

        return view('dashboard', [
            'account' => $account,
            'myGames' => $myGames,
            'openGames' => $openGames,
            'finishedGames' => $finishedGames,
            'stats' => $stats,
        ]);
    }

    /**
     * Calculate account statistics
     */
    private function calculateAccountStats(Account $account): array
    {
        $players = $account->players()->with(['match'])->get();

        $finishedPlayers = $players->filter(fn($p) => $p->match && $p->match->state === GameMatch::STATE_FINISHED);

        $totalGames = $finishedPlayers->count();
        $wins = $finishedPlayers->filter(fn($p) => $p->match->winner_player_id === $p->id)->count();
        $losses = $totalGames - $wins;
        $winRate = $totalGames > 0 ? round(($wins / $totalGames) * 100) : 0;

        // Hero stats
        $heroStats = [];
        foreach ($finishedPlayers as $player) {
            if (!$player->hero_id) continue;

            if (!isset($heroStats[$player->hero_id])) {
                $heroStats[$player->hero_id] = ['games' => 0, 'wins' => 0];
            }
            $heroStats[$player->hero_id]['games']++;
            if ($player->match->winner_player_id === $player->id) {
                $heroStats[$player->hero_id]['wins']++;
            }
        }

        // Find favorite and best hero
        $favoriteHero = null;
        $bestHero = null;
        $maxGames = 0;
        $bestWinRate = 0;

        foreach ($heroStats as $heroId => $data) {
            if ($data['games'] > $maxGames) {
                $maxGames = $data['games'];
                $favoriteHero = $heroId;
            }
            $heroWinRate = $data['games'] > 0 ? ($data['wins'] / $data['games']) * 100 : 0;
            if ($data['games'] >= 2 && $heroWinRate > $bestWinRate) {
                $bestWinRate = $heroWinRate;
                $bestHero = $heroId;
            }
        }

        // Calculate current streak
        $streak = 0;
        $streakType = null;
        $recentGames = $finishedPlayers->sortByDesc(fn($p) => $p->match->updated_at);
        foreach ($recentGames as $player) {
            $won = $player->match->winner_player_id === $player->id;
            if ($streakType === null) {
                $streakType = $won ? 'win' : 'loss';
                $streak = 1;
            } elseif (($won && $streakType === 'win') || (!$won && $streakType === 'loss')) {
                $streak++;
            } else {
                break;
            }
        }

        return [
            'total_games' => $totalGames,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $winRate,
            'favorite_hero' => $favoriteHero,
            'best_hero' => $bestHero,
            'best_hero_win_rate' => round($bestWinRate),
            'hero_stats' => $heroStats,
            'streak' => $streak,
            'streak_type' => $streakType,
        ];
    }
}
