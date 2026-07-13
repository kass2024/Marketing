<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_meta_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_meta_connections', 'linked_waba_directory')) {
                $table->json('linked_waba_directory')->nullable()->after('linked_waba_ids');
            }
            if (! Schema::hasColumn('platform_meta_connections', 'whatsapp_phone_directory')) {
                $table->json('whatsapp_phone_directory')->nullable()->after('whatsapp_phone_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_meta_connections', function (Blueprint $table) {
            if (Schema::hasColumn('platform_meta_connections', 'whatsapp_phone_directory')) {
                $table->dropColumn('whatsapp_phone_directory');
            }
            if (Schema::hasColumn('platform_meta_connections', 'linked_waba_directory')) {
                $table->dropColumn('linked_waba_directory');
            }
        });
    }
};
