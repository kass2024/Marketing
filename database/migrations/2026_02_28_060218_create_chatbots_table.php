<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatbotsTable extends Migration
{
    public function up()
    {
        Schema::create('chatbots', function (Blueprint $table) {

            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->boolean('is_default')->default(false);

            $table->enum('status', [
                'active',
                'inactive'
            ])->default('inactive');

            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chatbots');
    }
}