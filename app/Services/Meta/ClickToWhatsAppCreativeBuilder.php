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
     */
    public function buildObjectStorySpec(array $input): array
    {
        $pageId = (string) ($input['page_id'] ?? '');
        if ($pageId === '') {
            throw new \InvalidArgumentException('page_id is required for Click-to-WhatsApp creatives.');
        }

        $ctaType = $this->resolveCtaType();
        $whatsappLink = $this->buildWhatsAppLink(
            (string) ($input['whatsapp_phone_number'] ?? ''),
            (string) ($input['whatsapp_prefill_message'] ?? '')
        );

        $linkData = array_filter([
            'message' => $input['primary_text'] ?? $input['body'] ?? '',
            'name' => $input['headline'] ?? $input['name'] ?? '',
            'description' => $input['description'] ?? '',
            'image_hash' => $input['image_hash'] ?? null,
            'video_id' => $input['video_id'] ?? null,
            'link' => $whatsappLink,
            'call_to_action' => [
                'type' => $ctaType,
                'value' => array_filter([
                    'link' => $whatsappLink,
                    'app_destination' => 'WHATSAPP',
                ]),
            ],
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
     * @return array<string, mixed>
     */
    public function whatsAppAdSetDefaults(string $pageId): array
    {
        return [
            'optimization_goal' => 'CONVERSATIONS',
            'billing_event' => 'IMPRESSIONS',
            'destination_type' => 'WHATSAPP',
            'promoted_object' => [
                'page_id' => $pageId,
            ],
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
        ];
    }

    /**
     * Placements compatible with Click-to-WhatsApp.
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

    protected function resolveCtaType(): string
    {
        $version = config('services.meta.graph_version', 'v19.0');
        $major = (int) ltrim(explode('.', $version)[0] ?? '19', 'v');

        return $major >= 20 ? self::CTA_WHATSAPP_MESSAGE : self::CTA_SEND_MESSAGE;
    }
}
