@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2">DataBase Dungeon</h1>
            <p class="text-gray-400">Kirjaudu sisaan aloittaaksesi</p>
        </div>

        <div class="bg-gray-800 p-8 rounded-lg shadow-xl">
            @if(session('error'))
                <div class="bg-red-800 p-3 rounded mb-4 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('login.submit') }}" method="POST">
                @csrf
                <div class="mb-6">
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                        Pelaajan nimi
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Syota nimesi..."
                        class="w-full px-4 py-3 bg-gray-700 rounded-lg border border-gray-600 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 text-lg"
                        required
                        minlength="2"
                        maxlength="30"
                        autofocus
                    >
                    @error('name')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 active:scale-95 active:bg-blue-800 text-white font-bold py-3 px-6 rounded-lg transition-all duration-150 shadow-lg hover:shadow-xl text-lg"
                >
                    Kirjaudu / Luo tili
                </button>
            </form>

            <p class="text-gray-500 text-sm text-center mt-6">
                Jos nimeasi ei ole olemassa, luodaan uusi tili automaattisesti.
            </p>
        </div>
    </div>
</div>
@endsection
