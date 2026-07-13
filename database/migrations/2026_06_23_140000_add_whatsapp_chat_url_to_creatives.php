<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            if (! Schema::hasColumn('creatives', 'whatsapp_chat_url')) {
                $table->string('whatsapp_chat_url', 2048)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            if (Schema::hasColumn('creatives', 'whatsapp_chat_url')) {
                $table->dropColumn('whatsapp_chat_url');
            }
        });
    }
};
