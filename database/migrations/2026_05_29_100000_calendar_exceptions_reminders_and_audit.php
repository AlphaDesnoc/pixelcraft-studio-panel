<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('calendar_events', 'reminder_minutes')) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->unsignedSmallInteger('reminder_minutes')->nullable()->after('recurrence_until');
            });
        }

        if (! Schema::hasTable('calendar_event_exceptions')) {
            Schema::create('calendar_event_exceptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
                $table->date('occurrence_date');
                $table->string('type', 16);
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('start_at')->nullable();
                $table->timestamp('end_at')->nullable();
                $table->boolean('all_day')->nullable();
                $table->string('color', 16)->nullable();
                $table->timestamps();

                $table->unique(['calendar_event_id', 'occurrence_date'], 'cal_evt_exc_unique');
            });
        } elseif (! Schema::hasIndex('calendar_event_exceptions', 'cal_evt_exc_unique')) {
            Schema::table('calendar_event_exceptions', function (Blueprint $table) {
                $table->unique(['calendar_event_id', 'occurrence_date'], 'cal_evt_exc_unique');
            });
        }

        if (! Schema::hasTable('calendar_event_reminder_logs')) {
            Schema::create('calendar_event_reminder_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
                $table->date('occurrence_date');
                $table->unsignedSmallInteger('reminder_minutes');
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->unique(
                    ['calendar_event_id', 'occurrence_date', 'reminder_minutes'],
                    'calendar_reminder_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_reminder_logs');
        Schema::dropIfExists('calendar_event_exceptions');
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn('reminder_minutes');
        });
    }
};
