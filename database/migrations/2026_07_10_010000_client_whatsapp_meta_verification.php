<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'whatsapp_verified_name')) {
                $table->string('whatsapp_verified_name')->nullable()->after('whatsapp_phone_number_id');
            }

            if (! Schema::hasColumn('clients', 'whatsapp_verification_status')) {
                $table->string('whatsapp_verification_status')->default('pending')->after('whatsapp_verified_name');
            }

            if (! Schema::hasColumn('clients', 'whatsapp_verified_at')) {
                $table->timestamp('whatsapp_verified_at')->nullable()->after('whatsapp_verification_status');
            }

            if (! Schema::hasColumn('clients', 'whatsapp_meta_synced_at')) {
                $table->timestamp('whatsapp_meta_synced_at')->nullable()->after('whatsapp_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach ([
                'whatsapp_meta_synced_at',
                'whatsapp_verified_at',
                'whatsapp_verification_status',
                'whatsapp_verified_name',
            ] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
