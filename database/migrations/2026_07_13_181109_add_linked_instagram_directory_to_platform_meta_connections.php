<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_meta_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_meta_connections', 'linked_instagram_directory')) {
                $table->json('linked_instagram_directory')->nullable()->after('linked_instagram_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_meta_connections', function (Blueprint $table) {
            if (Schema::hasColumn('platform_meta_connections', 'linked_instagram_directory')) {
                $table->dropColumn('linked_instagram_directory');
            }
        });
    }
};
