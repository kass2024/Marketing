<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Meta delivery statuses include archived; enum was too narrow.
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->change();
        });
    }

    public function down(): void
    {
        // Best-effort revert — values outside the original enum become draft
        DB::table('campaigns')
            ->whereNotIn('status', ['draft', 'active', 'paused', 'completed'])
            ->update(['status' => 'draft']);

        Schema::table('campaigns', function (Blueprint $table) {
            $table->enum('status', ['draft', 'active', 'paused', 'completed'])->default('draft')->change();
        });
    }
};
