<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>@yield('title')</title>
    @if ($hotReload ?? false)
        @vite('resources/css/pdf/theme.css')
    @else
        <link href="{{ $cssPath }}" rel="stylesheet">
    @endif
    <style>
        :root {
            --font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body>
    <div class="pdf-wrapper">

        @yield('content')
    </div>
</body>

</html>
