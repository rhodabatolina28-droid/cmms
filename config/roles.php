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
        'supply_officer',
        'it',
        'user',
    ],

    'labels' => [
        'super_admin'    => 'Super Admin (IT)',
        'admin'          => 'Division Admin',
        'supply_officer' => 'Supply Officer / Admin (Administrative Div.)',
        'it'             => 'IT Personnel',
        'user'           => 'End User',
    ],
];
