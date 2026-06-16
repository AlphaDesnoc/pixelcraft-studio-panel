<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_players', function (Blueprint $table) {
            $table->string('geo_country_code', 2)->nullable()->after('geo_country');
            $table->decimal('geo_lat', 10, 7)->nullable()->after('geo_country_code');
            $table->decimal('geo_lon', 10, 7)->nullable()->after('geo_lat');
            $table->string('geo_timezone')->nullable()->after('geo_lon');
            $table->string('geo_org')->nullable()->after('geo_isp');
            $table->string('geo_as')->nullable()->after('geo_org');
            $table->boolean('geo_proxy')->nullable()->after('geo_as');
            $table->boolean('geo_hosting')->nullable()->after('geo_proxy');
            $table->boolean('geo_mobile')->nullable()->after('geo_hosting');
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_players', function (Blueprint $table) {
            $table->dropColumn([
                'geo_country_code',
                'geo_lat',
                'geo_lon',
                'geo_timezone',
                'geo_org',
                'geo_as',
                'geo_proxy',
                'geo_hosting',
                'geo_mobile',
            ]);
        });
    }
};
