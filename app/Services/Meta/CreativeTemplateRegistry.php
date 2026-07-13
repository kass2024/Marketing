<?php

namespace App\Services\Meta;

class CreativeTemplateRegistry
{
    /**
     * @return array<string, array{label: string, icon: string, default_goal: string, default_audience: string, default_pain: string, default_benefit: string, default_offer: string, cta: string}>
     */
    public static function templates(): array
    {
        return [
            'study_abroad' => [
                'label' => 'Study abroad service',
                'icon' => '🎓',
                'default_goal' => 'Generate qualified study abroad inquiries',
                'default_audience' => 'Students and parents planning international education',
                'default_pain' => 'Confusing admission requirements and visa process',
                'default_benefit' => 'Personalized guidance from application to arrival',
                'default_offer' => 'Free consultation this week',
                'cta' => 'WHATSAPP_MESSAGE',
            ],
            'cleaning_service' => [
                'label' => 'Cleaning service',
                'icon' => '🧹',
                'default_goal' => 'Book home or office cleaning appointments',
                'default_audience' => 'Busy homeowners and small businesses',
                'default_pain' => 'No time for deep cleaning',
                'default_benefit' => 'Professional, insured cleaners on your schedule',
                'default_offer' => '15% off first booking',
                'cta' => 'WHATSAPP_MESSAGE',
            ],
            'real_estate' => [
                'label' => 'Real estate service',
                'icon' => '🏠',
                'default_goal' => 'Connect buyers and renters with listings',
                'default_audience' => 'First-time buyers and relocating families',
                'default_pain' => 'Hard to find trusted local listings',
                'default_benefit' => 'Curated properties matched to your budget',
                'default_offer' => 'Free property shortlist',
                'cta' => 'WHATSAPP_MESSAGE',
            ],
            'elearning_course' => [
                'label' => 'E-learning course',
                'icon' => '💻',
                'default_goal' => 'Enroll students in online courses',
                'default_audience' => 'Professionals upskilling remotely',
                'default_pain' => 'Overwhelming course options online',
                'default_benefit' => 'Structured learning with mentor support',
                'default_offer' => 'Early-bird enrollment discount',
                'cta' => 'WHATSAPP_MESSAGE',
            ],
            'event_promotion' => [
                'label' => 'Event promotion',
                'icon' => '🎉',
                'default_goal' => 'Drive event registrations via WhatsApp',
                'default_audience' => 'Local community and event enthusiasts',
                'default_pain' => 'Missing out on limited seats',
                'default_benefit' => 'Reserve your spot in seconds on WhatsApp',
                'default_offer' => 'Limited early-bird tickets',
                'cta' => 'WHATSAPP_MESSAGE',
            ],
            'product_sale' => [
                'label' => 'Product sale',
                'icon' => '🛍️',
                'default_goal' => 'Increase product orders through chat',
                'default_audience' => 'Shoppers looking for quality deals',
                'default_pain' => 'Uncertain about product fit and delivery',
                'default_benefit' => 'Fast answers and secure ordering via WhatsApp',
                'default_offer' => 'Flash sale — limited stock',
                'cta' => 'WHATSAPP_MESSAGE',
            ],
            'consultation_booking' => [
                'label' => 'Consultation booking',
                'icon' => '📅',
                'default_goal' => 'Book discovery calls and consultations',
                'default_audience' => 'Prospects evaluating your service',
                'default_pain' => 'Long forms and slow email replies',
                'default_benefit' => 'Talk to an expert today on WhatsApp',
                'default_offer' => 'Free 15-minute consultation',
                'cta' => 'WHATSAPP_MESSAGE',
            ],
        ];
    }

    public static function goals(): array
    {
        return [
            'leads' => 'Generate leads',
            'sales' => 'Drive sales',
            'bookings' => 'Book appointments',
            'awareness' => 'Build awareness',
            'engagement' => 'Start conversations',
        ];
    }

    public static function placements(): array
    {
        // Meta Marketing API position enums:
        // facebook_positions: feed, story, facebook_reels, ...
        // instagram_positions: stream, story, reels, ...
        // See https://developers.facebook.com/docs/marketing-api/audiences/reference/placement-targeting/
        return [
            'facebook_feed' => ['platform' => 'facebook', 'position' => 'feed', 'label' => 'Facebook Feed'],
            'facebook_story' => ['platform' => 'facebook', 'position' => 'story', 'label' => 'Facebook Stories'],
            'facebook_reels' => ['platform' => 'facebook', 'position' => 'facebook_reels', 'label' => 'Facebook Reels'],
            'instagram_feed' => ['platform' => 'instagram', 'position' => 'stream', 'label' => 'Instagram Feed'],
            'instagram_story' => ['platform' => 'instagram', 'position' => 'story', 'label' => 'Instagram Stories'],
            'instagram_reels' => ['platform' => 'instagram', 'position' => 'reels', 'label' => 'Instagram Reels'],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::templates()[$key] ?? null;
    }
}
