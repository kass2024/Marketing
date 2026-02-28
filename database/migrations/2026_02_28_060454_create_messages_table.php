<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {

            $table->id();

            $table->foreignId('conversation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('direction', [
                'incoming',
                'outgoing'
            ]);

            $table->enum('type', [
                'text',
                'template',
                'media'
            ])->default('text');

            $table->text('content');

            $table->enum('status', [
                'sent',
                'delivered',
                'read'
            ])->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'direction']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
}