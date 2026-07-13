<?php

/**
 * Durable WhatsApp Business Manager directory facts.
 *
 * These never wipe when Meta Graph rate-limits (#80008 / #4) or returns empty.
 * Graph results always win when they include a real phone number ID / richer name.
 *
 * Live Graph debug (2026-07-13):
 * - 2185384198950246 was a WRONG id (typo). Correct id is 2185384198958246 —
 *   Graph returns name "Parrot Canada Visa Consultant Company Ltd" and phones [].
 * - 731320686199458 is visible in Meta BM as "parrot Canada visa consultant"
 *   with Connected number +1 450-367-5329 (WhatsApp Business App), but Graph
 *   often returns #80008 on that WABA until Meta cools down.
 */
return [

    /**
     * Remap corrupted / truncated linked ids → canonical WABA ids.
     *
     * @var array<string, string>
     */
    'id_aliases' => [
        '2185384198950246' => '2185384198958246',
    ],

    /**
     * Floor names + phones (display) shown in BM / Ad Studio when Graph is blank.
     * Phone rows without a Meta phone_number_id use a durable synthetic id
     * (display:…) that is upgraded automatically when Graph later returns the real id.
     *
     * @var array<string, array{name: string, phones?: array<int, array<string, mixed>>}>
     */
    'seeds' => [
        '731320686199458' => [
            'name' => 'parrot Canada visa consultant',
            'ownership_type' => 'whatsapp_business_app',
            'phones' => [
                [
                    'display_phone_number' => '+1 450-367-5329',
                    'verified_name' => 'parrot Canada visa consultant',
                    'status' => 'CONNECTED',
                    'verified' => true,
                    'platform_type' => 'NOT_APPLICABLE',
                    'code_verification_status' => 'VERIFIED',
                ],
            ],
        ],
        '2185384198958246' => [
            'name' => 'Parrot Canada Visa Consultant Company Ltd',
            'ownership_type' => 'SELF',
            'phones' => [
                // Graph currently returns phones: [] for this WABA — name seed only.
            ],
        ],
    ],
];
