<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class SendUnreadMessagesReport extends Command
{
    protected $signature = 'report:unread-messages';
    protected $description = 'Send unread WhatsApp messages to admin every 5 minutes';

    public function handle()
    {
        $messages = Message::with('conversation')
            ->where('direction', 'incoming')
            ->where('is_read', 0)
            ->where('reported', 0)
            ->orderBy('created_at')
            ->get();

        if ($messages->isEmpty()) {
            $this->info('No new unread messages.');
            return;
        }

        $report = "Unread WhatsApp Messages Report\n\n";

        foreach ($messages as $message) {

            $conversation = $message->conversation;

            $report .= "-----------------------------------\n";
            $report .= "Name: " . ($conversation->customer_name ?? 'N/A') . "\n";
            $report .= "Email: " . ($conversation->customer_email ?? 'N/A') . "\n";
            $report .= "Phone: " . $conversation->phone_number . "\n";
            $report .= "Message: " . $message->content . "\n";
            $report .= "Time: " . $message->created_at . "\n\n";
        }

        // Send email to admin
        Mail::raw($report, function ($mail) {
            $mail->to(env('ADMIN_EMAIL')) // define in .env
                 ->subject('New Unread WhatsApp Messages');
        });

        // Mark messages as reported
        Message::whereIn('id', $messages->pluck('id'))
            ->update(['reported' => 1]);

        $this->info('Unread messages report sent.');
    }
}