<!DOCTYPE html>
<html lang="{{ evo()->getConfig('manager_language') }}">
<head>
    <meta charset="utf-8">
    <title>EspoCRM</title>
    <style>
        body { margin: 0; }
        iframe { width: 100%; height: 100vh; border: none; }
    </style>
</head>
<body>
    <iframe src="{{ $espocrmUrl }}"></iframe>
</body>
</html>