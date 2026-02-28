<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {

            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('category')->nullable();
            $table->string('language')->default('en');
            $table->string('meta_template_id')->nullable();

            $table->enum('status', [
                'draft',
                'pending',
                'approved',
                'rejected'
            ])->default('draft');

            $table->text('content');

            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};