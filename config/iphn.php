<?php

return [
    'demo_password' => env('IPHN_DEMO_PASSWORD', 'Password@123'),

    'demo_accounts' => [
        ['email' => 'superadmin@iphn.test', 'name' => 'Network Super Administrator', 'role' => 'Super Administrator', 'context' => 'National scope'],
        ['email' => 'network@iphn.test', 'name' => 'National Network Administrator', 'role' => 'National Network Administrator', 'context' => 'National scope'],
        ['email' => 'region11@iphn.test', 'name' => 'Region XI Administrator', 'role' => 'Regional Administrator', 'context' => 'Region XI'],
        ['email' => 'hospitaladmin@iphn.test', 'name' => 'Davao Regional Medical Center Admin', 'role' => 'Hospital Administrator', 'context' => 'Davao Regional Medical Center'],
        ['email' => 'bedmanager@iphn.test', 'name' => 'DRMC Bed Manager', 'role' => 'Hospital Bed Manager', 'context' => 'Davao Regional Medical Center'],
        ['email' => 'erstaff@iphn.test', 'name' => 'DRMC ER Staff', 'role' => 'ER Staff', 'context' => 'Davao Regional Medical Center'],
        ['email' => 'referral@iphn.test', 'name' => 'DRMC Referral Coordinator', 'role' => 'Referral Coordinator', 'context' => 'Davao Regional Medical Center'],
        ['email' => 'admission@iphn.test', 'name' => 'SPMC Admission Staff', 'role' => 'Admission Staff', 'context' => 'Southern Philippines Medical Center'],
    ],

    'disclaimer' => 'Bed availability is manually reported by each hospital and is not a confirmed admission. Only an authorized receiving-hospital user can accept a referral and reserve a bed.',

    'freshness' => [
        'fresh_minutes' => 15,
        'aging_minutes' => 60,
    ],

    'reservation_ttl_minutes' => 120,

    'referral_document_max_kb' => 10240,
];
