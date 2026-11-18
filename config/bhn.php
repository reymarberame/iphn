<?php

return [
    'demo_password' => env('BHN_DEMO_PASSWORD', 'Password@123'),

    'demo_accounts' => [
        ['email' => 'superadmin@bhn.test', 'name' => 'Network Super Administrator', 'role' => 'Super Administrator', 'context' => 'Network scope'],
        ['email' => 'network@bhn.test', 'name' => 'Bukidnon Network Administrator', 'role' => 'Network Administrator', 'context' => 'Network scope'],
        ['email' => 'region10@bhn.test', 'name' => 'Region X Administrator', 'role' => 'Regional Administrator', 'context' => 'Region X'],
        ['email' => 'province@bhn.test', 'name' => 'Bukidnon Provincial Administrator', 'role' => 'Provincial Administrator', 'context' => 'Bukidnon'],
        ['email' => 'hospitaladmin@bhn.test', 'name' => 'Bukidnon Provincial Medical Center Admin', 'role' => 'Hospital Administrator', 'context' => 'Bukidnon Provincial Medical Center'],
        ['email' => 'bedmanager@bhn.test', 'name' => 'BPMC Bed Manager', 'role' => 'Hospital Bed Manager', 'context' => 'Bukidnon Provincial Medical Center'],
        ['email' => 'erstaff@bhn.test', 'name' => 'BPMC ER Staff', 'role' => 'ER Staff', 'context' => 'Bukidnon Provincial Medical Center'],
        ['email' => 'referral@bhn.test', 'name' => 'BPMC Referral Coordinator', 'role' => 'Referral Coordinator', 'context' => 'Bukidnon Provincial Medical Center'],
        ['email' => 'admission@vcmc.bhn.test', 'name' => 'VCMC Admission Staff', 'role' => 'Admission Staff', 'context' => 'Valencia City Medical Center'],
    ],

    'disclaimer' => 'Bed availability is manually reported by each hospital and is not a confirmed admission. Only an authorized receiving-hospital user can accept a referral and reserve a bed.',

    'freshness' => [
        'fresh_minutes' => 15,
        'aging_minutes' => 60,
    ],

    'reservation_ttl_minutes' => 120,

    'referral_document_max_kb' => 10240,
];
