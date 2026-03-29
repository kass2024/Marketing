<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $brand = (string) config('app.name', 'Xander Global Scholars');

        DB::table('ad_accounts')
            ->where('name', 'like', '%Parrot Canada%')
            ->update([
                'name' => $brand,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
