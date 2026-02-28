<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {

            $table->id();

            $table->foreignId('client_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('name');
            $table->string('objective')->nullable();

            $table->decimal('budget', 12, 2)->default(0);
            $table->decimal('spend', 12, 2)->default(0);

            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('leads')->default(0);

            $table->enum('status', [
                'draft',
                'active',
                'paused',
                'completed'
            ])->default('draft');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};