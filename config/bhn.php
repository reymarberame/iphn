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

    // Supabase Realtime replaces the long-running Reverb server. Browser
    // clients only receive the publishable key and an unguessable topic.
    'realtime' => [
        'url' => env('SUPABASE_URL', 'https://aobqazeyvwwvqacfewla.supabase.co'),
        'publishable_key' => env('SUPABASE_PUBLISHABLE_KEY'),
        'secret_key' => env('SUPABASE_SECRET_KEY'),
    ],

    // Shared secret for the Vercel Cron endpoint that expires bed holds.
    'cron_secret' => env('CRON_SECRET'),

    // ICE servers for peer-to-peer voice/video calls. The public TURN relay is
    // a best-effort fallback for restrictive networks; set TURN_* to use your
    // own relay for production-grade reliability.
    'calls' => [
        'ice_servers' => [
            ['urls' => 'stun:stun.l.google.com:19302'],
            [
                'urls' => 'turn:openrelay.metered.ca:80',
                'username' => env('TURN_USERNAME', 'openrelayproject'),
                'credential' => env('TURN_CREDENTIAL', 'openrelayproject'),
            ],
            [
                'urls' => 'turn:openrelay.metered.ca:443',
                'username' => env('TURN_USERNAME', 'openrelayproject'),
                'credential' => env('TURN_CREDENTIAL', 'openrelayproject'),
            ],
            [
                'urls' => 'turn:openrelay.metered.ca:443?transport=tcp',
                'username' => env('TURN_USERNAME', 'openrelayproject'),
                'credential' => env('TURN_CREDENTIAL', 'openrelayproject'),
            ],
        ],
    ],
];
