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
];
