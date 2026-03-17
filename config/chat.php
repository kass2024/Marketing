<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Routing Priority
    |--------------------------------------------------------------------------
    | Order of agents for escalation (level 1 → level 2 → ...)
    */
    'agent_priority' => explode(',', env('CHAT_AGENT_PRIORITY', '4,5')),

    /*
    |--------------------------------------------------------------------------
    | Agent Response Timeout (seconds)
    |--------------------------------------------------------------------------
    | After this time → reassign to next agent
    */
    'agent_timeout' => env('CHAT_AGENT_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Bot Fallback Timeout (seconds)
    |--------------------------------------------------------------------------
    | After this time → return conversation to AI
    */
    'bot_fallback_timeout' => env('CHAT_BOT_FALLBACK_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Enable Auto Fallback to Bot
    |--------------------------------------------------------------------------
    */
    'enable_bot_fallback' => env('CHAT_ENABLE_BOT_FALLBACK', true),

    /*
    |--------------------------------------------------------------------------
    | Enable Agent Reassignment
    |--------------------------------------------------------------------------
    */
    'enable_agent_reassignment' => env('CHAT_ENABLE_AGENT_REASSIGNMENT', true),

    /*
    |--------------------------------------------------------------------------
    | Default Escalation Level
    |--------------------------------------------------------------------------
    */
    'default_escalation_level' => 1,

];