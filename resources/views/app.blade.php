<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#014783">
    <meta name="description" content="Bukidnon Hospital Network — coordinated bed availability, referrals, and patient transfers across participating hospitals.">
    <title inertia>{{ config('app.name', 'BHN') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="/images/bukidnon-seal-64.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/bukidnon-seal-180.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
