<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationsTable extends Migration
{
    public function up()
    {
        Schema::create('conversations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('chatbot_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('phone_number');

            $table->enum('status', [
                'bot',
                'human',
                'closed'
            ])->default('bot');

            $table->timestamps();

            $table->index(['client_id', 'phone_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('conversations');
    }
}