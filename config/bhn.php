<?php

return [
    // Demo shortcuts are only ever exposed by LoginController in the local
    // environment, and only when this flag is explicitly enabled.
    'show_demo_accounts' => env('BHN_SHOW_DEMO_ACCOUNTS', false),

    'demo_password' => env('BHN_DEMO_PASSWORD'),

    'demo_accounts' => [
        ['email' => 'superadmin@bhn.test', 'name' => 'Network Super Administrator', 'role' => 'Super Administrator', 'context' => 'Network scope'],
        ['email' => 'hospitaladmin@bhn.test', 'name' => 'Bukidnon Provincial Medical Center Admin', 'role' => 'Hospital Administrator', 'context' => 'Bukidnon Provincial Medical Center'],
        ['email' => 'bedmanager@bhn.test', 'name' => 'BPMC Bed Manager', 'role' => 'Hospital Bed Manager', 'context' => 'Bukidnon Provincial Medical Center'],
        ['email' => 'referral@bhn.test', 'name' => 'BPMC Hospital Staff', 'role' => 'Hospital Staff', 'context' => 'Bukidnon Provincial Medical Center'],
        ['email' => 'admission@vcmc.bhn.test', 'name' => 'VCMC Hospital Staff', 'role' => 'Hospital Staff', 'context' => 'Valencia City Medical Center'],
    ],

    'disclaimer' => 'Bed availability is manually reported by each hospital and is not a confirmed admission. Only an authorized receiving-hospital user can accept a referral and reserve a bed.',

    'freshness' => [
        'fresh_minutes' => 15,
        'aging_minutes' => 60,
    ],

    'reservation_ttl_minutes' => 120,

    'referral_document_max_kb' => 10240,

    // "local" keeps files under storage/app/private; "supabase" stores them in
    // Supabase Storage through the S3-compatible disk defined in filesystems.php.
    'referral_documents_disk' => env('REFERRAL_DOCUMENTS_DISK', 'local'),

    // Browser-side Reverb settings shared through Inertia. Leave the client
    // host/port/scheme empty in production to reuse the request host (which
    // also works behind Railway's HTTPS proxy).
    'reverb' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_CLIENT_HOST'),
        'port' => env('REVERB_CLIENT_PORT'),
        'scheme' => env('REVERB_CLIENT_SCHEME'),
    ],
];
