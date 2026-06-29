<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Centralized CMMS Roles Configuration
    |--------------------------------------------------------------------------
    |
    | Defines the list of authorized roles within the NCMB CMMS portal.
    |
    */

    'list' => [
        'super_admin',
        'admin',
        'it',
        'user',
    ],

    'labels' => [
        'super_admin'    => 'Super Admin (IT)',
        'admin'          => 'Division Admin',
        'it'             => 'IT Personnel',
        'user'           => 'End User',
    ],
];
