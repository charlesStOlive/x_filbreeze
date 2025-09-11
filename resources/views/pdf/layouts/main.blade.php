<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>@yield('title')</title>
    <link href="{{ $cssPath }}" rel="stylesheet">
    <style>
        :root {
            --font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body>
    <div class="pdf-wrapper pdf-content">

        @yield('content')
    </div>
</body>

</html>
