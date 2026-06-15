<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_players', function (Blueprint $table) {
            // Nom du serveur backend (derrière le proxy Velocity) où se trouve
            // actuellement le joueur. Null lorsqu'il est hors-ligne.
            $table->string('current_server')->nullable()->after('online');
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_players', function (Blueprint $table) {
            $table->dropColumn('current_server');
        });
    }
};
