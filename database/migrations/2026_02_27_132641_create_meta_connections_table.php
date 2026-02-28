<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_connections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('meta_user_id');
            $table->text('access_token'); // encrypted
            $table->timestamp('token_expires_at')->nullable();

            $table->timestamps();

            $table->unique('client_id'); // One connection per client
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_connections');
    }
};