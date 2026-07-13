<?php

return [
  'api_key' => trim((string) env('GOOGLE_AI_API_KEY', env('GEMINI_API_KEY')), " \t\"'"),
  'model' => env('GOOGLE_AI_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash')),
  'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.0-flash-preview-image-generation'),
  'tts_model' => env('GEMINI_TTS_MODEL', 'gemini-2.5-flash-preview-tts'),
  'tts_voice' => env('GEMINI_TTS_VOICE', 'Charon'),
  'stt_model' => env('GEMINI_STT_MODEL', 'gemini-2.5-flash'),
  'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
  'timeout' => (int) env('GEMINI_TIMEOUT', 60),
  'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 15),
  'image_timeout' => (int) env('GEMINI_IMAGE_TIMEOUT', 120),
  'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 8192),
  'retry_attempts' => (int) env('GEMINI_RETRY_ATTEMPTS', 3),
  'retry_delay_ms' => (int) env('GEMINI_RETRY_DELAY_MS', 1500),
];
