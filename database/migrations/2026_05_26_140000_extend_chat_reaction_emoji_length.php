<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_message_reactions', function (Blueprint $table) {
            $table->string('emoji', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_message_reactions', function (Blueprint $table) {
            $table->string('emoji', 16)->change();
        });
    }
};
