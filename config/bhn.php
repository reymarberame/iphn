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
];
