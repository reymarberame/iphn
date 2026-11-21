<?php

/*
 * Vercel PHP entrypoint for BHN.
 *
 * The Vercel filesystem is read-only except for /tmp, so redirect Laravel's
 * writable runtime paths there before booting the framework.
 */
$tmp = '/tmp/bhn';

foreach ([
    $tmp,
    $tmp.'/views',
    $tmp.'/cache',
    $tmp.'/sessions',
    $tmp.'/logs',
] as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }
}

$paths = [
    'VIEW_COMPILED_PATH' => $tmp.'/views',
    'APP_CONFIG_CACHE' => $tmp.'/cache/config.php',
    'APP_EVENTS_CACHE' => $tmp.'/cache/events.php',
    'APP_PACKAGES_CACHE' => $tmp.'/cache/packages.php',
    'APP_ROUTES_CACHE' => $tmp.'/cache/routes.php',
    'APP_SERVICES_CACHE' => $tmp.'/cache/services.php',
];

foreach ($paths as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

/*
 * Vercel invokes this file as /api/index.php, which makes Symfony treat /api
 * as the base path. Mount it at the domain root so Laravel sees real URIs.
 */
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__.'/../public/index.php';
