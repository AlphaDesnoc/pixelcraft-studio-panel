<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->string('recurrence', 16)->nullable()->after('color');
            $table->json('recurrence_weekdays')->nullable()->after('recurrence');
            $table->date('recurrence_until')->nullable()->after('recurrence_weekdays');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn(['recurrence', 'recurrence_weekdays', 'recurrence_until']);
        });
    }
};
