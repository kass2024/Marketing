<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voice replies (FAQ / bot → customer on WhatsApp)
    |--------------------------------------------------------------------------
    | When enabled, FAQ-sourced replies also send a short MP3 via OpenAI TTS.
    | Requires services.openai.key and a public APP_URL for media links.
    */
    'voice_faq_replies' => filter_var(env('CHATBOT_VOICE_FAQ_REPLIES', 'false'), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Inbound voice (customer → bot)
    |--------------------------------------------------------------------------
    | Transcribe WhatsApp audio with OpenAI Whisper before FAQ / AI routing.
    */
    'transcribe_inbound_audio' => filter_var(env('CHATBOT_TRANSCRIBE_AUDIO', 'true'), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Which AIEngine "source" values may attach TTS audio (when voice_faq_replies is true)
    |--------------------------------------------------------------------------
    | Comma-separated. Include grounded_ai, ai, pure_ai, greeting so all AIEngine paths can attach TTS when enabled.
    | If you set CHATBOT_VOICE_SOURCES in .env, add pure_ai (and any other sources you use) or TTS will be skipped.
    */
    'voice_reply_sources' => array_values(array_filter(array_map('trim', explode(',', env(
        'CHATBOT_VOICE_SOURCES',
        'direct_match,similar_question,keyword_match,faq_token_overlap,semantic_match,grounded_ai,ai,pure_ai,greeting'
    ))))),

];
