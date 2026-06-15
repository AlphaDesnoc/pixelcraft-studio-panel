<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_servers', function (Blueprint $table) {
            $table->string('link_code', 16)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_servers', function (Blueprint $table) {
            $table->dropUnique(['link_code']);
            $table->dropColumn('link_code');
        });
    }
};
