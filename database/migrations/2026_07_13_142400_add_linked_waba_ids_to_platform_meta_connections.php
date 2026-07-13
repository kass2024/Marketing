<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_meta_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_meta_connections', 'linked_waba_ids')) {
                $table->json('linked_waba_ids')->nullable()->after('whatsapp_business_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_meta_connections', function (Blueprint $table) {
            if (Schema::hasColumn('platform_meta_connections', 'linked_waba_ids')) {
                $table->dropColumn('linked_waba_ids');
            }
        });
    }
};
