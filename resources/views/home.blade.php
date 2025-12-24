@extends('layouts.app')

@section('content')
<div class="text-center">
    <h1 class="text-4xl font-bold mb-2">DataBase Dungeon</h1>
    <p class="text-gray-400 mb-8">1v1 Dungeon Builder Battle</p>

    <div class="max-w-md mx-auto space-y-6">
        <!-- Create New Game -->
        <div class="bg-gray-800 p-6 rounded-lg">
            <h2 class="text-xl font-semibold mb-4">Create New Game</h2>
            <form action="{{ route('match.create') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <input
                        type="text"
                        name="name"
                        placeholder="Your name"
                        class="w-full px-4 py-2 bg-gray-700 rounded border border-gray-600 focus:border-blue-500 focus:outline-none"
                        required
                    >
                </div>
                <button
                    type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 active:scale-95 active:bg-blue-800 text-white font-bold py-3 px-6 rounded-lg transition-all duration-150 shadow-lg hover:shadow-xl"
                >
                    Create Game
                </button>
            </form>
        </div>

        <!-- How to Play -->
        <div class="bg-gray-800 p-6 rounded-lg text-left">
            <h2 class="text-xl font-semibold mb-4">How to Play</h2>
            <ol class="list-decimal list-inside space-y-2 text-gray-300">
                <li>Create a game and share the link with a friend</li>
                <li>Both players select 3 cards that affect opponent's dungeon</li>
                <li>Navigate through your opponent's dungeon</li>
                <li>Collect loot and gold from rooms</li>
                <li>Fight the final boss</li>
                <li>PvP battle determines the winner!</li>
            </ol>
        </div>
    </div>
</div>
@endsection
