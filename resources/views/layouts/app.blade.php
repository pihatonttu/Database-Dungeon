<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DataBase Dungeon' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        @if(session('error'))
            <div class="bg-red-600 text-white px-4 py-2 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if(session('message'))
            <div class="bg-blue-600 text-white px-4 py-2 rounded mb-4">
                {{ session('message') }}
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
