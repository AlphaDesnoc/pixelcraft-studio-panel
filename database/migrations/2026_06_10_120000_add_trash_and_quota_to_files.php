<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_nodes', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('uploader_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            // Quota de stockage en octets ; null = quota par défaut de la config.
            $table->unsignedBigInteger('storage_quota')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('file_nodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('storage_quota');
        });
    }
};
