<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $columns = [
                'service_name' => 'string',
                'campaign_goal' => 'string',
                'target_audience' => 'string',
                'pain_point' => 'text',
                'main_benefit' => 'text',
                'offer_discount' => 'string',
                'template_key' => 'string',
                'ab_variant' => 'string',
                'creative_group_id' => 'uuid',
                'placements' => 'json',
                'builder_inputs' => 'json',
                'is_reusable' => 'boolean',
            ];

            foreach ($columns as $name => $type) {
                if (Schema::hasColumn('creatives', $name)) {
                    continue;
                }
                match ($type) {
                    'text' => $table->text($name)->nullable(),
                    'json' => $table->json($name)->nullable(),
                    'boolean' => $table->boolean($name)->default(true),
                    'uuid' => $table->uuid($name)->nullable()->index(),
                    default => $table->string($name)->nullable(),
                };
            }
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            foreach ([
                'service_name', 'campaign_goal', 'target_audience', 'pain_point',
                'main_benefit', 'offer_discount', 'template_key', 'ab_variant',
                'creative_group_id', 'placements', 'builder_inputs', 'is_reusable',
            ] as $col) {
                if (Schema::hasColumn('creatives', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
