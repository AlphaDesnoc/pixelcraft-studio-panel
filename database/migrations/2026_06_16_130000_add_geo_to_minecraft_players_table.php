<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_players', function (Blueprint $table) {
            $table->string('geo_city')->nullable()->after('ip');
            $table->string('geo_postal', 16)->nullable()->after('geo_city');
            $table->string('geo_region')->nullable()->after('geo_postal');
            $table->string('geo_country')->nullable()->after('geo_region');
            $table->string('geo_isp')->nullable()->after('geo_country');
            $table->timestamp('geo_resolved_at')->nullable()->after('geo_isp');
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_players', function (Blueprint $table) {
            $table->dropColumn([
                'geo_city',
                'geo_postal',
                'geo_region',
                'geo_country',
                'geo_isp',
                'geo_resolved_at',
            ]);
        });
    }
};
