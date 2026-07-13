<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ads')) {
            return;
        }

        Schema::table('ads', function (Blueprint $table) {
            if (! Schema::hasColumn('ads', 'daily_spend_anchor')) {
                $table->decimal('daily_spend_anchor', 12, 2)->default(0)->after('daily_spend');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ads')) {
            return;
        }

        Schema::table('ads', function (Blueprint $table) {
            if (Schema::hasColumn('ads', 'daily_spend_anchor')) {
                $table->dropColumn('daily_spend_anchor');
            }
        });
    }
};
