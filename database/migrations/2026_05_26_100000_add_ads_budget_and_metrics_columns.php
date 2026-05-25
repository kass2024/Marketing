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
            if (! Schema::hasColumn('ads', 'ctr')) {
                $table->decimal('ctr', 8, 2)->default(0)->after('clicks');
            }

            if (! Schema::hasColumn('ads', 'daily_budget')) {
                $table->decimal('daily_budget', 12, 2)->nullable()->after('spend');
            }

            if (! Schema::hasColumn('ads', 'daily_spend')) {
                $table->decimal('daily_spend', 12, 2)->default(0)->after('daily_budget');
            }

            if (! Schema::hasColumn('ads', 'spend_date')) {
                $table->date('spend_date')->nullable()->after('daily_spend');
            }

            if (! Schema::hasColumn('ads', 'pause_reason')) {
                $table->string('pause_reason')->nullable()->after('spend_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ads')) {
            return;
        }

        Schema::table('ads', function (Blueprint $table) {
            foreach (['pause_reason', 'spend_date', 'daily_spend', 'daily_budget', 'ctr'] as $column) {
                if (Schema::hasColumn('ads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
