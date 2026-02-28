<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationStatesTable extends Migration
{
    public function up()
    {
        Schema::create('conversation_states', function (Blueprint $table) {

            $table->id();

            $table->foreignId('conversation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('current_node_id')
                ->nullable()
                ->constrained('chatbot_nodes')
                ->nullOnDelete();

            $table->timestamp('last_interaction_at')->nullable();

            $table->timestamps();

            $table->index(['conversation_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('conversation_states');
    }
}