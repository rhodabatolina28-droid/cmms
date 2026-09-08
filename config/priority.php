<?php

/**
 * D4a — High Official priority config (NCMB).
 *
 * Locked decisions (Sept 2026):
 *  - Detection = case-insensitive KEYWORD matching against users.position.
 *    NO new column, NO rank table — position text is the single source.
 *  - Full-phrase keywords only (never a bare word like "Director") so
 *    "Director's Secretary" or "Programmer" can never match by accident.
 *  - Official list: ED IV · Deputy ED IV (OIC variants) · Director II
 *    (Technical/Internal Services) · Chief/OIC Chief of CMD · WRED · VAD ·
 *    OED · AD · FMD · RID · State Auditor III (COA — INCLUDED per user).
 *  - Guardrail: position is editable ONLY by Super Admin (User Management)
 *    and Department Admin (Personnel Management). Self-service profile is
 *    read-only for position — self-inflation must be impossible.
 */
return [
    'high_official_keywords' => [
        'executive director',   // OIC-ED IV, OIC Deputy ED IV, Deputy ED IV
        'director ii',          // Director II, Technical / Internal Services
        'chief',                // Chief / OIC Chief of the six divisions
        'state auditor',        // State Auditor III (COA)
    ],

    /**
     * D4c — Position catalog for the Create/Edit System Account dropdowns.
     * Options are filtered by the cascade: Department → Division → Position.
     *  - 'always'      : visible regardless of selection (top officials)
     *  - 'by_department': visible when that Department is selected
     *  - 'by_office'    : visible when that Division/Office is selected
     * The dropdown ALSO has "— None / Not set —" (position unknown — still
     * creatable) and "— Other / Not Listed —" (free text for regular staff,
     * e.g. "Computer Programmer I" — never matches high_official_keywords).
     */
    'position_catalog' => [
        'always' => [
            'OIC-Executive Director IV',
            'OIC Deputy Executive Director IV',
            'Deputy Executive Director IV',
        ],
        'by_department' => [
            'TECHNICAL SERVICES DEPARTMENT' => ['Director II, Technical Services'],
            'INTERNAL SERVICES DEPARTMENT'  => ['Director II, Internal Services'],
        ],
        'by_office' => [
            'RESEARCH AND INFORMATION DIVISION'        => ['Chief, RID', 'OIC, Chief, RID'],
            'CONCILIATION AND MEDIATION DIVISION'      => ['Chief, CMD', 'OIC, Chief, CMD'],
            'VOLUNTARY ARBITRATION DIVISION'           => ['Chief, VAD', 'OIC, Chief, VAD'],
            'WORKPLACE RELATIONS ENHANCEMENT DIVISION' => ['Chief, WRED', 'OIC, Chief, WRED'],
            'ADMINISTRATIVE DIVISION'                  => ['Chief, AD', 'OIC, Chief, AD'],
            'FINANCIAL AND MANAGEMENT DIVISION'        => ['Chief, FMD', 'OIC, Chief, FMD'],
            'COMMISSION ON AUDIT'                      => ['State Auditor III'],
        ],
    ],
];
