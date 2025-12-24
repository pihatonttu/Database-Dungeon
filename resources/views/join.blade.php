@extends('layouts.app')

@section('content')
<div class="text-center">
    <h1 class="text-3xl font-bold mb-2">Join Game</h1>
    <p class="text-gray-400 mb-6">You've been invited to a dungeon battle!</p>

    <div class="bg-gray-800 p-6 rounded-lg max-w-md mx-auto">
        <form action="{{ route('match.join', $match->id) }}" method="POST">
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
                class="w-full bg-green-600 hover:bg-green-700 active:scale-95 active:bg-green-800 text-white font-bold py-3 px-6 rounded-lg transition-all duration-150 shadow-lg hover:shadow-xl"
            >
                Join Game
            </button>
        </form>
    </div>
</div>
@endsection
