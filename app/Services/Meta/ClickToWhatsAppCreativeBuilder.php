<?php

namespace App\Services\Meta;

class ClickToWhatsAppCreativeBuilder
{
    public const CTA_WHATSAPP_MESSAGE = 'WHATSAPP_MESSAGE';
    public const CTA_SEND_MESSAGE = 'SEND_MESSAGE';

    /**
     * Build Meta object_story_spec for Click-to-WhatsApp image/video ad.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @see https://developers.facebook.com/docs/marketing-api/ad-creative/messaging-ads/click-to-whatsapp/
     */
    public function buildObjectStorySpec(array $input): array
    {
        $pageId = (string) ($input['page_id'] ?? '');
        if ($pageId === '') {
            throw new \InvalidArgumentException('page_id is required for Click-to-WhatsApp creatives.');
        }

        $prefill = (string) ($input['whatsapp_prefill_message'] ?? '');

        // Phone for the conversation is on the ad set promoted_object. Creative CTA must be
        // WHATSAPP_MESSAGE with api.whatsapp.com/send — SEND_MESSAGE is Messenger-oriented
        // and fails for WhatsApp destination ad sets (often mislabeled as “permission”).
        $linkData = array_filter([
            'message' => $input['primary_text'] ?? $input['body'] ?? '',
            'name' => $input['headline'] ?? $input['name'] ?? '',
            'description' => $input['description'] ?? '',
            'image_hash' => $input['image_hash'] ?? null,
            'video_id' => $input['video_id'] ?? null,
            'link' => 'https://api.whatsapp.com/send',
            'call_to_action' => [
                'type' => self::CTA_WHATSAPP_MESSAGE,
                'value' => [
                    'app_destination' => 'WHATSAPP',
                ],
            ],
            'page_welcome_message' => $this->buildPageWelcomeMessage(
                $prefill !== '' ? $prefill : "Hi! I'd like more details."
            ),
        ], fn ($v) => $v !== null && $v !== '');

        $spec = [
            'page_id' => $pageId,
            'link_data' => $linkData,
        ];

        if (! empty($input['instagram_user_id'])) {
            $spec['instagram_user_id'] = (string) $input['instagram_user_id'];
        }

        return $spec;
    }

    public function buildWhatsAppLink(string $phoneE164, string $prefillMessage = ''): string
    {
        $digits = preg_replace('/\D+/', '', $phoneE164) ?? '';

        if ($digits === '') {
            throw new \InvalidArgumentException('WhatsApp phone number is required (E.164, digits only).');
        }

        $url = "https://wa.me/{$digits}";

        if ($prefillMessage !== '') {
            $url .= '?text='.rawurlencode($prefillMessage);
        }

        return $url;
    }

    /**
     * Accept a full WhatsApp URL (wa.me, api.whatsapp.com) or phone digits + optional prefill.
     */
    public function resolveWhatsAppLink(string $linkOrPhone, string $prefillMessage = ''): string
    {
        $linkOrPhone = trim($linkOrPhone);

        if ($linkOrPhone === '') {
            throw new \InvalidArgumentException('WhatsApp chat link or phone number is required.');
        }

        if (preg_match('#^https?://#i', $linkOrPhone)) {
            if (! $this->isValidWhatsAppUrl($linkOrPhone)) {
                throw new \InvalidArgumentException(
                    'Invalid WhatsApp URL. Use https://wa.me/... or https://api.whatsapp.com/send?...'
                );
            }

            if ($prefillMessage !== '' && ! preg_match('/[?&]text=/i', $linkOrPhone)) {
                $separator = str_contains($linkOrPhone, '?') ? '&' : '?';

                return $linkOrPhone.$separator.'text='.rawurlencode($prefillMessage);
            }

            return $linkOrPhone;
        }

        return $this->buildWhatsAppLink($linkOrPhone, $prefillMessage);
    }

    public function isValidWhatsAppUrl(string $url): bool
    {
        return (bool) preg_match(
            '#^https?://(wa\.me|api\.whatsapp\.com|chat\.whatsapp\.com)/#i',
            $url
        );
    }

    /**
     * Extract display phone digits from a WhatsApp URL when possible.
     */
    public function phoneFromLink(string $linkOrPhone): ?string
    {
        $linkOrPhone = trim($linkOrPhone);

        if (preg_match('#^https?://wa\.me/(\d+)#i', $linkOrPhone, $m)) {
            return $m[1];
        }

        if (preg_match('#phone=(\d+)#i', $linkOrPhone, $m)) {
            return $m[1];
        }

        $digits = preg_replace('/\D+/', '', $linkOrPhone) ?? '';

        return $digits !== '' ? $digits : null;
    }

    public function buildCreativePayload(string $name, array $input): array
    {
        return [
            'name' => $name,
            'object_story_spec' => $this->buildObjectStorySpec($input),
        ];
    }

    /**
     * Ad set settings for Click-to-WhatsApp campaigns.
     *
     * @see https://developers.facebook.com/docs/marketing-api/ad-creative/messaging-ads/click-to-whatsapp/
     * @see https://developers.facebook.com/docs/marketing-api/reference/ad-promoted-object/
     *
     * @return array<string, mixed>
     */
    public function whatsAppAdSetDefaults(
        string $pageId,
        ?string $whatsappPhoneDigits = null,
        ?string $whatsappBusinessPhoneNumberId = null
    ): array {
        $promoted = array_filter([
            'page_id' => $pageId,
            // Required for Ads Manager parity — without this Meta uses the Page's default
            // WhatsApp link, which may be a personal consumer account (error 2446885).
            'whatsapp_phone_number' => $whatsappPhoneDigits !== null && $whatsappPhoneDigits !== ''
                ? preg_replace('/\D+/', '', $whatsappPhoneDigits)
                : null,
            'whats_app_business_phone_number_id' => $whatsappBusinessPhoneNumberId
                && ctype_digit($whatsappBusinessPhoneNumberId)
                ? $whatsappBusinessPhoneNumberId
                : null,
        ], fn ($v) => $v !== null && $v !== '');

        return [
            'optimization_goal' => 'CONVERSATIONS',
            'billing_event' => 'IMPRESSIONS',
            'destination_type' => 'WHATSAPP',
            'page_id' => $pageId,
            'promoted_object' => $promoted,
        ];
    }

    /**
     * Campaign objectives suitable for Click-to-WhatsApp.
     *
     * @return array<string, string>
     */
    public static function campaignObjectives(): array
    {
        return [
            'OUTCOME_ENGAGEMENT' => 'Engagement (Messages)',
            'OUTCOME_LEADS' => 'Leads',
            'OUTCOME_SALES' => 'Sales',
            'OUTCOME_TRAFFIC' => 'Traffic',
        ];
    }

    /**
     * Default placements for Click-to-WhatsApp (Facebook + Instagram).
     *
     * @return array<string, mixed>
     */
    public static function defaultPlacements(): array
    {
        return [
            'publisher_platforms' => ['facebook', 'instagram'],
            'facebook_positions' => ['feed', 'story', 'facebook_reels'],
            'instagram_positions' => ['stream', 'story', 'reels'],
            'device_platforms' => ['mobile', 'desktop'],
        ];
    }

    /**
     * Welcome / autofill message JSON string for link_data.page_welcome_message.
     */
    public function buildPageWelcomeMessage(string $autofillContent): string
    {
        return json_encode([
            'type' => 'VISUAL_EDITOR',
            'version' => 2,
            'landing_screen_type' => 'welcome_message',
            'media_type' => 'text',
            'text_format' => [
                'customer_action_type' => 'autofill_message',
                'message' => [
                    'text' => 'Thanks for reaching out — send the message below to get started.',
                    'autofill_message' => [
                        'content' => $autofillContent,
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * @param  array<string, mixed>  $wizardData
     * @return array{messenger: bool, instagram: bool, whatsapp: bool}
     */
    public function resolveMessagingApps(array $wizardData): array
    {
        $mode = strtolower(trim((string) ($wizardData['message_destination_mode'] ?? 'automatic')));

        if ($mode !== 'manual') {
            return ['messenger' => true, 'instagram' => true, 'whatsapp' => true];
        }

        $truthy = static function ($value): bool {
            if (is_bool($value)) {
                return $value;
            }
            $v = strtolower(trim((string) $value));

            return in_array($v, ['1', 'true', 'on', 'yes'], true);
        };

        $messenger = $truthy($wizardData['dest_messenger'] ?? false);
        $instagram = $truthy($wizardData['dest_instagram'] ?? false);
        $whatsapp = $truthy($wizardData['dest_whatsapp'] ?? false);

        if (! $messenger && ! $instagram && ! $whatsapp) {
            return ['messenger' => true, 'instagram' => true, 'whatsapp' => true];
        }

        return [
            'messenger' => $messenger,
            'instagram' => $instagram,
            'whatsapp' => $whatsapp,
        ];
    }

    /**
     * @param  array{messenger: bool, instagram: bool, whatsapp: bool}  $apps
     *
     * @see https://developers.facebook.com/docs/marketing-api/ad-creative/messaging-ads/click-to-multidestination/
     */
    public function destinationTypeForApps(array $apps): string
    {
        $m = ! empty($apps['messenger']);
        $i = ! empty($apps['instagram']);
        $w = ! empty($apps['whatsapp']);
        $count = (int) $m + (int) $i + (int) $w;

        if ($count === 0) {
            throw new \InvalidArgumentException('Select at least one message destination (Messenger, Instagram, or WhatsApp).');
        }

        if ($m && $i && $w) {
            return 'MESSAGING_INSTAGRAM_DIRECT_MESSENGER_WHATSAPP';
        }
        if ($m && $i) {
            return 'MESSAGING_INSTAGRAM_DIRECT_MESSENGER';
        }
        if ($m && $w) {
            return 'MESSAGING_MESSENGER_WHATSAPP';
        }
        if ($i && $w) {
            return 'MESSAGING_INSTAGRAM_DIRECT_WHATSAPP';
        }
        if ($w) {
            return 'WHATSAPP';
        }
        if ($m) {
            return 'MESSENGER';
        }

        return 'INSTAGRAM_DIRECT';
    }

    public function isMultiDestination(string $destinationType): bool
    {
        return str_starts_with($destinationType, 'MESSAGING_');
    }

    /**
     * @param  array{messenger: bool, instagram: bool, whatsapp: bool}  $apps
     * @return list<array<string, mixed>>
     */
    public function buildMultiDestinationCallToActions(array $apps): array
    {
        $ctas = [];
        if (! empty($apps['messenger'])) {
            $ctas[] = [
                'type' => 'MESSAGE_PAGE',
                'value' => [
                    'app_destination' => 'MESSENGER',
                    'link' => 'https://fb.com/messenger_doc/',
                ],
            ];
        }
        if (! empty($apps['whatsapp'])) {
            $ctas[] = [
                'type' => self::CTA_WHATSAPP_MESSAGE,
                'value' => [
                    'app_destination' => 'WHATSAPP',
                    'link' => 'https://api.whatsapp.com/send',
                ],
            ];
        }
        if (! empty($apps['instagram'])) {
            $ctas[] = [
                'type' => 'INSTAGRAM_MESSAGE',
                'value' => [
                    'app_destination' => 'INSTAGRAM_DIRECT',
                    'link' => 'https://www.instagram.com',
                ],
            ];
        }

        return $ctas;
    }

    /**
     * Primary CTA + link for object_story_spec based on selected messaging apps.
     *
     * @param  array{messenger: bool, instagram: bool, whatsapp: bool}  $apps
     * @return array{type: string, link: string, app_destination: string}
     */
    public function primaryCtaForApps(array $apps): array
    {
        if (! empty($apps['messenger'])) {
            return [
                'type' => 'MESSAGE_PAGE',
                'link' => 'https://fb.com/messenger_doc/',
                'app_destination' => 'MESSENGER',
            ];
        }
        if (! empty($apps['whatsapp'])) {
            return [
                'type' => self::CTA_WHATSAPP_MESSAGE,
                'link' => 'https://api.whatsapp.com/send',
                'app_destination' => 'WHATSAPP',
            ];
        }

        return [
            'type' => 'INSTAGRAM_MESSAGE',
            'link' => 'https://www.instagram.com',
            'app_destination' => 'INSTAGRAM_DIRECT',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{messenger: bool, instagram: bool, whatsapp: bool}  $apps
     * @return array<string, mixed>
     */
    public function buildMessagingCreativePayload(string $name, array $input, array $apps): array
    {
        $destinationType = $this->destinationTypeForApps($apps);
        $prefill = (string) ($input['whatsapp_prefill_message'] ?? '');
        $pageId = (string) ($input['page_id'] ?? '');
        if ($pageId === '') {
            throw new \InvalidArgumentException('page_id is required for messaging creatives.');
        }

        $primary = $this->primaryCtaForApps($apps);
        $linkData = array_filter([
            'message' => $input['primary_text'] ?? $input['body'] ?? '',
            'name' => $input['headline'] ?? $input['name'] ?? '',
            'description' => $input['description'] ?? '',
            'image_hash' => $input['image_hash'] ?? null,
            'video_id' => $input['video_id'] ?? null,
            'link' => $primary['link'],
            'call_to_action' => [
                'type' => $primary['type'],
                'value' => [
                    'app_destination' => $primary['app_destination'],
                ],
            ],
            'page_welcome_message' => $this->buildPageWelcomeMessage(
                $prefill !== '' ? $prefill : "Hi! I'd like more details."
            ),
        ], fn ($v) => $v !== null && $v !== '');

        $spec = [
            'page_id' => $pageId,
            'link_data' => $linkData,
        ];

        if (! empty($input['instagram_user_id']) && (
            ! empty($apps['instagram']) || $this->isMultiDestination($destinationType)
        )) {
            $spec['instagram_user_id'] = (string) $input['instagram_user_id'];
        }

        $payload = [
            'name' => $name,
            'object_story_spec' => $spec,
        ];

        if ($this->isMultiDestination($destinationType)) {
            $payload['asset_feed_spec'] = [
                'optimization_type' => 'DOF_MESSAGING_DESTINATION',
                'call_to_actions' => $this->buildMultiDestinationCallToActions($apps),
            ];
        }

        return $payload;
    }

    /**
     * @param  array{messenger: bool, instagram: bool, whatsapp: bool}  $apps
     * @return array<string, mixed>
     */
    public function messagingAdSetDefaults(
        string $pageId,
        array $apps,
        ?string $whatsappPhoneDigits = null,
        ?string $whatsappBusinessPhoneNumberId = null
    ): array {
        $destinationType = $this->destinationTypeForApps($apps);
        $promoted = ['page_id' => $pageId];

        if (! empty($apps['whatsapp'])) {
            if ($whatsappPhoneDigits !== null && $whatsappPhoneDigits !== '') {
                $promoted['whatsapp_phone_number'] = preg_replace('/\D+/', '', $whatsappPhoneDigits);
            }
            if ($whatsappBusinessPhoneNumberId && ctype_digit($whatsappBusinessPhoneNumberId)) {
                $promoted['whats_app_business_phone_number_id'] = $whatsappBusinessPhoneNumberId;
            }
        }

        return [
            'optimization_goal' => 'CONVERSATIONS',
            'billing_event' => 'IMPRESSIONS',
            'destination_type' => $destinationType,
            'page_id' => $pageId,
            'promoted_object' => array_filter($promoted, fn ($v) => $v !== null && $v !== ''),
        ];
    }
}
