<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatbotNodesTable extends Migration
{
    public function up()
    {
        Schema::create('chatbot_nodes', function (Blueprint $table) {

            $table->id();

            $table->foreignId('chatbot_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type', [
                'message',
                'question',
                'delay',
                'condition'
            ]);

            $table->text('content')->nullable();

            $table->json('options')->nullable(); 
            // future: quick replies, buttons, branching

            $table->unsignedBigInteger('next_node_id')->nullable();

            $table->timestamps();

            $table->index(['chatbot_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chatbot_nodes');
    }
}