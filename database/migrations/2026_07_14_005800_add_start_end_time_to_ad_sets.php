<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ad_sets')) {
            return;
        }

        Schema::table('ad_sets', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_sets', 'start_time')) {
                $table->timestamp('start_time')->nullable()->after('status');
            }
            if (! Schema::hasColumn('ad_sets', 'end_time')) {
                $table->timestamp('end_time')->nullable()->after('start_time');
            }
            if (! Schema::hasColumn('ad_sets', 'destination_type')) {
                $table->string('destination_type')->nullable()->after('billing_event');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ad_sets')) {
            return;
        }

        Schema::table('ad_sets', function (Blueprint $table) {
            foreach (['start_time', 'end_time', 'destination_type'] as $col) {
                if (Schema::hasColumn('ad_sets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
