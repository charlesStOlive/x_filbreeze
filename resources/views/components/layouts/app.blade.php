<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Aperçu Email' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 2rem;
            background-color: #f8f8f8;
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #333;
        }

        

        h1, h2, h3 {
            margin-top: 0;
        }

        code {
            background: #eee;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 90%;
        }
    </style>
</head>
<body>
    <div class="canvas">
        {{ $slot }}
    </div>
</body>
</html>
