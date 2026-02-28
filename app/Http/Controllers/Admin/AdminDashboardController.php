<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Models\Campaign;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Template;
use App\Models\PlatformMetaConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // Basic counts (no try/catch, let Laravel show real error)

        $stats = [
            'total_users'         => class_exists(User::class) ? User::count() : 0,
            'total_clients'       => class_exists(Client::class) ? Client::count() : 0,
            'total_campaigns'     => class_exists(Campaign::class) ? Campaign::count() : 0,
            'active_campaigns'    => class_exists(Campaign::class)
                                        ? Campaign::where('status', 'active')->count()
                                        : 0,
            'total_chatbots'      => class_exists(Chatbot::class) ? Chatbot::count() : 0,
            'total_conversations' => class_exists(Conversation::class) ? Conversation::count() : 0,
            'total_messages'      => class_exists(Message::class) ? Message::count() : 0,
            'total_templates'     => class_exists(Template::class) ? Template::count() : 0,
            'messages_today'      => class_exists(Message::class)
                                        ? Message::whereDate('created_at', today())->count()
                                        : 0,
        ];

        $platformMeta = class_exists(PlatformMetaConnection::class)
                            ? PlatformMetaConnection::first()
                            : null;

        $recentClients = class_exists(Client::class)
                            ? Client::latest()->take(5)->get()
                            : collect();

        $recentCampaigns = class_exists(Campaign::class)
                            ? Campaign::latest()->take(5)->get()
                            : collect();

        $queueStats = [
            'pending_jobs' => Schema::hasTable('jobs')
                                ? DB::table('jobs')->count()
                                : 0,

            'failed_jobs'  => Schema::hasTable('failed_jobs')
                                ? DB::table('failed_jobs')->count()
                                : 0,
        ];

        return view('admin.dashboard', compact(
            'stats',
            'platformMeta',
            'recentClients',
            'recentCampaigns',
            'queueStats'
        ));
    }
}