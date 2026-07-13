<?php

namespace App\Services\Meta;

class CreativeCopyGenerator
{
    public function __construct(
        protected ClickToWhatsAppCreativeBuilder $whatsAppBuilder
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{primary_text: string, headline: string, description: string, whatsapp_prefill_message: string, wa_me_link: string|null}
     */
    public function generate(array $input, string $variant = 'A'): array
    {
        $service = trim((string) ($input['service_name'] ?? 'Our service'));
        $goal = trim((string) ($input['campaign_goal'] ?? ''));
        $audience = trim((string) ($input['target_audience'] ?? ''));
        $pain = trim((string) ($input['pain_point'] ?? ''));
        $benefit = trim((string) ($input['main_benefit'] ?? ''));
        $offer = trim((string) ($input['offer_discount'] ?? ''));
        $template = CreativeTemplateRegistry::get((string) ($input['template_key'] ?? ''));
        $templateLabel = $template['label'] ?? 'our service';

        $copy = match (strtoupper($variant)) {
            'B' => $this->urgencyCopy($service, $audience, $pain, $benefit, $offer, $templateLabel),
            'C' => $this->trustCopy($service, $audience, $pain, $benefit, $offer, $templateLabel),
            default => $this->benefitCopy($service, $audience, $pain, $benefit, $offer, $templateLabel),
        };

        if ($goal !== '') {
            $copy['primary_text'] = "Goal: {$goal}. ".$copy['primary_text'];
        }

        $phone = (string) ($input['whatsapp_chat_url'] ?? $input['whatsapp_phone_number'] ?? '');
        $waLink = null;
        if ($phone !== '') {
            try {
                $waLink = $this->whatsAppBuilder->resolveWhatsAppLink($phone, $copy['whatsapp_prefill_message']);
            } catch (\Throwable) {
                $waLink = null;
            }
        }

        return array_merge($copy, ['wa_me_link' => $waLink]);
    }

    /**
     * @return array{primary_text: string, headline: string, description: string, whatsapp_prefill_message: string}
     */
    public function generateAllVariants(array $input): array
    {
        return [
            'A' => $this->generate($input, 'A'),
            'B' => $this->generate($input, 'B'),
            'C' => $this->generate($input, 'C'),
        ];
    }

  protected function benefitCopy(string $service, string $audience, string $pain, string $benefit, string $offer, string $templateLabel): array
    {
        $painLine = $pain !== '' ? "Tired of {$pain}? " : '';
        $benefitLine = $benefit !== '' ? $benefit : "Get expert help with {$service}";
        $offerLine = $offer !== '' ? " {$offer}." : '';

        return [
            'primary_text' => "{$painLine}{$benefitLine}. Perfect for {$audience}. Tap below to chat on WhatsApp{$offerLine}",
            'headline' => "{$service} — {$benefitLine}",
            'description' => $offer !== '' ? $offer : "Chat with us on WhatsApp for {$templateLabel}",
            'whatsapp_prefill_message' => "Hi! I'm interested in {$service}. I'd like to learn more about how you can help.",
        ];
    }

    protected function urgencyCopy(string $service, string $audience, string $pain, string $benefit, string $offer, string $templateLabel): array
    {
        $offerLine = $offer !== '' ? $offer : 'Limited availability — act today';
        $painLine = $pain !== '' ? "Don't let {$pain} hold you back. " : '';

        return [
            'primary_text' => "⏰ {$painLine}{$offerLine}! {$service} for {$audience}. Message us now on WhatsApp before spots fill up.",
            'headline' => "{$offerLine} — {$service}",
            'description' => 'Reply on WhatsApp in minutes',
            'whatsapp_prefill_message' => "Hello! I saw your ad about {$service}. I want to claim: {$offerLine}.",
        ];
    }

    protected function trustCopy(string $service, string $audience, string $pain, string $benefit, string $offer, string $templateLabel): array
    {
        $benefitLine = $benefit !== '' ? $benefit : 'Trusted by clients who chose us for results';
        $painLine = $pain !== '' ? "We help {$audience} overcome {$pain}. " : '';

        return [
            'primary_text' => "✅ {$painLine}{$benefitLine}. Real support, real answers — start a WhatsApp chat with our team about {$service}.",
            'headline' => "Trusted {$service}",
            'description' => 'Speak with our team on WhatsApp',
            'whatsapp_prefill_message' => "Hi, I'd like to ask a few questions about {$service} before I decide.",
        ];
    }
}
