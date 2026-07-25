<?php
/**
 * Database configuration — Sabeel Us Salaam Result Management System
 *
 * IMPORTANT (Hostinger):
 * Set db_pass to your real MySQL password between the quotes.
 * Do NOT leave the placeholder text.
 */
return [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'u917534606_results',
    'db_user' => 'u917534606_resultspanel',
    'db_pass' => 'Mohsin1016*', // REQUIRED: put Hostinger MySQL password here (install will fail if empty)
    'db_charset' => 'utf8mb4',

    'app_name' => 'Sabeel Us Salaam Online',
    'app_tagline' => 'Result Management System',
    'app_address' => 'Shaheen Bagh, Okhla, New Delhi 110025',

    'base_url' => '/student-portal',

    'grades' => [
        ['min' => 90, 'max' => 100, 'grade' => 'A1'],
        ['min' => 80, 'max' => 89,  'grade' => 'A2'],
        ['min' => 70, 'max' => 79,  'grade' => 'B1'],
        ['min' => 60, 'max' => 69,  'grade' => 'B2'],
        ['min' => 50, 'max' => 59,  'grade' => 'C1'],
        ['min' => 40, 'max' => 49,  'grade' => 'C2'],
    ],
    'pass_percentage' => 40,
];
