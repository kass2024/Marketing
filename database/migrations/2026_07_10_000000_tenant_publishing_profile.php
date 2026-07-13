<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'whatsapp_phone_number')) {
                $table->string('whatsapp_phone_number')->nullable()->after('meta_ad_account_name');
            }

            if (! Schema::hasColumn('clients', 'whatsapp_phone_number_id')) {
                $table->string('whatsapp_phone_number_id')->nullable()->after('whatsapp_phone_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'whatsapp_phone_number_id')) {
                $table->dropColumn('whatsapp_phone_number_id');
            }
            if (Schema::hasColumn('clients', 'whatsapp_phone_number')) {
                $table->dropColumn('whatsapp_phone_number');
            }
        });
    }
};
