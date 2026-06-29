<?php

namespace App\Enums;

class AssetStatus
{
    const ACTIVE = 'Active';
    const SPARE = 'Spare';
    const DEFECTIVE = 'Defective';
    const FOR_REPAIR = 'For Repair';
    const FOR_DISPOSAL = 'For Disposal';
    const SCRAPPED = 'Scrapped';

    const ALL = [
        self::ACTIVE,
        self::SPARE,
        self::DEFECTIVE,
        self::FOR_REPAIR,
        self::FOR_DISPOSAL,
        self::SCRAPPED,
    ];

    const LOCKED = [
        self::SCRAPPED,
        self::FOR_DISPOSAL,
    ];
}
