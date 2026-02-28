<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatbotTriggersTable extends Migration
{
    public function up()
    {
        Schema::create('chatbot_triggers', function (Blueprint $table) {

            $table->id();

            $table->foreignId('chatbot_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('trigger_type', [
                'welcome',
                'keyword'
            ]);

            $table->string('keyword')->nullable();

            $table->timestamps();

            $table->index(['chatbot_id', 'trigger_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chatbot_triggers');
    }
}