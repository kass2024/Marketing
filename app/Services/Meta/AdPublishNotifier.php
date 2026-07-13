<?php

namespace App\Services\Meta;

use App\Models\Ad;
use App\Models\Campaign;
use App\Models\PlatformMetaConnection;
use App\Services\Chatbot\MessageDispatcher;
use Illuminate\Support\Facades\Log;

class AdPublishNotifier
{
    public function __construct(
        protected MessageDispatcher $dispatcher,
        protected ClickToWhatsAppCreativeBuilder $creativeBuilder
    ) {}

    /**
     * Send a WhatsApp confirmation to the selected business number after ad publish.
     */
    public function notifyPublished(
        Campaign $campaign,
        Ad $ad,
        PlatformMetaConnection $connection,
        array $wizardData
    ): bool {
        if (! ($wizardData['notify_on_publish'] ?? true)) {
            return false;
        }

        $notifyPhone = $this->resolveNotifyPhone($wizardData, $connection);
        if ($notifyPhone === '') {
            return false;
        }

        $waDestination = trim((string) (
            $wizardData['whatsapp_chat_url']
            ?? $wizardData['whatsapp_phone_number']
            ?? $connection->whatsapp_phone_number
            ?? ''
        ));

        $deliveryLabel = $waDestination !== ''
            ? $this->formatPhoneLabel($waDestination)
            : 'your WhatsApp business number';

        $budgetCents = (int) ($wizardData['daily_budget'] ?? 0);
        $budgetDisplay = $budgetCents > 0
            ? '$'.number_format($budgetCents / 100, 2).'/day'
            : 'not set';

        $status = strtoupper((string) ($ad->status ?? 'PAUSED'));
        $statusLabel = $status === 'ACTIVE' ? 'Active' : 'Paused (review on Meta)';

        $message = implode("\n", array_filter([
            '✅ *Ad published successfully*',
            '',
            "*Campaign:* {$campaign->name}",
            "*Ad:* {$ad->name}",
            "*Status:* {$statusLabel}",
            "*Daily budget:* {$budgetDisplay}",
            "*WhatsApp delivery:* {$deliveryLabel}",
            '',
            'New conversations from this ad will arrive on your selected business number.',
            'Manage ads: '.url('/admin/campaigns/'.$campaign->id),
        ]));

        try {
            $result = $this->dispatcher->send($connection, $notifyPhone, ['text' => $message]);
            $failed = collect($result)->contains(fn ($r) => empty($r['success']));

            if ($failed) {
                Log::warning('Ad publish WhatsApp notification failed', [
                    'campaign_id' => $campaign->id,
                    'notify_phone' => $notifyPhone,
                    'result' => $result,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Ad publish WhatsApp notification exception', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function resolveNotifyPhone(array $wizardData, PlatformMetaConnection $connection): string
    {
        $explicit = trim((string) ($wizardData['notification_whatsapp_number'] ?? ''));
        if ($explicit !== '') {
            return preg_replace('/\D+/', '', $this->creativeBuilder->phoneFromLink($explicit) ?? $explicit) ?? '';
        }

        $delivery = trim((string) (
            $wizardData['whatsapp_phone_number']
            ?? $wizardData['whatsapp_chat_url']
            ?? $connection->whatsapp_phone_number
            ?? ''
        ));

        return preg_replace('/\D+/', '', $this->creativeBuilder->phoneFromLink($delivery) ?? $delivery) ?? '';
    }

    protected function formatPhoneLabel(string $phoneOrLink): string
    {
        $digits = $this->creativeBuilder->phoneFromLink($phoneOrLink) ?? preg_replace('/\D+/', '', $phoneOrLink);

        if (! $digits) {
            return $phoneOrLink;
        }

        return '+'.$digits;
    }
}
