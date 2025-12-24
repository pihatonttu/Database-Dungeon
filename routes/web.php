<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\MatchController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::get('/', [AccountController::class, 'loginForm'])->name('login');
Route::post('/login', [AccountController::class, 'login'])->name('login.submit');
Route::post('/logout', [AccountController::class, 'logout'])->name('logout');

// Dashboard
Route::get('/dashboard', [AccountController::class, 'dashboard'])->name('dashboard');

// Legacy home route (redirect to dashboard)
Route::get('/home', fn() => redirect()->route('dashboard'))->name('home');

// Match creation and joining
Route::post('/matches', [MatchController::class, 'create'])->name('match.create');
Route::get('/matches/{matchId}/join', [MatchController::class, 'joinForm'])->name('match.join.form');
Route::post('/matches/{matchId}/join', [MatchController::class, 'join'])->name('match.join');

// Lobby
Route::get('/matches/{matchId}/lobby', [MatchController::class, 'lobby'])->name('match.lobby');

// Hero selection
Route::get('/matches/{matchId}/hero', [MatchController::class, 'heroSelect'])->name('match.hero');
Route::post('/matches/{matchId}/hero', [MatchController::class, 'submitHeroSelect'])->name('match.hero.submit');

// Setup (card selection)
Route::get('/matches/{matchId}/setup', [MatchController::class, 'setup'])->name('match.setup');
Route::post('/matches/{matchId}/setup', [MatchController::class, 'submitSetup'])->name('match.setup.submit');

// Dungeon
Route::get('/matches/{matchId}/dungeon', [MatchController::class, 'dungeon'])->name('match.dungeon');
Route::post('/matches/{matchId}/rooms/{roomId}/enter', [MatchController::class, 'enterRoom'])->name('match.room.enter');
Route::get('/matches/{matchId}/rooms/{roomId}', [MatchController::class, 'room'])->name('match.room');
Route::post('/matches/{matchId}/rooms/{roomId}/action', [MatchController::class, 'roomAction'])->name('match.room.action');

// Inventory management
Route::post('/matches/{matchId}/equip', [MatchController::class, 'equipItem'])->name('match.equip');
Route::post('/matches/{matchId}/unequip', [MatchController::class, 'unequipItem'])->name('match.unequip');
Route::post('/matches/{matchId}/use-item', [MatchController::class, 'useItem'])->name('match.use_item');
Route::post('/matches/{matchId}/sell-item', [MatchController::class, 'sellItem'])->name('match.sell_item');
Route::post('/matches/{matchId}/drop-item', [MatchController::class, 'dropItem'])->name('match.drop_item');
Route::post('/matches/{matchId}/swap-bonus-loot', [MatchController::class, 'swapBonusLoot'])->name('match.swap-bonus-loot');
Route::post('/matches/{matchId}/discard-bonus-loot', [MatchController::class, 'discardBonusLoot'])->name('match.discard-bonus-loot');

// Combat
Route::get('/matches/{matchId}/combat/{combatId}', [MatchController::class, 'combat'])->name('combat');
Route::post('/matches/{matchId}/combat/{combatId}/attack', [MatchController::class, 'combatAttack'])->name('combat.attack');
Route::post('/matches/{matchId}/combat/{combatId}/use-item', [MatchController::class, 'combatUseItem'])->name('combat.use_item');
Route::post('/matches/{matchId}/combat/{combatId}/flee', [MatchController::class, 'combatFlee'])->name('combat.flee');

// Scout (reveal room)
Route::post('/matches/{matchId}/rooms/{roomId}/scout', [MatchController::class, 'scoutRoom'])->name('match.room.scout');

// PvP (turn-based)
Route::get('/matches/{matchId}/pvp', [MatchController::class, 'pvp'])->name('match.pvp');
Route::get('/matches/{matchId}/pvp/battle/{pvpId}', [MatchController::class, 'pvpBattle'])->name('pvp.battle');
Route::post('/matches/{matchId}/pvp/battle/{pvpId}/attack', [MatchController::class, 'pvpAttack'])->name('pvp.attack');

// API for polling
Route::get('/matches/{matchId}/state', [MatchController::class, 'state'])->name('match.state');
